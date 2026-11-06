/*
 * JASPEST realtime client (Laravel Reverb, protokol Pusher v7).
 *
 * admin.js sudah memakai antarmuka Laravel Echo (window.Echo.private(...).listen(...)).
 * File ini menyediakan antarmuka itu tanpa npm/Vite, jadi tidak perlu build ulang aset.
 * Bila window.Echo sudah disediakan bundel lain, file ini tidak mengubah apa pun.
 *
 * Konfigurasi dikirim server lewat window.JASPEST_REALTIME (lihat config/jaspest_admin.php).
 */
(function () {
  'use strict';

  if (window.Echo) { window.JASPEST_ECHO_CONFIGURED = true; return; }

  var cfg = window.JASPEST_REALTIME;
  if (!cfg || !cfg.key || !cfg.host || typeof WebSocket === 'undefined') return;

  var secure = String(cfg.scheme || 'https') !== 'http';
  var defaultPort = secure ? 443 : 80;
  var port = Number(cfg.port) || defaultPort;
  var path = String(cfg.path || '').replace(/^\/+|\/+$/g, '');
  var url = (secure ? 'wss://' : 'ws://') + cfg.host + (port !== defaultPort ? ':' + port : '') +
    (path ? '/' + path : '') + '/app/' + encodeURIComponent(cfg.key) +
    '?protocol=7&client=js&version=8.4.0&flash=false';
  var authEndpoint = cfg.authEndpoint || '/broadcasting/auth';

  var socket = null;
  var socketId = null;
  var state = 'initialized';
  var stateListeners = [];
  var channels = {};
  var retry = 0;
  var retryTimer = null;
  var idleTimer = null;
  var pongTimer = null;
  var activityTimeout = 30000;

  function setState(next) {
    if (next === state) return;
    var previous = state;
    state = next;
    stateListeners.slice().forEach(function (fn) {
      try { fn({ previous: previous, current: next }); } catch (e) { /* listener error tidak boleh memutus koneksi */ }
    });
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function send(payload) {
    if (socket && socket.readyState === 1) socket.send(JSON.stringify(payload));
  }

  function clearTimers() {
    clearTimeout(idleTimer);
    clearTimeout(pongTimer);
  }

  // Bila tidak ada pesan selama activity_timeout, kirim ping; tanpa pong koneksi dianggap mati dan disambung ulang.
  function resetIdle() {
    clearTimers();
    idleTimer = setTimeout(function () {
      send({ event: 'pusher:ping', data: {} });
      pongTimer = setTimeout(function () { if (socket) socket.close(); }, 10000);
    }, activityTimeout);
  }

  function channelName(name) { return String(name).replace(/^private-/, ''); }

  function Channel(name) {
    this.name = name;
    this.active = false;
    this.listeners = {};
    this.subscribedCallbacks = [];
    this.errorCallbacks = [];
  }
  Channel.prototype.listen = function (event, callback) {
    var key = String(event).replace(/^\./, '');
    (this.listeners[key] = this.listeners[key] || []).push(callback);
    return this;
  };
  Channel.prototype.subscribed = function (callback) {
    this.subscribedCallbacks.push(callback);
    if (this.active) callback();
    return this;
  };
  Channel.prototype.error = function (callback) {
    this.errorCallbacks.push(callback);
    return this;
  };

  function subscribe(channel) {
    if (!socket || socket.readyState !== 1 || !socketId) return;
    var currentSocketId = socketId;
    var body = new URLSearchParams({ socket_id: currentSocketId, channel_name: 'private-' + channel.name });

    fetch(authEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(function (response) {
      if (!response.ok) { var error = new Error('Otorisasi channel ditolak (' + response.status + ')'); error.status = response.status; throw error; }
      return response.json();
    }).then(function (data) {
      // Koneksi bisa berganti selama otorisasi berjalan; jangan pakai auth untuk socket lain.
      if (!socket || socket.readyState !== 1 || socketId !== currentSocketId) return;
      send({ event: 'pusher:subscribe', data: { auth: data.auth, channel: 'private-' + channel.name } });
    }).catch(function (error) {
      channel.errorCallbacks.slice().forEach(function (callback) {
        try { callback(error); } catch (e) { /* abaikan */ }
      });
    });
  }

  function onMessage(event) {
    var message;
    try { message = JSON.parse(event.data); } catch (e) { return; }
    resetIdle();

    var data = message.data;
    if (typeof data === 'string') { try { data = JSON.parse(data); } catch (e) { /* data bukan JSON */ } }

    switch (message.event) {
      case 'pusher:connection_established':
        socketId = data && data.socket_id;
        if (data && Number(data.activity_timeout) > 0) activityTimeout = Math.max(10, Number(data.activity_timeout)) * 1000;
        retry = 0;
        resetIdle();
        setState('connected');
        Object.keys(channels).forEach(function (name) { subscribe(channels[name]); });
        return;
      case 'pusher:ping':
        send({ event: 'pusher:pong', data: {} });
        return;
      case 'pusher:pong':
        clearTimeout(pongTimer);
        return;
      case 'pusher_internal:subscription_succeeded': {
        var subscribed = channels[channelName(message.channel)];
        if (!subscribed) return;
        subscribed.active = true;
        subscribed.subscribedCallbacks.slice().forEach(function (callback) {
          try { callback(); } catch (e) { /* abaikan */ }
        });
        return;
      }
      default: {
        if (!message.channel || String(message.event).indexOf('pusher') === 0) return;
        var target = channels[channelName(message.channel)];
        var handlers = target && target.listeners[message.event];
        if (!handlers) return;
        handlers.slice().forEach(function (handler) {
          try { handler(data); } catch (e) { console.error('JASPEST realtime handler error:', e); }
        });
      }
    }
  }

  function scheduleReconnect() {
    clearTimeout(retryTimer);
    var delay = Math.min(15000, 1000 * Math.pow(2, retry));
    retry += 1;
    retryTimer = setTimeout(connect, delay);
  }

  function connect() {
    if (socket && (socket.readyState === 0 || socket.readyState === 1)) return;
    clearTimeout(retryTimer);
    setState('connecting');

    var ws;
    try { ws = new WebSocket(url); } catch (e) { setState('unavailable'); scheduleReconnect(); return; }
    socket = ws;
    socketId = null;

    ws.onmessage = onMessage;
    ws.onerror = function () { /* onclose menyusul dan menangani sambung ulang */ };
    ws.onclose = function () {
      if (socket !== ws) return;
      clearTimers();
      socket = null;
      socketId = null;
      Object.keys(channels).forEach(function (name) { channels[name].active = false; });
      setState('unavailable');
      scheduleReconnect();
    };
  }

  function reconnectNow() {
    if (!Object.keys(channels).length) return;
    if (socket && socket.readyState <= 1) return;
    retry = 0;
    connect();
  }
  window.addEventListener('online', reconnectNow);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) reconnectNow(); });

  window.Echo = {
    connector: {
      pusher: {
        connection: {
          bind: function (eventName, callback) {
            if (eventName === 'state_change' && typeof callback === 'function') stateListeners.push(callback);
          }
        }
      }
    },
    private: function (name) {
      var key = channelName(name);
      if (!channels[key]) channels[key] = new Channel(key);
      connect();
      if (socketId) subscribe(channels[key]);
      return channels[key];
    },
    disconnect: function () {
      channels = {};
      clearTimeout(retryTimer);
      clearTimers();
      if (socket) { var ws = socket; socket = null; ws.close(); }
      setState('unavailable');
    }
  };
  window.JASPEST_ECHO_CONFIGURED = true;
})();

/* JASPEST — persetujuan cookie.
   - Cookie esensial (sesi, token formulir, pilihan ini) selalu aktif.
   - Konten pihak ketiga (video TikTok) hanya dimuat setelah diizinkan.
   - Pilihan disimpan di cookie pihak pertama "jaspest_consent" selama 180 hari.
   API: window.JaspestConsent.{allowed(kategori), set(kategori, nilai), acceptAll(), rejectAll(), open()}
   Event: "jaspest:consent" pada document setiap pilihan berubah. */
(() => {
  'use strict';

  const COOKIE_NAME = 'jaspest_consent';
  const VERSION = 1;               // naikkan bila kategori/kebijakan berubah agar pengunjung ditanya ulang
  const MAX_AGE = 60 * 60 * 24 * 180;

  const CATEGORIES = [
    {
      id: 'necessary', locked: true, title: 'Esensial',
      desc: 'Diperlukan agar situs dan formulir konsultasi berfungsi dengan aman. Tidak dipakai untuk iklan atau pelacakan.',
      items: [
        '<code>*_session</code> — menjaga sesi dan keamanan formulir. Berlaku hingga 2 jam atau sampai browser ditutup.',
        '<code>XSRF-TOKEN</code> — melindungi formulir dari pengiriman palsu. Berlaku selama sesi.',
        '<code>jaspest_consent</code> — mengingat pilihan cookie Anda. Berlaku 180 hari.'
      ]
    },
    {
      id: 'media', locked: false, title: 'Konten pihak ketiga (video TikTok)',
      desc: 'Memuat pemutar video dari TikTok. TikTok dapat memasang cookie dan memproses data perangkat Anda sesuai kebijakan privasinya sendiri. Jika dimatikan, video tidak dimuat dan tautan ke TikTok tetap tersedia.',
      items: ['Layanan: <code>tiktok.com</code> (pihak ketiga). Cookie dan masa berlakunya ditentukan oleh TikTok.']
    }
  ];

  const OPTIONAL = CATEGORIES.filter((c) => !c.locked).map((c) => c.id);

  /* ---------- penyimpanan ---------- */
  function readCookie() {
    try {
      const row = document.cookie.split('; ').find((r) => r.startsWith(COOKIE_NAME + '='));
      if (!row) return null;
      const data = JSON.parse(decodeURIComponent(row.slice(COOKIE_NAME.length + 1)));
      if (!data || data.v !== VERSION || !data.c || typeof data.c !== 'object') return null;
      return data;
    } catch (e) { return null; }
  }

  function writeCookie(data) {
    try {
      const secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(data)) +
        '; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax' + secure;
    } catch (e) { /* cookie diblokir: pilihan tetap berlaku selama halaman terbuka */ }
  }

  let state = readCookie();

  const allowed = (category) => category === 'necessary' || !!(state && state.c && state.c[category] === true);

  function commit(choices) {
    const clean = {};
    OPTIONAL.forEach((id) => { clean[id] = choices[id] === true; });
    state = { v: VERSION, ts: Date.now(), c: clean };
    writeCookie(state);
    removeBanner();
    announce('Preferensi cookie disimpan.');
    document.dispatchEvent(new CustomEvent('jaspest:consent', { detail: { choices: Object.assign({}, clean) } }));
  }

  /* ---------- UI ---------- */
  let banner = null;
  let dialog = null;
  let live = null;

  function announce(message) {
    if (!live) {
      live = document.createElement('div');
      live.className = 'cc-sr';
      live.setAttribute('role', 'status');
      live.setAttribute('aria-live', 'polite');
      document.body.appendChild(live);
    }
    live.textContent = '';
    window.setTimeout(() => { live.textContent = message; }, 50);
  }

  function syncOffset() {
    if (!banner) return;
    document.documentElement.style.setProperty('--cc-h', banner.offsetHeight + 'px');
  }

  function showBanner() {
    if (banner || state) return;
    banner = document.createElement('section');
    banner.className = 'cc-banner';
    banner.id = 'cc-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Persetujuan cookie');
    banner.innerHTML =
      '<p class="cc-title">Privasi &amp; cookie</p>' +
      '<p class="cc-text">Kami memakai cookie esensial agar situs dan formulir konsultasi berfungsi. ' +
      'Cookie opsional, seperti untuk video TikTok, hanya dipasang jika Anda mengizinkannya. ' +
      'Pilihan dapat diubah kapan saja. Baca <button type="button" class="cc-inline" data-privacy>kebijakan privasi</button>.</p>' +
      '<div class="cc-actions">' +
      '<button type="button" class="cc-btn cc-btn-primary" data-cc="accept">Terima semua</button>' +
      '<button type="button" class="cc-btn cc-btn-secondary" data-cc="reject">Tolak non-esensial</button>' +
      '<button type="button" class="cc-link" data-cc="settings">Atur preferensi</button>' +
      '</div>';
    document.body.prepend(banner);
    document.documentElement.classList.add('cc-open');
    syncOffset();
    window.addEventListener('resize', syncOffset, { passive: true });
  }

  function removeBanner() {
    if (!banner) return;
    banner.remove();
    banner = null;
    document.documentElement.classList.remove('cc-open');
    document.documentElement.style.removeProperty('--cc-h');
    window.removeEventListener('resize', syncOffset);
  }

  function buildDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'cc-dialog';
    dialog.id = 'cc-dialog';
    dialog.setAttribute('aria-labelledby', 'cc-dialog-title');
    const rows = CATEGORIES.map((c) => {
      const control = c.locked
        ? '<span class="cc-always">Selalu aktif</span>'
        : '<label class="cc-switch"><input type="checkbox" role="switch" data-cc-cat="' + c.id + '">' +
          '<span class="cc-track" aria-hidden="true"></span><span class="cc-sr">Izinkan ' + c.title + '</span></label>';
      const items = c.items.map((i) => '<li>' + i + '</li>').join('');
      return '<div class="cc-cat"><div class="cc-cat-head"><h3>' + c.title + '</h3>' + control + '</div>' +
        '<p>' + c.desc + '</p><ul class="cc-list">' + items + '</ul></div>';
    }).join('');
    dialog.innerHTML =
      '<div class="cc-dialog-inner">' +
      '<div class="cc-dialog-head"><h2 id="cc-dialog-title">Pengaturan cookie</h2>' +
      '<p>Pilih jenis cookie yang boleh dipasang. Cookie esensial tidak dapat dimatikan karena dibutuhkan agar situs bekerja.</p></div>' +
      '<div class="cc-dialog-body">' + rows + '</div>' +
      '<div class="cc-dialog-foot">' +
      '<button type="button" class="cc-btn cc-btn-primary" data-cc="save">Simpan pilihan</button>' +
      '<button type="button" class="cc-btn cc-btn-secondary" data-cc="accept">Terima semua</button>' +
      '<button type="button" class="cc-btn cc-btn-secondary" data-cc="reject">Tolak non-esensial</button>' +
      '</div></div>';
    dialog.addEventListener('click', (event) => { if (event.target === dialog) closeDialog(); });
    document.body.appendChild(dialog);
  }

  function closeDialog() {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
  }

  function openSettings() {
    const content = document.getElementById('content-dialog');
    if (content && content.open && typeof content.close === 'function') content.close();
    if (!dialog) buildDialog();
    dialog.querySelectorAll('[data-cc-cat]').forEach((input) => { input.checked = allowed(input.dataset.ccCat); });
    if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    const first = dialog.querySelector('[data-cc-cat]') || dialog.querySelector('button');
    if (first) first.focus();
  }

  function setAll(value) {
    const choices = {};
    OPTIONAL.forEach((id) => { choices[id] = value; });
    return choices;
  }

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    if (target.closest('[data-cookie-settings]')) { event.preventDefault(); openSettings(); return; }

    const action = target.closest('[data-cc]');
    if (!action) return;
    const type = action.dataset.cc;

    if (type === 'settings') { openSettings(); return; }
    if (type === 'accept') { commit(setAll(true)); closeDialog(); return; }
    if (type === 'reject') { commit(setAll(false)); closeDialog(); return; }
    if (type === 'save' && dialog) {
      const choices = setAll(false);
      dialog.querySelectorAll('[data-cc-cat]').forEach((input) => { choices[input.dataset.ccCat] = input.checked; });
      commit(choices);
      closeDialog();
    }
  });

  window.JaspestConsent = {
    allowed,
    set(category, value) {
      if (!OPTIONAL.includes(category)) return;
      const choices = setAll(false);
      OPTIONAL.forEach((id) => { choices[id] = allowed(id); });
      choices[category] = value === true;
      commit(choices);
    },
    acceptAll() { commit(setAll(true)); },
    rejectAll() { commit(setAll(false)); },
    open: openSettings
  };

  const init = () => showBanner();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

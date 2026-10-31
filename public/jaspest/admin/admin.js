(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const iconPaths = {
    eye: '<path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
    whatsapp: '<path d="M20.2 11.7a8.2 8.2 0 0 1-12.1 7.2L4 20l1.1-4A8.2 8.2 0 1 1 20.2 11.7Z"/><path d="M9 8.2c.2-.5.5-.5.8-.5h.5c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.2.4 0 .6.5.9 1.2 1.5 2.1 1.9.2.1.4.1.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.5.7c.3.1.4.3.4.5 0 .4-.2 1.1-.7 1.5-.5.4-1.1.6-1.7.5-1-.1-2.3-.7-3.5-1.8-1.3-1.2-2.1-2.7-2.3-3.7-.2-.7 0-1.4.4-1.9Z"/>',
    edit: '<path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z"/>',
    trash: '<path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"/>',
  };
  function uiIcon(name) {
    return `<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${iconPaths[name] || ''}</svg>`;
  }
  const config = window.JASPEST_ADMIN_CONFIG || {};
  const apiMode = config.mode === 'api' && Boolean(config.apiBase);
  const apiBase = String(config.apiBase || '').replace(/\/$/, '');
  const csrfToken = $('meta[name="csrf-token"]')?.content || '';
  const contentLabels = {
    pages: 'Konten halaman', services: 'Layanan', pests: 'Jenis hama',
    methods: 'Metode penanganan', articles: 'Blog & artikel', gallery: 'Galeri',
    faqs: 'FAQ', settings: 'Pengaturan website'
  };
  const contentDescriptions = {
    pages: ['Atur konten halaman utama.', 'Kelola bagian hero, profil, dan informasi penting perusahaan.'],
    services: ['Atur layanan JASPEST.', 'Konten layanan aktif dapat digunakan pada pilihan kebutuhan formulir.'],
    pests: ['Kelola informasi jenis hama.', 'Tambahkan nama, ciri, foto, dan informasi identifikasi hama.'],
    methods: ['Kelola metode penanganan.', 'Jelaskan metode, kegunaan, dan prosedur keselamatan.'],
    articles: ['Kelola artikel edukasi.', 'Artikel disimpan pada modul Blog Laravel dan dapat diterbitkan atau dijadikan draf.'],
    gallery: ['Kelola dokumentasi galeri.', 'Foto yang aktif dapat ditampilkan pada galeri website publik.'],
    faqs: ['Kelola pertanyaan umum.', 'Pertanyaan dan jawaban aktif menjadi sumber informasi chatbot FAQ.'],
    settings: ['Kelola pengaturan website.', 'Kelola informasi kontak, identitas, dan pengaturan umum.']
  };
  const seedBookings = [];
  const contentSeed = { pages: [], services: [], pests: [], methods: [], articles: [], gallery: [], faqs: [], settings: [] };

  let bookings = apiMode ? [] : loadLocal('jaspest-admin-bookings', seedBookings);
  let contentData = apiMode ? clone(contentSeed) : loadLocal('jaspest-admin-content', contentSeed);
  let dashboardData = null;
  let recentBookings = [];
  let bookingMeta = { current_page: 1, last_page: 1, total: bookings.length };
  let bookingPage = 1;
  let knownTotal = null;   // total pengajuan terakhir yang diketahui (untuk deteksi pengajuan baru)
  let pollBusy = false;
  const POLL_MS = 60000;   // cek pengajuan baru tiap 60 detik
  let currentBooking = null;
  let currentContentType = 'pages';
  let editingContentId = null;

  function clone(value) { return JSON.parse(JSON.stringify(value)); }
  function loadLocal(key, fallback) {
    try { const value = JSON.parse(localStorage.getItem(key)); return value ?? clone(fallback); }
    catch { return clone(fallback); }
  }
  function saveLocal(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); return true; }
    catch { toast('Browser tidak dapat menyimpan perubahan.'); return false; }
  }
  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  }
  function toast(message) {
    const element = $('#toast');
    element.textContent = message;
    element.classList.add('show');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => element.classList.remove('show'), 3000);
  }
  function showError(message) { toast(message || 'Terjadi kesalahan. Silakan coba kembali.'); }
  function statusLabel(status) {
    return ({ new: 'Belum dihubungi', contacted: 'Sudah dihubungi', followup: 'Follow-up', converted: 'Menjadi order', lost: 'Tidak deal' })[status] || status;
  }
  function statusClass(status) {
    return ({ new: 'status-new', contacted: 'status-contacted', followup: 'status-followup', converted: 'status-converted', lost: 'status-new' })[status] || 'status-new';
  }
  function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase(); }
  function formatPhone(phone) {
    // Format tampil: +62 823-2983-7800
    let digits = String(phone || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
    else if (digits.startsWith('8')) digits = `62${digits}`;
    if (!digits.startsWith('62')) return `+${digits}`;
    const national = digits.slice(2);
    return `+62 ${[national.slice(0, 3), national.slice(3, 7), national.slice(7)].filter(Boolean).join('-')}`;
  }
  function phoneForWhatsApp(phone) {
    let number = String(phone || '').replace(/\D/g, '');
    if (number.startsWith('0')) number = `62${number.slice(1)}`;
    if (number.startsWith('8')) number = `62${number}`;
    return number;
  }
  function greeting() {
    const hour = Number(new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', hourCycle: 'h23' }).format(new Date()));
    return hour < 11 ? 'pagi' : hour < 15 ? 'siang' : hour < 18 ? 'sore' : 'malam';
  }
  function buildWhatsAppMessage(booking) {
    return `Halo, selamat ${greeting()}. Kami dari JASPEST.\n\nTerima kasih telah menghubungi kami. Berikut ringkasan pengajuan Anda:\n\nNama: ${booking.name || '-'}\nNomor WhatsApp: ${formatPhone(booking.whatsapp)}\nAlamat lokasi: ${booking.address || '-'}\nPerkiraan luasan: ${booking.area || '-'} m²\nKebutuhan layanan: ${booking.need || '-'}\nJenis lokasi: ${booking.property === 'bisnis' ? 'Bisnis' : 'Rumah'}${booking.company ? `\nNama perusahaan: ${booking.company}` : ''}\nKeterangan: ${booking.description || '-'}\n\nKami akan membantu Anda menemukan solusi penanganan hama yang tepat. Tim kami siap berdiskusi lebih lanjut mengenai kebutuhan lokasi Anda.\n\nTerima kasih.\nJASPEST — Jaya Abadi Solusi.`;
  }

  async function apiRequest(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken);
    let body = options.body;
    if (body && !(body instanceof FormData) && typeof body !== 'string') {
      headers.set('Content-Type', 'application/json');
      body = JSON.stringify(body);
    }
    const response = await fetch(`${apiBase}${path}`, {
      ...options, body, headers, credentials: 'same-origin', redirect: 'follow'
    });
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : null;
    if (!response.ok) {
      if (response.status === 401 || response.status === 419) {
        if (config.loginUrl) setTimeout(() => { window.location.href = config.loginUrl; }, 1500);
        throw new Error('Sesi login berakhir. Anda akan diarahkan ke halaman masuk.');
      }
      if (response.status === 403) throw new Error('Akun Anda tidak memiliki izin untuk tindakan ini.');
      if (response.status === 422) {
        const messages = Object.values(payload?.errors || {}).flat();
        throw new Error(messages[0] || payload?.message || 'Periksa kembali isian Anda.');
      }
      throw new Error(payload?.message || `Permintaan gagal (${response.status}).`);
    }
    if (!payload) throw new Error('Respons server tidak sesuai format JSON. Periksa rute dan konfigurasi Laravel.');
    return payload;
  }

  function updateStats(stats) {
    const values = stats || {
      total: bookings.length,
      new: bookings.filter((item) => item.status === 'new').length,
      contacted: bookings.filter((item) => item.status === 'contacted').length,
      followup: bookings.filter((item) => item.status === 'followup').length,
      converted: bookings.filter((item) => item.status === 'converted').length
    };
    knownTotal = Number(values.total ?? bookings.length);
    const waiting = Number(values.new ?? 0);
    const navBadge = $('#bookingNavCount');
    navBadge.textContent = waiting;
    navBadge.hidden = waiting === 0;
    navBadge.title = `${waiting} pengajuan belum dihubungi`;
    document.title = `${waiting > 0 ? `(${waiting}) ` : ''}Admin Dashboard | JASPEST`;
    const totalHint = $('#statTotalHint');
    if (totalHint) {
      const totalNow = Number(values.total ?? 0);
      totalHint.textContent = totalNow > 0 && values.converted !== undefined
        ? `${Math.round((Number(values.converted) / totalNow) * 100)}% menjadi order`
        : 'Sejak website aktif';
    }
    $('#statTotal').textContent = values.total ?? 0;
    $('#statNew').textContent = values.new ?? 0;
    $('#statContacted').textContent = values.contacted ?? 0;
    $('#statFollow').textContent = values.followup ?? 0;
    $('#bookingTotal').textContent = values.total ?? 0;
    $('#bookingNew').textContent = values.new ?? 0;
    $('#bookingContacted').textContent = values.contacted ?? 0;
    $('#bookingFollow').textContent = values.followup ?? 0;
    $('#attentionNew').textContent = `${values.new ?? 0} pengajuan baru`;
    $('#attentionFollow').textContent = `${values.followup ?? 0} klien follow-up`;
  }
  function renderChart(series = []) {
    const values = series.length ? series : (apiMode ? [] : [
      { label: 'Sen', total: 8 }, { label: 'Sel', total: 12 }, { label: 'Rab', total: 7 },
      { label: 'Kam', total: 15 }, { label: 'Jum', total: 10 }, { label: 'Sab', total: 18 }, { label: 'Min', total: 9 }
    ]);
    const maximum = Math.max(1, ...values.map((item) => Number(item.total || 0)));
    $('#chartBars').innerHTML = values.length ? values.map((item) => {
      const height = Math.max(2, (Number(item.total || 0) / maximum) * 100);
      return `<div class="chart-column" title="${esc(item.label)}: ${Number(item.total || 0)} pengajuan"><b>${Number(item.total || 0)}</b><i style="height:${height}%"></i><small>${esc(item.label)}</small></div>`;
    }).join('') : '<p class="muted-empty">Belum ada data booking untuk grafik.</p>';
  }
  function renderRecent() {
    const rows = (recentBookings.length ? recentBookings : bookings).slice(0, 5);
    $('#recentRows').innerHTML = rows.map((booking) => `
      <tr>
        <td><div class="client-cell"><span class="client-initial">${esc(initials(booking.name))}</span><span><strong>${esc(booking.name)}</strong><small>${esc(booking.id)}${booking.photo_count ? ` · ${booking.photo_count} foto` : ''}</small></span></div></td>
        <td>${esc(booking.need)}</td><td>${esc(booking.date)}</td>
        <td><div class="status-cell"><span class="status-pill ${statusClass(booking.status)}">${statusLabel(booking.status)}</span>${waitingBadge(booking)}</div></td>
        <td><div class="table-actions"><button class="table-action" data-detail="${esc(booking.id)}" title="Lihat detail" aria-label="Lihat detail ${esc(booking.name)}">${uiIcon('eye')}<span>Detail</span></button><button class="table-action wa" data-wa="${esc(booking.id)}" title="Chat WhatsApp" aria-label="Chat WhatsApp ${esc(booking.name)}">${uiIcon('whatsapp')}<span>WhatsApp</span></button></div></td>
      </tr>`).join('') || '<tr><td colspan="5">Belum ada data booking.</td></tr>';
  }
  function localDateKey(value) {
    const time = Date.parse(value);
    if (!Number.isFinite(time)) return '';
    // Hari dihitung menurut WIB agar sama dengan tanggal yang tampil di tabel.
    return new Date(time + 7 * 3600000).toISOString().slice(0, 10);
  }
  function inDateRange(booking) {
    const from = $('#dateFrom').value;
    const to = $('#dateTo').value;
    if (!from && !to) return true;
    const key = localDateKey(booking.created_at || booking.createdAt);
    if (!key) return false;
    return (!from || key >= from) && (!to || key <= to);
  }
  function syncDateLimits() {
    const from = $('#dateFrom').value;
    const to = $('#dateTo').value;
    $('#dateTo').min = from || '';
    $('#dateFrom').max = to || '';
    $('#dateClear').hidden = !(from || to);
  }
  function renderBookings() {
    const query = $('#bookingSearch').value.trim().toLowerCase();
    const status = $('#statusFilter').value;
    const type = $('#typeFilter').value;
    const list = apiMode ? bookings : bookings.filter((booking) =>
      (status === 'all' || booking.status === status) &&
      (type === 'all' || booking.property === type) &&
      inDateRange(booking) &&
      [booking.name, booking.whatsapp, booking.need, booking.id, booking.address].some((value) => String(value || '').toLowerCase().includes(query))
    );
    $('#bookingRows').innerHTML = list.map((booking) => `
      <tr>
        <td><div class="client-cell"><span class="client-initial">${esc(initials(booking.name))}</span><span><strong>${esc(booking.name)}</strong><small>${esc(booking.id)}${booking.photo_count ? ` · ${booking.photo_count} foto` : ''}</small></span></div></td>
        <td class="contact-number">${esc(formatPhone(booking.whatsapp))}</td>
        <td>${esc(booking.need)}</td><td>${booking.property === 'bisnis' ? 'Bisnis' : 'Rumah'}</td>
        <td>${esc(booking.date)}</td>
        <td><div class="status-cell"><span class="status-pill ${statusClass(booking.status)}">${statusLabel(booking.status)}</span>${waitingBadge(booking)}</div></td>
        <td><div class="table-actions"><button class="table-action" data-detail="${esc(booking.id)}" title="Detail lengkap" aria-label="Detail ${esc(booking.name)}">${uiIcon('eye')}<span>Detail</span></button><button class="table-action wa" data-wa="${esc(booking.id)}" title="Chat WhatsApp" aria-label="Chat ${esc(booking.name)}">${uiIcon('whatsapp')}<span>WhatsApp</span></button></div></td>
      </tr>`).join('');
    $('#emptyBookings').hidden = list.length > 0;
    const resetButton = $('#resetBookingFilters'); if (resetButton) resetButton.hidden = !filtersActive();
    const total = apiMode ? (bookingMeta.total ?? list.length) : list.length;
    $('#tableCount').textContent = `Menampilkan ${list.length} dari ${total} data`;
    $('#bookingPageLabel').textContent = `${bookingMeta.current_page || 1} / ${bookingMeta.last_page || 1}`;
    $('#bookingsPrev').disabled = (bookingMeta.current_page || 1) <= 1;
    $('#bookingsNext').disabled = (bookingMeta.current_page || 1) >= (bookingMeta.last_page || 1);
  }
  async function refreshDashboard() {
    if (!apiMode) {
      recentBookings = bookings.slice().sort((a, b) => String(b.id).localeCompare(String(a.id))).slice(0, 5);
      updateStats();
      renderRecent();
      renderChart();
      return;
    }
    dashboardData = await apiRequest('/dashboard');
    updateStats(dashboardData.stats);
    recentBookings = dashboardData.recent || [];
    renderRecent();
    renderChart(dashboardData.series || []);
    renderAttentionHint();
  }
  async function refreshBookings() {
    if (!apiMode) {
      bookingMeta = { current_page: 1, last_page: 1, total: bookings.length };
      updateStats();
      renderBookings();
      return;
    }
    const query = new URLSearchParams({
      search: $('#bookingSearch').value.trim(), status: $('#statusFilter').value,
      type: $('#typeFilter').value, page: String(bookingPage)
    });
    if ($('#dateFrom').value) query.set('date_from', $('#dateFrom').value);
    if ($('#dateTo').value) query.set('date_to', $('#dateTo').value);
    const result = await apiRequest(`/bookings?${query.toString()}`);
    bookings = result.data || [];
    bookingMeta = result.meta || { current_page: 1, last_page: 1, total: 0 };
    updateStats(dashboardData?.stats);
    renderBookings();
  }
  function renderAll() { updateStats(); renderRecent(); renderBookings(); }

  function waitingText(iso) {
    const ms = Date.now() - Date.parse(iso);
    if (!Number.isFinite(ms) || ms < 0) return '';
    const minutes = Math.floor(ms / 60000);
    if (minutes < 1) return 'kurang dari 1 menit';
    if (minutes < 60) return `${minutes} menit`;
    const hours = Math.floor(minutes / 60);
    return hours < 24 ? `${hours} jam` : `${Math.floor(hours / 24)} hari`;
  }
  function isLateWaiting(iso) { return Date.now() - Date.parse(iso) >= 3600000; }
  function waitingBadge(booking) {
    if (booking.status !== 'new' || !booking.created_at) return '';
    return `<small class="wait-note${isLateWaiting(booking.created_at) ? ' is-late' : ''}" data-created="${esc(booking.created_at)}">Menunggu ${waitingText(booking.created_at)}</small>`;
  }
  function renderAttentionHint() {
    const hint = $('#attentionNewHint');
    if (!hint) return;
    const waiting = Number(dashboardData?.stats?.new ?? 0);
    const oldest = dashboardData?.waiting?.oldest_new_at;
    hint.textContent = waiting > 0 && oldest ? `Terlama sudah menunggu ${waitingText(oldest)}` : 'Belum dihubungi melalui WhatsApp';
  }
  function refreshWaitingLabels() {
    $$('.wait-note[data-created]').forEach((element) => {
      element.textContent = `Menunggu ${waitingText(element.dataset.created)}`;
      element.classList.toggle('is-late', isLateWaiting(element.dataset.created));
    });
    renderAttentionHint();
  }
  function eventLabel(event) {
    return ({ status_changed: 'Status diperbarui', whatsapp_opened: 'WhatsApp dibuka', note_added: 'Catatan admin' })[event.event] || 'Pengajuan masuk';
  }
  function renderEventList(events) {
    if (!events.length) return '<p class="muted-empty">Belum ada riwayat aktivitas.</p>';
    const stamp = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value)) : '';
    return events.map((event) => {
      const isNote = event.event === 'note_added';
      const text = event.note || statusLabel(event.to_status || '');
      const meta = [stamp(event.created_at), event.author ? `oleh ${event.author}` : ''].filter(Boolean).join(' · ');
      return `<div class="detail-event${isNote ? ' is-note' : ''}"><strong>${esc(eventLabel(event))}</strong>${isNote
        ? `<p class="event-note">${esc(text)}</p><small>${esc(meta)}</small>`
        : `<small>${esc(text)} · ${esc(meta)}</small>`}</div>`;
    }).join('');
  }
  async function saveBookingNote() {
    if (!currentBooking) return;
    const input = $('#detailNoteInput');
    const button = $('#saveBookingNote');
    const note = input.value.trim();
    if (note.length < 2) { showError('Tulis catatan terlebih dahulu.'); input.focus(); return; }
    if (!apiMode) {
      currentBooking.events = [...(currentBooking.events || []), { event: 'note_added', note, created_at: new Date().toISOString() }];
      input.value = '';
      $('#detailEvents').innerHTML = renderEventList(currentBooking.events);
      toast('Catatan ditambahkan (mode preview).');
      return;
    }
    button.disabled = true;
    try {
      const created = await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/notes`, { method: 'POST', body: { note } });
      currentBooking.events = [...(currentBooking.events || []), created];
      input.value = '';
      $('#detailEvents').innerHTML = renderEventList(currentBooking.events);
      toast('Catatan disimpan.');
    } catch (error) { showError(error.message); }
    finally { button.disabled = false; }
  }

  function filtersActive() {
    return Boolean($('#bookingSearch').value.trim()) || $('#statusFilter').value !== 'all' || $('#typeFilter').value !== 'all' || Boolean($('#dateFrom').value) || Boolean($('#dateTo').value);
  }
  function applyBookingFilters() {
    bookingPage = 1;
    if (apiMode) refreshBookings().catch((error) => showError(error.message)); else renderBookings();
  }
  function syncSummaryFilter() {
    const current = $('#statusFilter').value;
    $$('.booking-summary [data-filter]').forEach((card) => card.setAttribute('aria-pressed', String(card.dataset.filter === current)));
  }
  async function pollNewBookings() {
    if (!apiMode || document.hidden || pollBusy) return;
    pollBusy = true;
    try {
      const before = knownTotal;
      await refreshDashboard();
      const fresh = before === null ? 0 : knownTotal - before;
      if (fresh > 0) {
        toast(fresh === 1 ? '1 pengajuan baru masuk.' : `${fresh} pengajuan baru masuk.`);
        if ($('#page-bookings').classList.contains('active')) await refreshBookings();
      }
    } catch (error) { /* polling berjalan diam-diam agar admin tidak terganggu */ }
    finally { pollBusy = false; }
  }

  function showModal(id) { $(`#${id}`).hidden = false; document.body.style.overflow = 'hidden'; }
  let pendingConfirm = null;
  function settleConfirm(value) {
    const resolve = pendingConfirm;
    pendingConfirm = null;
    if (resolve) resolve(value);
  }
  // Pengganti window.confirm: dialog bawaan situs, mengembalikan Promise<boolean>.
  function confirmDialog({ title = 'Konfirmasi', message = '', confirmText = 'Ya, lanjutkan', cancelText = 'Batal', danger = false } = {}) {
    settleConfirm(false);
    $('#confirmTitle').textContent = title;
    $('#confirmMessage').textContent = message;
    const ok = $('#confirmOk');
    ok.textContent = confirmText;
    ok.classList.toggle('btn-danger', danger);
    ok.classList.toggle('btn-primary', !danger);
    $('#confirmCancel').textContent = cancelText;
    showModal('confirmModal');
    setTimeout(() => (danger ? $('#confirmCancel') : ok).focus(), 0);
    return new Promise((resolve) => { pendingConfirm = resolve; });
  }
  function closeModal(id) {
    const modal = $(`#${id}`);
    if (!modal) return;
    if (id === 'confirmModal') settleConfirm(false);
    modal.hidden = true;
    if (!$$('.modal-backdrop:not([hidden])').length) document.body.style.overflow = '';
  }
  function renderPhoto(photo, index) {
    if (photo && typeof photo === 'object' && photo.url) {
      const name = photo.name || `Foto temuan ${index + 1}`;
      const url = esc(photo.url);
      const safeName = esc(name);
      // Use the authenticated same-origin image endpoint directly. Native browser
      // image loading avoids fetch/Blob length checks; clicking opens the full image
      // in a new tab while preserving the admin page in the current tab.
      return `<a class="photo-link" href="${url}" target="_blank" rel="noopener noreferrer" referrerpolicy="same-origin" aria-label="Buka ${safeName} ukuran penuh di tab baru" title="Klik untuk membuka foto ukuran penuh di tab baru"><img src="${url}" alt="${safeName}" loading="lazy" decoding="async" referrerpolicy="same-origin"><span>${safeName}<small>Buka ukuran penuh ↗</small></span></a>`;
    }
    return `<div class="photo-placeholder">▧<br>${esc(typeof photo === 'string' ? photo : `Foto ${index + 1}`)}</div>`;
  }
  async function openDetail(id) {
    try {
      let booking = bookings.find((item) => item.id === id || item.reference === id);
      if (apiMode) booking = await apiRequest(`/bookings/${encodeURIComponent(id)}`);
      if (!booking) return;
      currentBooking = booking;
      $('#detailName').textContent = booking.name;
      $('#detailRef').textContent = `${booking.id} · ${booking.date}`;
      $('#detailStatus').innerHTML = `<span class="status-pill ${statusClass(booking.status)}">${statusLabel(booking.status)}</span>`;
      const fields = [
        ['Nama lengkap', booking.name], ['Nomor WhatsApp', formatPhone(booking.whatsapp)],
        ['Alamat lokasi', booking.address, true], ['Perkiraan luasan', `${booking.area} m²`],
        ['Kebutuhan layanan', booking.need], ['Jenis lokasi', booking.property === 'bisnis' ? 'Bisnis' : 'Rumah'],
        ...(booking.company ? [['Nama perusahaan', booking.company]] : []),
        ['Keterangan', booking.description, true], ['Tanggal pengajuan', booking.date]
      ];
      $('#detailFields').innerHTML = fields.map(([label, value, full]) => `<div class="detail-field ${full ? 'full' : ''}"><small>${esc(label)}</small><strong>${esc(value || '-')}</strong></div>`).join('');
      $('#detailPhotos').innerHTML = booking.photos?.length ? booking.photos.map(renderPhoto).join('') : '<div class="photo-placeholder">Belum ada foto</div>';
      $('#detailConsent').textContent = booking.consent ? 'Klien menyetujui untuk dihubungi oleh tim JASPEST.' : 'Persetujuan kontak belum tercatat.';
      $('#detailWhatsapp').disabled = !booking.consent;
      $('#detailStatusSelect').value = booking.status;
      const events = booking.events || [];
      $('#detailEvents').innerHTML = renderEventList(events);
      $('#detailNoteInput').value = '';
      showModal('detailModal');
    } catch (error) { showError(error.message); }
  }
  function openWhatsApp(id) {
    const booking = bookings.find((item) => item.id === id || item.reference === id) || (currentBooking?.id === id ? currentBooking : null);
    if (!booking) { showError('Data booking tidak ditemukan.'); return; }
    if (!booking.consent) { showError('Klien belum menyetujui untuk dihubungi.'); return; }
    if (!apiMode) {
      $('#whatsappPreviewMessage').textContent = buildWhatsAppMessage(booking);
      showModal('whatsappPreviewModal');
      return;
    }
    const phone = phoneForWhatsApp(booking.whatsapp);
    if (!/^62\d{8,15}$/.test(phone)) { showError('Nomor WhatsApp tidak valid. Periksa data klien.'); return; }
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(buildWhatsAppMessage(booking))}`;
    // Buka tab langsung dari aksi klik agar tidak diblokir popup blocker.
    window.open(url, '_blank', 'noopener,noreferrer');
    if (apiMode) {
      apiRequest(`/bookings/${encodeURIComponent(id)}/whatsapp-opened`, { method: 'POST' })
        .then(() => toast('WhatsApp dibuka. Status tetap belum dihubungi sampai pesan benar-benar dikirim dan dikonfirmasi.'))
        .catch((error) => showError(error.message));
    } else {
      toast('WhatsApp dibuka. Status tidak berubah otomatis. Tandai sudah dihubungi setelah pesan terkirim.');
    }
  }
  function closeAllModals() {
    $$('.modal-backdrop:not([hidden])').forEach((modal) => closeModal(modal.id));
  }
  async function saveBookingStatus(status) {
    if (!currentBooking) return false;
    const fromStatus = currentBooking.status;
    if (status === fromStatus) return false;
    // Konfirmasi selalu muncul untuk semua perubahan status.
    const confirmed = await confirmDialog({
      title: `Ubah status menjadi “${statusLabel(status)}”?`,
      message: status === 'contacted'
        ? 'Pastikan pesan WhatsApp sudah benar-benar dikirim sebelum menyimpan status ini.'
        : `Status akan diubah dari “${statusLabel(fromStatus)}” menjadi “${statusLabel(status)}”.`,
      confirmText: 'Ya, simpan'
    });
    if (!confirmed) return false;
    try {
      if (apiMode) {
        await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/status`, { method: 'PATCH', body: { status } });
        await Promise.all([refreshDashboard(), refreshBookings()]);
      } else {
        const target = bookings.find((item) => item.id === currentBooking.id);
        if (target) { target.status = status; if (status === 'contacted') target.contactedAt = new Date().toISOString(); saveLocal('jaspest-admin-bookings', bookings); }
        currentBooking.status = status;
        updateStats(); renderRecent(); renderBookings();
      }
      // Setelah tersimpan, semua popup (konfirmasi + detail) langsung ditutup.
      closeAllModals();
      toast('Status booking berhasil diperbarui.');
      return true;
    } catch (error) { showError(error.message); return false; }
  }
  async function handleExport() {
    try {
      if (!apiMode) {
        showError('Export Excel memerlukan koneksi database Laravel. Mode preview hanya menyimpan data contoh di browser.');
        return;
      }

      const params = new URLSearchParams({
        search: $('#bookingSearch').value.trim(),
        status: $('#statusFilter').value,
        type: $('#typeFilter').value
      });
      if ($('#dateFrom').value) params.set('date_from', $('#dateFrom').value);
      if ($('#dateTo').value) params.set('date_to', $('#dateTo').value);
      const response = await fetch(`${apiBase}/bookings/export?${params.toString()}`, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      if (!response.ok) {
        let payload = null;
        if (contentType.includes('application/json')) {
          try { payload = await response.json(); } catch { /* response body unavailable */ }
        }
        if (response.status === 401 || response.status === 419) throw new Error('Sesi login admin berakhir. Silakan login kembali.');
        if (response.status === 403) throw new Error('Akun ini tidak memiliki izin mengekspor data booking.');
        throw new Error(payload?.message || `Export Excel gagal (HTTP ${response.status}).`);
      }
      if (!contentType.includes('spreadsheetml')) {
        throw new Error('Server tidak mengirim file Excel. Periksa route export dan log Laravel.');
      }

      const blob = await response.blob();
      if (!blob.size) throw new Error('File Excel kosong. Tidak ada data yang dapat diunduh.');
      const disposition = response.headers.get('content-disposition') || '';
      const filenameMatch = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
      const filename = filenameMatch
        ? decodeURIComponent(filenameMatch[1] || filenameMatch[2])
        : `JASPEST-Data-Booking-${new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date())}.xlsx`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.append(anchor);
      anchor.click();
      anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1500);
      toast('File Excel berhasil diunduh.');
    } catch (error) {
      showError(error.message);
    }
  }

  // Ikon yang sama dengan halaman website (lihat partials/icons.blade.php).
  const siteIcons = {
    shield: '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
    house: '<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/>',
    building: '<path d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1m-6 4h1m4 0h1m-4 6v-3h2v3"/>',
    bug: '<path d="m9 3 1 3m5-3-1 3M7 11H3m18 0h-4M7 15H3m18 0h-4M7 8 4 6m13 2 3-2M7 18l-3 3m13-3 3 3M12 7v13"/><rect x="7" y="6" width="10" height="15" rx="5"/>',
    mouse: '<path d="M8 9a3 3 0 1 1 4-4m1 3a3 3 0 1 1 5 4m-9-1c-4 0-6 3-6 6s3 4 6 4h6c3 0 5-2 5-5s-3-6-7-6M20 17c4 0 3-5 0-5"/><path d="M7 15h.01"/>',
    warehouse: '<path d="m3 8 9-5 9 5v13H3V8Zm4 13V11h10v10M7 15h10M7 18h10"/>',
    sparkles: '<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Zm7 0v4m-2-2h4M4 17v4m-2-2h4"/>',
    chat: '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5A8.5 8.5 0 0 1 10.5 3h2A8.5 8.5 0 0 1 21 11.5Z"/><path d="M7 11h.01M12 11h.01M17 11h.01"/>',
    camera: '<path d="M14 4H9L7 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-3-3Z"/><circle cx="12" cy="13" r="4"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8m-8 4h6"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.01"/>'
  };
  function siteIcon(name) {
    return `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${siteIcons[name] || siteIcons.info}</svg>`;
  }
  function contentIcon(type, item = {}) {
    // Layanan memakai ikon pilihan masing-masing (sama seperti kartu layanan di website).
    if (type === 'services') return siteIcon(siteIcons[item.icon] ? item.icon : 'shield');
    // Jenis hama = ikon serangga, metode penanganan = ikon perisai (sama seperti kartu direktori website).
    return siteIcon(({ pages: 'house', pests: 'bug', methods: 'shield', articles: 'file', gallery: 'camera', faqs: 'chat', settings: 'info' })[type] || 'info');
  }
  function renderContent() {
    const items = contentData[currentContentType] || [];
    const query = $('#contentSearch').value.trim().toLowerCase();
    const filtered = items.filter((item) => [item.title, item.category, item.description, item.slug].some((value) => String(value || '').toLowerCase().includes(query)));
    $('#contentList').innerHTML = filtered.map((item) => `
      <div class="content-row">
        ${(item.image_url || item.default_image_url) ? `<img class="content-row-image" src="${esc(item.image_url || item.default_image_url)}" alt="" loading="lazy">` : `<div class="content-row-icon">${contentIcon(currentContentType, item)}</div>`}
        <div class="content-row-copy"><strong>${esc(item.title)}</strong><small>${esc(item.category || 'Tanpa kategori')} · ${esc(item.description || item.slug || '')}</small></div>
        <span class="status-pill ${item.status === 'Aktif' ? 'status-contacted' : 'status-followup'}">${esc(item.status)}</span>
        <div class="content-row-actions"><button data-edit-content="${esc(item.id)}" title="Edit" aria-label="Edit ${esc(item.title)}">${uiIcon('edit')}<span>Edit</span></button>${currentContentType === 'pages' ? '' : `<button class="delete" data-delete-content="${esc(item.id)}" title="Hapus" aria-label="Hapus ${esc(item.title)}">${uiIcon('trash')}<span>Hapus</span></button>`}</div>
      </div>`).join('') || '<div class="empty-state"><span>▤</span><strong>Belum ada konten</strong><small>Tambahkan data baru atau ubah kata pencarian.</small></div>';
    $('#contentTotal').textContent = `${filtered.length} data`;
  }
  async function showContentPage(type) {
    if (!contentLabels[type]) return;
    currentContentType = type;
    const [intro, description] = contentDescriptions[type];
    $('#contentPageTitle').textContent = contentLabels[type];
    $('#contentPageDesc').textContent = description;
    $('#contentIntroTitle').textContent = intro;
    $('#contentIntroDesc').textContent = `${description} ${apiMode ? 'Perubahan disimpan ke database server.' : 'Data preview disimpan lokal di browser.'}`;
    $('#contentSearch').value = '';
    $('#addContent').hidden = type === 'pages';
    const article = type === 'articles';
    const limits = { title: article ? 160 : 180, slug: article ? 180 : 200, category: article ? 60 : 120, description: article ? 300 : 500 };
    Object.entries(limits).forEach(([name, max]) => { const field = $('#contentForm').elements.namedItem(name); if (field) field.maxLength = max; });
    const slugField = $('#contentForm').elements.namedItem('slug');
    if (slugField) slugField.readOnly = type === 'pages';
    try {
      if (apiMode) {
        const response = await apiRequest(`/content/${type}`);
        contentData[type] = response.data || [];
      }
      renderContent(); navigate('content');
    } catch (error) { showError(error.message); }
  }
  function addContent() {
    if (currentContentType === 'pages') { showError('Slot halaman utama sudah ditentukan. Edit slot yang tersedia.'); return; }
    editingContentId = null;
    $('#contentForm').reset();
    $('#contentForm').elements.sort_order.value = '0';
    $('#contentForm').elements.status.value = 'Aktif';
    $('#contentModalTitle').textContent = `Tambah ${contentLabels[currentContentType]}`;
    $('#contentForm').querySelector('[name="image"]').value = '';
    showModal('contentModal');
  }
  function editContent(id) {
    const item = (contentData[currentContentType] || []).find((entry) => String(entry.id) === String(id));
    if (!item) return;
    editingContentId = id;
    const form = $('#contentForm');
    ['title', 'slug', 'category', 'description', 'body', 'keywords', 'sort_order', 'icon', 'image_alt', 'status'].forEach((key) => {
      if (form.elements.namedItem(key)) form.elements.namedItem(key).value = item[key] ?? (key === 'sort_order' ? '0' : '');
    });
    form.elements.remove_image.checked = false;
    form.elements.image.value = '';
    form.elements.slug.readOnly = currentContentType === 'pages';
    $('#contentModalTitle').textContent = `Edit ${contentLabels[currentContentType]}`;
    showModal('contentModal');
  }
  async function saveContent(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true; submit.textContent = 'Menyimpan…';
    try {
      const formData = new FormData(form);
      if (!form.elements.remove_image.checked) formData.delete('remove_image');
      if (apiMode) {
        const path = `/content/${currentContentType}${editingContentId ? `/${encodeURIComponent(editingContentId)}` : ''}`;
        if (editingContentId) formData.append('_method', 'PUT');
        await apiRequest(path, { method: 'POST', body: formData });
        const response = await apiRequest(`/content/${currentContentType}`);
        contentData[currentContentType] = response.data || [];
      } else {
        const values = Object.fromEntries(formData.entries());
        const item = {
          id: editingContentId || `C-${Date.now()}`,
          title: String(values.title || '').trim(), slug: values.slug || '', category: values.category || '',
          description: values.description || '', body: values.body || '', sort_order: Number(values.sort_order || 0),
          icon: values.icon || '', keywords: values.keywords || '', image_alt: values.image_alt || '', status: values.status || 'Aktif'
        };
        const list = contentData[currentContentType] || (contentData[currentContentType] = []);
        if (editingContentId) {
          const index = list.findIndex((entry) => String(entry.id) === String(editingContentId));
          if (index >= 0) list[index] = { ...list[index], ...item };
        } else list.unshift(item);
        saveLocal('jaspest-admin-content', contentData);
        if (values.image?.size) toast('Upload gambar baru aktif setelah modul Laravel/MySQL dipasang.');
      }
      renderContent(); closeModal('contentModal'); toast(apiMode ? 'Konten berhasil disimpan ke database.' : 'Perubahan preview tersimpan di browser.');
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.textContent = 'Simpan Data'; }
  }
  async function deleteContent(id) {
    const item = (contentData[currentContentType] || []).find((entry) => String(entry.id) === String(id));
    if (!item) return;
    if (!(await confirmDialog({
      title: 'Hapus konten ini?',
      message: `“${item.title}” akan dihapus. Tindakan ini tidak dapat dibatalkan.`,
      confirmText: 'Hapus',
      danger: true
    }))) return;
    try {
      if (apiMode) {
        await apiRequest(`/content/${currentContentType}/${encodeURIComponent(id)}`, { method: 'DELETE' });
        const response = await apiRequest(`/content/${currentContentType}`);
        contentData[currentContentType] = response.data || [];
      } else {
        contentData[currentContentType] = contentData[currentContentType].filter((entry) => String(entry.id) !== String(id));
        saveLocal('jaspest-admin-content', contentData);
      }
      renderContent(); toast(apiMode ? 'Konten berhasil dihapus.' : 'Konten dihapus dari preview.');
    } catch (error) { showError(error.message); }
  }
  function navigate(page) {
    $$('.page-view').forEach((section) => section.classList.remove('active'));
    const target = page === 'content' ? 'content' : page;
    $(`#page-${target}`)?.classList.add('active');
    $$('.nav-link').forEach((button) => button.classList.toggle('active', button.dataset.page === page || (page === 'content' && button.dataset.page === currentContentType)));
    const label = page === 'content' ? contentLabels[currentContentType] : (page === 'bookings' ? 'Data booking' : 'Ringkasan');
    $('#breadcrumbCurrent').textContent = label;
    history.replaceState(null, '', `#${page === 'content' ? currentContentType : page}`);
    $('#sidebar').classList.remove('open');
    document.body.classList.remove('admin-menu-open');
    $('#mobileMenu').setAttribute('aria-expanded', 'false');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (page === 'bookings') refreshBookings().catch((error) => showError(error.message));
  }

  function setRealtimeStatus(state, label) {
    const badge = $('#realtimeStatus');
    if (!badge) return;
    badge.dataset.state = state;
    const text = badge.querySelector('span');
    if (text) text.textContent = label;
  }

  // Coalesce bursts of WebSocket events into complete, ordered API refreshes.
  // Both endpoints are refreshed even when the bookings page is not visible, so
  // dashboard stats, navigation badge, recent rows, chart, and booking table stay in sync.
  let realtimeRefreshPromise = null;
  let realtimeRefreshRequested = false;
  let realtimeNewReference = null;

  async function refreshAfterRealtime(payload = {}, isNew = false) {
    if (isNew) {
      // Newest bookings are on page one. Keep filters/search as selected by admin.
      bookingPage = 1;
      realtimeNewReference = payload.reference || 'Pengajuan baru';
    }

    realtimeRefreshRequested = true;
    if (realtimeRefreshPromise) return realtimeRefreshPromise;

    realtimeRefreshPromise = (async () => {
      try {
        do {
          realtimeRefreshRequested = false;

          // Dashboard endpoint contains authoritative counts, chart series and recent bookings.
          // Then fetch the current booking page so the table and pagination metadata are fresh.
          await refreshDashboard();
          await refreshBookings();

          // Keep an open detail modal in sync when its record was the one updated.
          const reference = payload.reference;
          if (!$('#detailModal')?.hidden && currentBooking &&
              (!reference || String(currentBooking.id) === String(reference))) {
            await openDetail(currentBooking.id);
          }
        } while (realtimeRefreshRequested);

        if (realtimeNewReference) {
          toast(`Pengajuan baru masuk (${realtimeNewReference}). Semua data dashboard diperbarui.`);
          realtimeNewReference = null;
        }
      } finally {
        realtimeRefreshPromise = null;
      }
    })();

    return realtimeRefreshPromise;
  }

  function connectRealtime() {
    if (!apiMode) return;
    if (!window.Echo) {
      setRealtimeStatus('offline', 'WebSocket belum dikonfigurasi');
      return;
    }

    try {
      const connection = window.Echo.connector?.pusher?.connection;
      connection?.bind('state_change', ({ current }) => {
        if (current === 'connected') setRealtimeStatus('connected', 'WebSocket: realtime aktif');
        else if (current === 'connecting' || current === 'initialized') setRealtimeStatus('connecting', 'WebSocket: menghubungkan');
        else setRealtimeStatus('offline', 'WebSocket: terputus');
      });

      const channel = window.Echo.private('jaspest.admin');
      channel.subscribed(() => {
        setRealtimeStatus('connected', 'WebSocket: realtime aktif');
        // Initial sync on every successful (re)subscription prevents missed events
        // while the browser was loading or temporarily disconnected.
        refreshAfterRealtime({}, false).catch((error) => showError(error.message));
      });
      channel.error(() => setRealtimeStatus('offline', 'WebSocket: akses ditolak / gagal'));
      channel.listen('.jaspest.enquiry.created', (payload) => {
        refreshAfterRealtime(payload, true).catch((error) => showError(error.message));
      });
      channel.listen('.jaspest.enquiry.updated', (payload) => {
        refreshAfterRealtime(payload, false).catch((error) => showError(error.message));
      });
    } catch (error) {
      setRealtimeStatus('offline', 'WebSocket gagal dimulai');
      console.error('JASPEST Reverb connection error:', error);
    }
  }

  window.addEventListener('jaspest:echo-ready', connectRealtime);
  if (window.JASPEST_ECHO_CONFIGURED) connectRealtime();

  function bindEvents() {
    document.addEventListener('click', (event) => {
      const nav = event.target.closest('[data-page]');
      if (nav) {
        const page = nav.dataset.page;
        if (contentLabels[page]) showContentPage(page); else navigate(page);
        return;
      }
      const go = event.target.closest('[data-go]');
      if (go) { const page = go.dataset.go; if (contentLabels[page]) showContentPage(page); else navigate(page); return; }
      const detail = event.target.closest('[data-detail]'); if (detail) { openDetail(detail.dataset.detail); return; }
      const wa = event.target.closest('[data-wa]'); if (wa) { openWhatsApp(wa.dataset.wa); return; }
      const close = event.target.closest('[data-close]'); if (close) { closeModal(close.dataset.close); return; }
      const edit = event.target.closest('[data-edit-content]'); if (edit) { editContent(edit.dataset.editContent); return; }
      const remove = event.target.closest('[data-delete-content]'); if (remove) deleteContent(remove.dataset.deleteContent);
    });
    let searchTimer;
    $('#bookingSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyBookingFilters, apiMode ? 350 : 0); });
    $('#statusFilter').addEventListener('change', () => { syncSummaryFilter(); applyBookingFilters(); });
    $('#typeFilter').addEventListener('change', applyBookingFilters);
    ['#dateFrom', '#dateTo'].forEach((selector) => $(selector).addEventListener('change', () => { syncDateLimits(); applyBookingFilters(); }));
    $('#dateClear').addEventListener('click', () => { $('#dateFrom').value = ''; $('#dateTo').value = ''; syncDateLimits(); applyBookingFilters(); });
    // Kartu ringkasan = filter cepat (klik lagi untuk kembali ke semua)
    $$('.booking-summary [data-filter]').forEach((card) => {
      const choose = () => {
        const target = card.dataset.filter;
        $('#statusFilter').value = ($('#statusFilter').value === target && target !== 'all') ? 'all' : target;
        syncSummaryFilter(); applyBookingFilters();
      };
      card.addEventListener('click', choose);
      card.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(); } });
    });
    $('#saveBookingNote').addEventListener('click', saveBookingNote);
    $('#detailNoteInput').addEventListener('keydown', (event) => { if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); saveBookingNote(); } });
    $('#resetBookingFilters')?.addEventListener('click', () => {
      $('#bookingSearch').value = ''; $('#statusFilter').value = 'all'; $('#typeFilter').value = 'all';
      $('#dateFrom').value = ''; $('#dateTo').value = ''; syncDateLimits();
      syncSummaryFilter(); applyBookingFilters();
    });
    syncSummaryFilter();
    // Pintasan: tekan "/" untuk langsung mencari di halaman Data booking
    document.addEventListener('keydown', (event) => {
      if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
      if (event.target.closest?.('input,textarea,select,[contenteditable]')) return;
      if (!$('#page-bookings').classList.contains('active') || $$('.modal-backdrop:not([hidden])').length) return;
      event.preventDefault(); $('#bookingSearch').focus();
    });
    // Pengajuan baru: cek otomatis berkala dan saat tab kembali dibuka
    if (apiMode) {
      setInterval(pollNewBookings, POLL_MS);
      setInterval(refreshWaitingLabels, 60000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) pollNewBookings(); });
    }
    $('#bookingsPrev').addEventListener('click', () => { if (bookingPage > 1) { bookingPage -= 1; refreshBookings().catch((error) => showError(error.message)); } });
    $('#bookingsNext').addEventListener('click', () => { if (bookingPage < bookingMeta.last_page) { bookingPage += 1; refreshBookings().catch((error) => showError(error.message)); } });
    $('#exportBookings').addEventListener('click', handleExport);
    $('#detailWhatsapp').addEventListener('click', () => currentBooking && openWhatsApp(currentBooking.id));
    $('#copyWhatsAppPreview').addEventListener('click', async () => {
      const message = $('#whatsappPreviewMessage').textContent;
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(message);
        else { const field = document.createElement('textarea'); field.value = message; document.body.append(field); field.select(); document.execCommand('copy'); field.remove(); }
        toast('Format chat berhasil disalin.');
      } catch { showError('Tidak dapat menyalin otomatis. Silakan pilih dan salin teks pesan.'); }
    });
    $('#confirmOk').addEventListener('click', () => { settleConfirm(true); closeModal('confirmModal'); });
    // Status langsung tersimpan saat dipilih; dikembalikan ke status lama bila dibatalkan atau gagal.
    $('#detailStatusSelect').addEventListener('change', async (event) => {
      const select = event.target;
      if (!currentBooking || select.value === currentBooking.status) return;
      select.disabled = true;
      const saved = await saveBookingStatus(select.value);
      select.disabled = false;
      if (!saved && currentBooking) select.value = currentBooking.status;
    });
    $('#addContent').addEventListener('click', addContent);
    $('#contentSearch').addEventListener('input', renderContent);
    $('#contentForm').addEventListener('submit', saveContent);
    $('#mobileMenu').addEventListener('click', () => {
      const isOpen = $('#sidebar').classList.toggle('open');
      document.body.classList.toggle('admin-menu-open', isOpen);
      $('#mobileMenu').setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', (event) => {
      if (!$('#sidebar').classList.contains('open')) return;
      if (event.target.closest('#sidebar, #mobileMenu')) return;
      $('#sidebar').classList.remove('open');
      document.body.classList.remove('admin-menu-open');
      $('#mobileMenu').setAttribute('aria-expanded', 'false');
    });
    $$('.modal-backdrop').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal.id); }));
    document.addEventListener('error', (event) => {
      const image = event.target;
      if (!(image instanceof HTMLImageElement) || !image.closest('#detailPhotos')) return;
      image.classList.add('photo-load-failed');
      const hint = image.closest('.photo-link')?.querySelector('small');
      if (hint) hint.textContent = 'Gagal dimuat — klik untuk coba buka tab baru';
    }, true);
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const openModals = $$('.modal-backdrop:not([hidden])');
      if (openModals.length) { closeModal(openModals[openModals.length - 1].id); return; }
      if ($('#sidebar').classList.contains('open')) {
        $('#sidebar').classList.remove('open');
        document.body.classList.remove('admin-menu-open');
        $('#mobileMenu').setAttribute('aria-expanded', 'false');
        $('#mobileMenu').focus();
      }
    });
  }

  async function init() {
    $('#todayDate').textContent = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date());
    if (apiMode) {
      $('#demoNotice').hidden = true;
      $('#bookingSourceNote').textContent = '';
      $('#contentSourceNote').textContent = 'Perubahan disimpan ke server.';
      $('#topModeChip')?.remove();
      const chip = $('.preview-chip'); if (chip) { chip.classList.add('live-chip'); chip.innerHTML = '<i></i> Terhubung ke server'; }
    }
    bindEvents();
    if (location.hash && contentLabels[location.hash.slice(1)]) {
      await showContentPage(location.hash.slice(1));
    } else if (location.hash === '#bookings') {
      navigate('bookings');
    }
    try {
      await refreshDashboard();
      await refreshBookings();
    } catch (error) {
      showError(error.message);
      if (apiMode) {
        // Jangan tampilkan contoh localStorage sebagai data produksi ketika API gagal.
        bookings = []; recentBookings = []; dashboardData = null;
        bookingMeta = { current_page: 1, last_page: 1, total: 0 };
        updateStats({ total: 0, new: 0, contacted: 0, followup: 0, converted: 0, lost: 0 });
        knownTotal = null;
        renderRecent(); renderBookings(); renderChart([]);
        $('#demoNotice').hidden = false;
        $('#demoNotice').innerHTML = `<span>!</span><div><strong>Dashboard belum dapat memuat data server</strong><small>${esc(error.message)}</small></div>`;
      }
    }
  }

  init();
})();

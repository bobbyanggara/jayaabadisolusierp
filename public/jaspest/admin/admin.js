(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  // ——— Bahasa notifikasi: semua pesan ke admin harus berbahasa Indonesia ———
  // Pesan bawaan Laravel/browser (Inggris) diterjemahkan di sini sebagai jaring pengaman,
  // walaupun locale server belum diubah ke "id".
  const FIELD_LABELS = {
    'username': 'Username', 'password': 'Password', 'name': 'Nama', 'full name': 'Nama lengkap',
    'phone': 'Nomor telepon', 'whatsapp': 'Nomor WhatsApp', 'email': 'Email', 'role': 'Divisi',
    'status': 'Status', 'title': 'Judul', 'description': 'Deskripsi', 'address': 'Alamat',
    'note': 'Catatan', 'notes': 'Catatan', 'body': 'Isi', 'excerpt': 'Ringkasan', 'category': 'Kategori',
    'slug': 'Slug', 'icon': 'Ikon', 'image': 'Gambar', 'image alt': 'Teks alternatif gambar',
    'cover': 'Gambar sampul', 'cover alt': 'Teks alternatif sampul', 'photos': 'Foto', 'photos.*': 'Foto',
    'author name': 'Nama penulis', 'company name': 'Nama perusahaan', 'property type': 'Jenis properti',
    'need': 'Kebutuhan', 'estimated area': 'Estimasi luas', 'order area': 'Luas order',
    'order price': 'Harga order', 'scheduled date': 'Tanggal jadwal', 'operational status': 'Status operasional',
    'specialization': 'Spesialisasi', 'service area': 'Wilayah layanan', 'certification': 'Sertifikasi',
    'technician code': 'Kode teknisi', 'date from': 'Tanggal mulai', 'date to': 'Tanggal akhir',
    'sort order': 'Urutan', 'is active': 'Status aktif', 'published at': 'Tanggal terbit',
    'meta title': 'Judul meta', 'meta description': 'Deskripsi meta', 'keywords': 'Kata kunci',
    'search': 'Kata pencarian', 'page': 'Halaman', 'type': 'Jenis',
  };
  function fieldLabel(raw) {
    const key = String(raw || '').trim().toLowerCase();
    const label = FIELD_LABELS[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : 'Isian');
    return label;
  }
  const MESSAGE_RULES = [
    [/failed to fetch|networkerror|load failed|network request failed|network error/i, () => 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'],
    [/failed to upload/i, () => 'Gambar gagal diunggah. Ukuran file melebihi batas server; coba gambar yang lebih kecil.'],
    [/^the (.+?) field is required when .+$/i, (m) => `${fieldLabel(m[1])} wajib diisi sesuai pilihan Anda.`],
    [/^the (.+?) field is required\.?$/i, (m) => `${fieldLabel(m[1])} wajib diisi.`],
    [/^the (.+?)(?: field)? must be at least (\d+) characters\.?$/i, (m) => `${fieldLabel(m[1])} minimal ${m[2]} karakter.`],
    [/^the (.+?)(?: field)? must not be greater than (\d+) characters\.?$/i, (m) => `${fieldLabel(m[1])} maksimal ${m[2]} karakter.`],
    [/^the (.+?)(?: field)? must not be greater than (\d+) kilobytes\.?$/i, (m) => `Ukuran ${fieldLabel(m[1]).toLowerCase()} maksimal ${m[2]} KB.`],
    [/^the (.+?)(?: field)? must not have more than (\d+) items\.?$/i, (m) => `${fieldLabel(m[1])} maksimal ${m[2]} berkas.`],
    [/^the (.+?)(?: field)? must be a valid email address\.?$/i, (m) => `${fieldLabel(m[1])} harus berupa alamat email yang valid.`],
    [/^the (.+?)(?: field)? has already been taken\.?$/i, (m) => `${fieldLabel(m[1])} sudah digunakan.`],
    [/^the (.+?)(?: field)? must be a number\.?$/i, (m) => `${fieldLabel(m[1])} harus berupa angka.`],
    [/^the (.+?)(?: field)? must be greater than (\S+?)\.?$/i, (m) => `${fieldLabel(m[1])} harus lebih besar dari ${m[2]}.`],
    [/^the (.+?)(?: field)? must be an image\.?$/i, (m) => `${fieldLabel(m[1])} harus berupa file gambar yang valid.`],
    [/^the (.+?)(?: field)? must be a file of type:.*$/i, (m) => `${fieldLabel(m[1])} harus berformat JPG, PNG, atau WebP.`],
    [/^the (.+?)(?: field)? must be a date after or equal to (.+?)\.?$/i, (m) => `${fieldLabel(m[1])} tidak boleh lebih awal dari ${fieldLabel(m[2]).toLowerCase()}.`],
    [/^the (.+?)(?: field)? does not match the format.*$/i, (m) => `Format ${fieldLabel(m[1]).toLowerCase()} tidak sesuai.`],
    [/^the (.+?)(?: field)? format is invalid\.?$/i, (m) => `Format ${fieldLabel(m[1]).toLowerCase()} tidak valid.`],
    [/^the selected (.+?) is invalid\.?$/i, (m) => `${fieldLabel(m[1])} yang dipilih tidak valid.`],
    [/^the (.+?)(?: field)? must be (?:true or false|a boolean)\.?$/i, (m) => `${fieldLabel(m[1])} tidak valid.`],
    [/^the given data was invalid\.?$/i, () => 'Periksa kembali isian Anda.'],
    [/unauthenticated/i, () => 'Sesi login berakhir. Silakan masuk kembali.'],
    [/this action is unauthorized|^forbidden\.?$/i, () => 'Akun Anda tidak memiliki izin untuk tindakan ini.'],
    [/csrf token mismatch|page expired/i, () => 'Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.'],
    [/too many (?:attempts|requests)/i, () => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'],
    [/^not found\.?$|no query results/i, () => 'Data tidak ditemukan.'],
    [/^server error\.?$|internal server error|service unavailable/i, () => 'Terjadi kesalahan pada server. Silakan coba kembali.'],
  ];
  // Kata-kata yang hampir pasti menandakan kalimat Inggris yang belum diterjemahkan.
  const ENGLISH_HINT = /\b(the|is|are|was|were|of|to|and|failed|cannot|can't|unexpected|undefined|invalid|field|must|should|network|fetch|reading|property|properties|has|have|been|not|already|taken|required|found|error|bad|request|payload|please|try|again|unable|could|couldn't|denied|timeout|timed|out|exception|null|illegal|missing)\b/i;
  function localizeMessage(message, fallback = 'Terjadi kesalahan. Silakan coba kembali.') {
    const text = String(message ?? '').trim();
    if (!text) return fallback;
    for (const [pattern, build] of MESSAGE_RULES) {
      const match = text.match(pattern);
      if (match) return build(match);
    }
    return ENGLISH_HINT.test(text) ? fallback : text;
  }

  // ——— Notifikasi (toast) ———
  // Tumpukan di kanan atas, tepat di bawah topbar. API lama toast(pesan) tetap valid;
  // opsi: toast(pesan, 'success'|'error'|'warning'|'info') atau toast(pesan, { type, title, key, duration, action }).
  const TOAST_TYPES = {
    success: { title: 'Berhasil', duration: 4500, role: 'status', icon: '<circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.8 2.8L16 9.8"/>' },
    error: { title: 'Gagal', duration: 7000, role: 'alert', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.2M12 16.3v.2"/>' },
    warning: { title: 'Perhatian', duration: 6000, role: 'alert', icon: '<path d="M12 4 3 19.5h18L12 4Z"/><path d="M12 10v4.2M12 17v.2"/>' },
    info: { title: 'Informasi', duration: 5000, role: 'status', icon: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.8v.2"/>' },
  };
  const TOAST_MAX = 4;
  const TOAST_CLOSE_ICON = '<path d="m6 6 12 12M18 6 6 18"/>';
  function toastSvg(paths) {
    return `<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${paths}</svg>`;
  }
  function inferToastType(text) {
    if (/gagal|kesalahan|tidak dapat|tidak valid|belum berhasil|ditolak/i.test(text)) return 'error';
    if (/berhasil|disimpan|ditambahkan|dihapus|dibuat|diperbarui|diunduh|disalin|dibuka|dipertahankan/i.test(text)) return 'success';
    return 'info';
  }
  function toastHost() {
    let host = document.getElementById('toastStack');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toastStack';
      host.className = 'toast-stack';
      host.setAttribute('role', 'region');
      host.setAttribute('aria-label', 'Notifikasi');
      document.body.appendChild(host);
    }
    return host;
  }
  // Posisi vertikal mengikuti tinggi topbar (berbeda per ukuran layar) agar tombol tema/profil tidak tertutup.
  function positionToastHost() {
    const bar = document.querySelector('.topbar');
    const bottom = bar ? Math.round(bar.getBoundingClientRect().bottom) : 0;
    const top = Math.min(Math.max(bottom, 0), 160) + 12;
    toastHost().style.setProperty('--toast-top', `${top}px`);
  }
  function setToastProgress(element, ms, total) {
    const bar = element._bar;
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.transform = `scaleX(${Math.max(0, Math.min(1, ms / total))})`;
    void bar.offsetWidth;
    bar.style.transition = `transform ${Math.max(ms, 0)}ms linear`;
    bar.style.transform = 'scaleX(0)';
  }
  function armToast(element, ms) {
    clearTimeout(element._timer);
    element._left = ms;
    element._started = Date.now();
    element._paused = false;
    element.classList.remove('is-paused');
    setToastProgress(element, ms, element._total);
    element._timer = setTimeout(() => dismissToast(element), ms);
  }
  function pauseToast(element) {
    if (element._paused || element._leaving) return;
    element._paused = true;
    clearTimeout(element._timer);
    element._left = Math.max(1500, element._left - (Date.now() - element._started));
    element.classList.add('is-paused');
    if (element._bar) {
      const ratio = Math.max(0, Math.min(1, element._left / element._total));
      element._bar.style.transition = 'none';
      element._bar.style.transform = `scaleX(${ratio})`;
    }
  }
  function resumeToast(element) {
    if (!element._paused || element._leaving) return;
    armToast(element, element._left);
  }
  function dismissToast(element) {
    if (!element || element._leaving) return;
    element._leaving = true;
    clearTimeout(element._timer);
    element.classList.remove('is-in');
    element.classList.add('is-out');
    const remove = () => element.remove();
    element.addEventListener('transitionend', remove, { once: true });
    setTimeout(remove, 280); // cadangan bila transisi dinonaktifkan (prefers-reduced-motion)
  }
  function toast(message, options) {
    const text = String(message ?? '').trim();
    if (!text) return null;
    const opts = typeof options === 'string' ? { type: options } : (options || {});
    const type = TOAST_TYPES[opts.type] ? opts.type : inferToastType(text);
    const def = TOAST_TYPES[type];
    const title = opts.title || def.title;
    const duration = Number(opts.duration) > 0 ? Number(opts.duration) : def.duration;
    const key = opts.key || `${type}:${text}`;
    const host = toastHost();
    positionToastHost();

    // Pesan yang sama (atau kunci yang sama) tidak ditumpuk: perbarui kartu yang ada.
    const existing = [...host.children].find((node) => node._key === key && !node._leaving);
    if (existing) {
      if (existing._text === text) {
        existing._count = (existing._count || 1) + 1;
        existing._countEl.textContent = `×${existing._count}`;
        existing._countEl.hidden = false;
      } else {
        existing._text = text;
        existing._count = 1;
        existing._countEl.hidden = true;
        existing._textEl.textContent = text;
      }
      existing._titleEl.textContent = title;
      existing._total = duration;
      existing.classList.remove('is-bump');
      void existing.offsetWidth;
      existing.classList.add('is-bump');
      armToast(existing, duration);
      return existing;
    }

    // Batasi jumlah kartu: yang paling lama ditutup lebih dulu.
    const live = [...host.children].filter((node) => !node._leaving);
    live.slice(0, Math.max(0, live.length - (TOAST_MAX - 1))).forEach(dismissToast);

    const element = document.createElement('div');
    element.className = `toast toast-${type}`;
    element.setAttribute('role', def.role);
    element.innerHTML =
      `<span class="toast-icon" aria-hidden="true">${toastSvg(def.icon)}</span>` +
      '<div class="toast-body">' +
        '<div class="toast-head"><strong class="toast-title"></strong><span class="toast-count" hidden></span></div>' +
        '<div class="toast-text"></div>' +
      '</div>' +
      `<button type="button" class="toast-close" aria-label="Tutup notifikasi">${toastSvg(TOAST_CLOSE_ICON)}</button>` +
      '<span class="toast-progress" aria-hidden="true"></span>';
    element._titleEl = element.querySelector('.toast-title');
    element._textEl = element.querySelector('.toast-text');
    element._countEl = element.querySelector('.toast-count');
    element._bar = element.querySelector('.toast-progress');
    element._titleEl.textContent = title;   // textContent: aman dari injeksi HTML
    element._textEl.textContent = text;
    element._key = key;
    element._text = text;
    element._count = 1;
    element._total = duration;

    if (opts.action && typeof opts.action.onClick === 'function' && opts.action.label) {
      const action = document.createElement('button');
      action.type = 'button';
      action.className = 'toast-action';
      action.textContent = String(opts.action.label);
      action.addEventListener('click', () => {
        try { opts.action.onClick(); } finally { dismissToast(element); }
      });
      element.querySelector('.toast-body').appendChild(action);
    }

    element.querySelector('.toast-close').addEventListener('click', () => dismissToast(element));
    element.addEventListener('mouseenter', () => pauseToast(element));
    element.addEventListener('mouseleave', () => { if (!element.matches(':focus-within')) resumeToast(element); });
    element.addEventListener('focusin', () => pauseToast(element));
    element.addEventListener('focusout', () => { if (!element.matches(':hover')) resumeToast(element); });

    host.appendChild(element);
    void element.offsetWidth;            // reflow sinkron agar transisi masuk selalu berjalan
    element.classList.add('is-in');
    armToast(element, duration);
    return element;
  }
  function showError(message) { return toast(localizeMessage(message, 'Terjadi kesalahan. Silakan coba kembali.'), 'error'); }
  function showWarning(message) { return toast(localizeMessage(message, 'Periksa kembali data Anda.'), 'warning'); }
  function announceNewBooking(message) {
    const onBookings = document.getElementById('page-bookings')?.classList.contains('active');
    return toast(message, {
      type: 'info',
      title: 'Pengajuan baru',
      key: 'new-booking',   // polling & realtime memakai kunci sama → satu kartu, bukan dua
      action: onBookings ? null : { label: 'Lihat data booking', onClick: () => navigate('bookings') },
    });
  }
  // Tab tersembunyi: jeda hitung mundur agar notifikasi tidak kedaluwarsa tanpa terbaca.
  document.addEventListener('visibilitychange', () => {
    const cards = [...document.querySelectorAll('#toastStack .toast')];
    cards.forEach((card) => {
      if (document.hidden) pauseToast(card);
      else if (!card.matches(':hover') && !card.matches(':focus-within')) resumeToast(card);
    });
  });
  window.addEventListener('resize', () => { if (document.getElementById('toastStack')) positionToastHost(); });
  const iconPaths = {
    plus: '<path d="M12 5v14M5 12h14"/>',
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
  const canManageAccounts = Boolean(config.canManageAccounts);
  const csrfToken = $('meta[name="csrf-token"]')?.content || '';
  const contentLabels = {
    pages: 'Konten Halaman', services: 'Layanan', pests: 'Jenis Hama',
    methods: 'Metode Penanganan', articles: 'Blog & Artikel', gallery: 'Galeri',
    faqs: 'FAQ', settings: 'Pengaturan Website'
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
  let accounts = [];
  let technicians = [];
  let clients = [];
  let accountMeta = { current_page: 1, last_page: 1, total: 0 };
  let technicianMeta = { current_page: 1, last_page: 1, total: 0 };
  let clientMeta = { current_page: 1, last_page: 1, total: 0 };
  let accountPage = 1;
  let technicianPage = 1;
  let clientPage = 1;
  let accountEditingId = null;
  let technicianEditingId = null;
  let clientEditingId = null;
  let accountSearchTimer = null;
  let technicianSearchTimer = null;
  let clientSearchTimer = null;
  let inventoryItems = [];
  let inventoryMovements = [];
  let inventoryTechnicianStocks = [];
  let inventoryMaintenanceRows = [];
  let inventoryOptions = { items: [], assets: [], technicians: [], orders: [] };
  let inventoryItemMeta = { current_page: 1, last_page: 1, total: 0 };
  let inventoryMovementMeta = { current_page: 1, last_page: 1, total: 0 };
  let inventoryTechStockMeta = { current_page: 1, last_page: 1, total: 0 };
  let inventoryMaintenanceMeta = { current_page: 1, last_page: 1, total: 0 };
  let inventoryItemPage = 1;
  let inventoryMovementPage = 1;
  let inventoryTechStockPage = 1;
  let inventoryMaintenancePage = 1;
  let inventoryItemEditingId = null;
  let inventorySearchTimers = { items: null, movements: null, technicians: null };
  let inventoryTab = 'items';
  // Read-only Data Master catalog: independent pagination and filters, shared inventory API.
  let stockCatalogItems = [];
  let stockCatalogMeta = { current_page: 1, last_page: 1, total: 0 };
  let stockCatalogPage = 1;
  let stockCatalogSearchTimer = null;

  function inventoryQuantity(value) {
    const amount = Number(value);
    return Number.isFinite(amount) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(amount) : '0';
  }
  function inventoryDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', {
      day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta'
    }).format(date);
  }
  function inventoryMovementLabel(type) {
    return ({ stock_in: 'Stok masuk', stock_out: 'Stok keluar', distribution: 'Distribusi teknisi', usage: 'Pemakaian pekerjaan', return: 'Pengembalian', adjustment_in: 'Penyesuaian masuk', adjustment_out: 'Penyesuaian keluar', write_off: 'Pemusnahan / barang kedaluwarsa' })[type] || type;
  }
  function inventoryMovementTone(type) {
    if (['stock_in', 'adjustment_in', 'return'].includes(type)) return 'in';
    if (['stock_out', 'adjustment_out', 'write_off'].includes(type)) return 'out';
    return 'neutral';
  }
  function inventoryStatusLabel(status) {
    return ({ planned: 'Terjadwal', completed: 'Selesai', cancelled: 'Dibatalkan' })[status] || status;
  }
  async function refreshInventoryOptions() {
    const response = await apiRequest('/inventory/options');
    inventoryOptions = {
      items: response.items || [], assets: response.assets || [],
      technicians: response.technicians || [], orders: response.orders || []
    };
    const options = (items, placeholder, render) => `<option value="">${esc(placeholder)}</option>${items.map(render).join('')}`;
    $('#inventoryMovementItem').innerHTML = options(inventoryOptions.items, 'Pilih barang', (item) => `<option value="${esc(item.id)}">${esc(item.name)} · ${esc(item.sku)} · Gudang ${inventoryQuantity(item.stock_on_hand)} ${esc(item.unit)}</option>`);
    $('#inventoryMovementTechnician').innerHTML = options(inventoryOptions.technicians, 'Pilih teknisi', (tech) => `<option value="${esc(tech.id)}">${esc(tech.name)} · ${esc(tech.code)}${tech.status === 'assigned' ? ' · Sedang bertugas' : ''}</option>`);
    $('#inventoryMovementOrder').innerHTML = options(inventoryOptions.orders, 'Pilih Deal Order', (order) => `<option value="${esc(order.id)}">${esc(order.reference)} · ${esc(order.name)}</option>`);
    $('#inventoryMaintenanceItem').innerHTML = options(inventoryOptions.assets, 'Pilih aset', (item) => `<option value="${esc(item.id)}">${esc(item.name)} · ${esc(item.sku)}</option>`);
    $('#inventoryMaintenanceTechnician').innerHTML = options(inventoryOptions.technicians, 'Belum ditentukan', (tech) => `<option value="${esc(tech.id)}">${esc(tech.name)} · ${esc(tech.code)}</option>`);
    syncInventoryMovementFields();
  }
  async function refreshInventorySummary() {
    const response = await apiRequest('/inventory/summary');
    const summary = response.summary || {};
    $('#inventoryStatItems').textContent = inventoryQuantity(summary.items || 0);
    $('#inventoryStatStockedItems').textContent = inventoryQuantity(summary.stocked_items || 0);
    $('#inventoryStatLow').textContent = inventoryQuantity(summary.low_stock || 0);
    $('#inventoryStatValue').textContent = formatRupiah(summary.stock_value || 0);
    $('#inventoryStatExpiry').textContent = inventoryQuantity(summary.expiring_batches || 0);
    $('#inventoryStatMaintenance').textContent = inventoryQuantity(summary.maintenance_due || 0);
    const low = response.low_stock_items || [];
    $('#inventoryLowStockList').innerHTML = low.length ? low.map((item) => `
      <div class="inventory-alert-row"><span><strong>${esc(item.name)}</strong><small>${esc(item.sku)} · Minimum ${inventoryQuantity(item.minimum_stock)} ${esc(item.unit)}</small></span><span class="inventory-alert-value">${inventoryQuantity(item.stock_on_hand)} ${esc(item.unit)}</span></div>`).join('')
      : '<p class="inventory-empty-inline">Tidak ada stok yang berada di bawah batas minimum.</p>';
    const expiring = response.expiring_items || [];
    const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
    $('#inventoryExpiryList').innerHTML = expiring.length ? expiring.map((batch) => {
      const expiryDate = batch.expires_at ? new Date(`${batch.expires_at}T00:00:00+07:00`) : null;
      const expired = expiryDate && batch.expires_at < today;
      return `<div class="inventory-alert-row"><span><strong>${esc(batch.name || 'Barang tidak tersedia')}</strong><small>${esc(batch.batch_number || 'Tanpa nomor batch')} · Total ${inventoryQuantity(batch.quantity_remaining)} ${esc(batch.unit || '')} · Gudang ${inventoryQuantity(batch.warehouse_quantity)} · Teknisi ${inventoryQuantity(batch.technician_quantity)}</small></span><span class="inventory-alert-value ${expired ? 'is-expired' : ''}">${expired ? 'Kedaluwarsa' : formatDateLong(batch.expires_at)}</span></div>`;
    }).join('') : '<p class="inventory-empty-inline">Tidak ada batch yang mendekati tanggal kedaluwarsa.</p>';
  }
  function renderInventoryItems() {
    $('#inventoryItemRows').innerHTML = inventoryItems.map((item) => `
      <tr>
        <td><div class="inventory-item-name"><strong>${esc(item.name)}</strong><small>${esc(item.sku)}${item.brand ? ` · ${esc(item.brand)}` : ''}</small></div></td>
        <td>${esc(item.category)}</td>
        <td><span class="inventory-type-tag ${item.item_type === 'asset' ? 'asset' : 'consumable'}">${item.item_type === 'asset' ? 'Aset' : 'Bahan habis pakai'}</span>${item.track_expiry ? '<small class="inventory-secondary" style="display:block">Lacak kedaluwarsa</small>' : ''}</td>
        <td><div class="inventory-stock-value"><strong class="${item.stock_state === 'low' ? 'inventory-stock-low' : 'inventory-stock-normal'}">${inventoryQuantity(item.stock_on_hand)} ${esc(item.unit)}</strong><small>Minimum ${inventoryQuantity(item.minimum_stock)}</small></div></td>
        <td><div class="inventory-stock-value"><strong>${inventoryQuantity(item.technician_stock)} ${esc(item.unit)}</strong><small>Di seluruh teknisi</small></div></td>
        <td><span class="inventory-status-badge ${item.is_active ? 'completed' : 'cancelled'}">${item.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
        <td><div class="inventory-row-actions"><button type="button" class="table-action" data-edit-inventory-item="${esc(item.id)}" aria-label="Edit ${esc(item.name)}">${uiIcon('edit')}<span>Edit</span></button><button type="button" class="table-action delete" data-delete-inventory-item="${esc(item.id)}" aria-label="Hapus ${esc(item.name)}">${uiIcon('trash')}<span>Hapus</span></button></div></td>
      </tr>`).join('');
    $('#inventoryItemsEmpty').hidden = inventoryItems.length > 0;
    $('#inventoryItemCount').textContent = `${inventoryItemMeta.total || 0} barang`;
    $('#inventoryItemTableCount').textContent = `Menampilkan ${inventoryItems.length} dari ${inventoryItemMeta.total || 0} barang`;
    $('#inventoryItemsPageLabel').textContent = `${inventoryItemMeta.current_page || 1} / ${inventoryItemMeta.last_page || 1}`;
    $('#inventoryItemsPrev').disabled = (inventoryItemMeta.current_page || 1) <= 1;
    $('#inventoryItemsNext').disabled = (inventoryItemMeta.current_page || 1) >= (inventoryItemMeta.last_page || 1);
  }
  async function refreshInventoryItems() {
    const params = new URLSearchParams({
      search: $('#inventoryItemSearch').value.trim(),
      type: $('#inventoryItemTypeFilter').value || 'all',
      status: $('#inventoryItemStatusFilter').value || 'active',
      page: String(inventoryItemPage),
    });
    const response = await apiRequest(`/inventory/items?${params.toString()}`);
    inventoryItems = response.data || [];
    inventoryItemMeta = response.meta || { current_page: 1, last_page: 1, total: inventoryItems.length };
    renderInventoryItems();
  }
  function renderInventoryMovements() {
    $('#inventoryMovementRows').innerHTML = inventoryMovements.map((movement) => {
      const detail = movement.technician_name ? `Teknisi: ${esc(movement.technician_name)}` : '';
      const order = movement.order_reference ? `Order: ${esc(movement.order_reference)}${movement.client_name ? ` · ${esc(movement.client_name)}` : ''}` : '';
      const supplier = movement.supplier_name ? `Supplier: ${esc(movement.supplier_name)}` : '';
      const batch = movement.batch_number ? `Batch: ${esc(movement.batch_number)}` : (movement.batch_allocations?.length ? `Batch ${movement.batch_allocations.length} lot` : '');
      const context = [detail, order, supplier, batch].filter(Boolean).join(' · ');
      const movementValue = Number(movement.quantity || 0) * Number(movement.unit_cost || 0);
      return `<tr>
        <td><div class="inventory-item-name"><strong>${inventoryDateTime(movement.created_at)}</strong><small>${esc(movement.movement_code)}</small></div></td>
        <td><div class="inventory-item-name"><strong>${esc(movement.item_name || '-')}</strong><small>${esc(movement.sku || '')}</small></div></td>
        <td><span class="inventory-movement-badge ${inventoryMovementTone(movement.movement_type)}">${esc(movement.movement_label || inventoryMovementLabel(movement.movement_type))}</span></td>
        <td><strong>${inventoryQuantity(movement.quantity)} ${esc(movement.unit || '')}</strong></td>
        <td><div class="inventory-stock-value"><strong>${formatRupiah(movementValue)}</strong><small>${formatRupiah(movement.unit_cost)} / ${esc(movement.unit || 'unit')}</small></div></td>
        <td class="master-cell">${context || '-'}${movement.invoice_number ? `<small>Invoice: ${esc(movement.invoice_number)}</small>` : ''}</td>
        <td class="master-cell">${esc(movement.notes || '-')}</td>
      </tr>`;
    }).join('');
    $('#inventoryMovementsEmpty').hidden = inventoryMovements.length > 0;
    $('#inventoryMovementCount').textContent = `${inventoryMovementMeta.total || 0} mutasi`;
    $('#inventoryMovementTableCount').textContent = `Menampilkan ${inventoryMovements.length} dari ${inventoryMovementMeta.total || 0} mutasi`;
    $('#inventoryMovementsPageLabel').textContent = `${inventoryMovementMeta.current_page || 1} / ${inventoryMovementMeta.last_page || 1}`;
    $('#inventoryMovementsPrev').disabled = (inventoryMovementMeta.current_page || 1) <= 1;
    $('#inventoryMovementsNext').disabled = (inventoryMovementMeta.current_page || 1) >= (inventoryMovementMeta.last_page || 1);
  }
  async function refreshInventoryMovements() {
    const params = new URLSearchParams({
      search: $('#inventoryMovementSearch').value.trim(),
      type: $('#inventoryMovementTypeFilter').value || 'all',
      page: String(inventoryMovementPage),
    });
    const response = await apiRequest(`/inventory/movements?${params.toString()}`);
    inventoryMovements = response.data || [];
    inventoryMovementMeta = response.meta || { current_page: 1, last_page: 1, total: inventoryMovements.length };
    renderInventoryMovements();
  }
  function renderInventoryTechnicianStocks() {
    $('#inventoryTechStockRows').innerHTML = inventoryTechnicianStocks.map((stock) => {
      const batches = (stock.batches || []).map((batch) => `<small class="inventory-secondary inventory-tech-batch"><strong>${esc(batch.batch_number || 'Tanpa nomor batch')}</strong> · ${inventoryQuantity(batch.quantity)} ${esc(stock.unit || '')} · ${batch.expires_at ? `Exp. ${formatDateLong(batch.expires_at)}` : 'Tanpa kedaluwarsa'}</small>`).join('');
      return `<tr><td><div class="inventory-item-name"><strong>${esc(stock.technician_name || 'Teknisi tidak tersedia')}</strong><small>${esc(stock.technician_code || '')}</small></div></td>
        <td>${esc(stock.item_name || '-')}</td><td>${esc(stock.sku || '-')}</td><td><strong>${inventoryQuantity(stock.quantity)} ${esc(stock.unit || '')}</strong></td>
        <td><div class="inventory-tech-batches">${batches || '<small class="inventory-secondary">Detail batch tidak tersedia</small>'}</div></td>
        <td><span class="inventory-status-badge technician-status-chip ${stock.technician_status === 'assigned' ? 'technician-state-assigned' : stock.technician_status === 'off_duty' ? 'technician-state-off-duty' : 'technician-state-available'}">${stock.technician_status === 'assigned' ? 'Sedang bertugas' : stock.technician_status === 'off_duty' ? 'Di luar tugas' : 'Tersedia'}</span></td>
        <td>${inventoryDateTime(stock.updated_at)}</td></tr>`;
    }).join('');
    $('#inventoryTechStocksEmpty').hidden = inventoryTechnicianStocks.length > 0;
    $('#inventoryTechStockCount').textContent = `${inventoryTechStockMeta.total || 0} saldo`;
    $('#inventoryTechStockTableCount').textContent = `Menampilkan ${inventoryTechnicianStocks.length} dari ${inventoryTechStockMeta.total || 0} saldo`;
    $('#inventoryTechStocksPageLabel').textContent = `${inventoryTechStockMeta.current_page || 1} / ${inventoryTechStockMeta.last_page || 1}`;
    $('#inventoryTechStocksPrev').disabled = (inventoryTechStockMeta.current_page || 1) <= 1;
    $('#inventoryTechStocksNext').disabled = (inventoryTechStockMeta.current_page || 1) >= (inventoryTechStockMeta.last_page || 1);
  }
  async function refreshInventoryTechnicianStocks() {
    const params = new URLSearchParams({ search: $('#inventoryTechStockSearch').value.trim(), page: String(inventoryTechStockPage) });
    const response = await apiRequest(`/inventory/technician-stocks?${params.toString()}`);
    inventoryTechnicianStocks = response.data || [];
    inventoryTechStockMeta = response.meta || { current_page: 1, last_page: 1, total: inventoryTechnicianStocks.length };
    renderInventoryTechnicianStocks();
  }
  function renderInventoryMaintenance() {
    $('#inventoryMaintenanceRows').innerHTML = inventoryMaintenanceRows.map((row) => `
      <tr><td>${formatDateLong(row.scheduled_date)}</td>
      <td><div class="inventory-item-name"><strong>${esc(row.item_name || '-')}</strong><small>${esc(row.sku || '')}</small></div></td>
      <td>${esc(row.maintenance_label || row.maintenance_type)}</td><td>${esc(row.technician_name || 'Belum ditentukan')}</td><td>${formatRupiah(row.cost)}</td>
      <td><span class="inventory-status-badge ${esc(row.status)}">${esc(inventoryStatusLabel(row.status))}</span></td>
      <td><select class="inventory-maintenance-status" data-maintenance-status="${esc(row.id)}" aria-label="Ubah status pemeliharaan ${esc(row.item_name || '')}" ${row.status === 'completed' ? 'disabled' : ''}><option value="planned" ${row.status === 'planned' ? 'selected' : ''}>Terjadwal</option><option value="completed" ${row.status === 'completed' ? 'selected' : ''}>Selesai</option><option value="cancelled" ${row.status === 'cancelled' ? 'selected' : ''}>Dibatalkan</option></select></td></tr>`).join('');
    $('#inventoryMaintenanceEmpty').hidden = inventoryMaintenanceRows.length > 0;
    $('#inventoryMaintenanceCount').textContent = `${inventoryMaintenanceMeta.total || 0} jadwal`;
    $('#inventoryMaintenanceTableCount').textContent = `Menampilkan ${inventoryMaintenanceRows.length} dari ${inventoryMaintenanceMeta.total || 0} jadwal`;
    $('#inventoryMaintenancePageLabel').textContent = `${inventoryMaintenanceMeta.current_page || 1} / ${inventoryMaintenanceMeta.last_page || 1}`;
    $('#inventoryMaintenancePrev').disabled = (inventoryMaintenanceMeta.current_page || 1) <= 1;
    $('#inventoryMaintenanceNext').disabled = (inventoryMaintenanceMeta.current_page || 1) >= (inventoryMaintenanceMeta.last_page || 1);
  }
  async function refreshInventoryMaintenance() {
    const params = new URLSearchParams({ status: $('#inventoryMaintenanceStatusFilter').value || 'all', page: String(inventoryMaintenancePage) });
    const response = await apiRequest(`/inventory/maintenance?${params.toString()}`);
    inventoryMaintenanceRows = response.data || [];
    inventoryMaintenanceMeta = response.meta || { current_page: 1, last_page: 1, total: inventoryMaintenanceRows.length };
    renderInventoryMaintenance();
  }
  async function setInventoryTab(tab) {
    inventoryTab = ['items', 'movements', 'technicians', 'maintenance'].includes(tab) ? tab : 'items';
    $$('[data-inventory-tab]').forEach((button) => {
      const active = button.dataset.inventoryTab === inventoryTab;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', String(active));
      button.tabIndex = active ? 0 : -1;
    });
    $$('[id^="inventoryPanel"]').forEach((panel) => {
      const active = panel.id === `inventoryPanel${inventoryTab.charAt(0).toUpperCase()}${inventoryTab.slice(1)}`;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
    if (inventoryTab === 'items') await refreshInventoryItems();
    if (inventoryTab === 'movements') await refreshInventoryMovements();
    if (inventoryTab === 'technicians') await refreshInventoryTechnicianStocks();
    if (inventoryTab === 'maintenance') await refreshInventoryMaintenance();
  }
  async function refreshInventory() {
    if (!apiMode) { showWarning('Inventaris memerlukan koneksi database server.'); return; }
    await Promise.all([refreshInventorySummary(), refreshInventoryOptions()]);
    await setInventoryTab(inventoryTab);
  }
  function stockCatalogCondition(item) {
    const warehouse = Number(item.stock_on_hand || 0);
    const minimum = Number(item.minimum_stock || 0);
    const technician = Number(item.technician_stock || 0);
    if (minimum > 0 && warehouse <= minimum) {
      return { label: warehouse <= 0 ? 'Stok kosong' : 'Stok menipis', tone: 'low' };
    }
    if (warehouse > 0) return { label: 'Tersedia', tone: 'good' };
    if (technician > 0) return { label: 'Di teknisi', tone: 'field' };
    return { label: 'Stok kosong', tone: 'empty' };
  }
  function renderStockCatalog() {
    $('#stockCatalogRows').innerHTML = stockCatalogItems.map((item) => {
      const condition = stockCatalogCondition(item);
      const kind = item.item_type === 'asset' ? 'Peralatan / aset' : 'Bahan habis pakai';
      return `<tr>
        <td><div class="inventory-item-name"><strong>${esc(item.name || '-')}</strong><small>${esc(item.sku || '-')}</small>${item.brand ? `<small>${esc(item.brand)}</small>` : ''}</div></td>
        <td>${esc(item.category || '-')}</td>
        <td><span class="inventory-type-tag ${item.item_type === 'asset' ? 'asset' : 'consumable'}">${esc(kind)}</span>${item.track_expiry ? '<small class="stock-catalog-secondary">Pelacakan kedaluwarsa</small>' : ''}</td>
        <td><div class="inventory-stock-value"><strong>${inventoryQuantity(item.stock_on_hand)} ${esc(item.unit || '')}</strong><small>Di gudang</small></div></td>
        <td><div class="inventory-stock-value"><strong>${inventoryQuantity(item.technician_stock)} ${esc(item.unit || '')}</strong><small>Terdistribusi</small></div></td>
        <td><div class="inventory-stock-value"><strong>${inventoryQuantity(item.minimum_stock)} ${esc(item.unit || '')}</strong><small>Batas minimum</small></div></td>
        <td><span class="stock-catalog-condition is-${condition.tone}">${esc(condition.label)}</span></td>
        <td><span class="inventory-status-badge ${item.is_active ? 'completed' : 'cancelled'}">${item.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
      </tr>`;
    }).join('');
    $('#stockCatalogEmpty').hidden = stockCatalogItems.length > 0;
    $('#stockCatalogCount').textContent = `${stockCatalogMeta.total || 0} barang`;
    $('#stockCatalogTableCount').textContent = `Menampilkan ${stockCatalogItems.length} dari ${stockCatalogMeta.total || 0} barang`;
    $('#stockCatalogPageLabel').textContent = `${stockCatalogMeta.current_page || 1} / ${stockCatalogMeta.last_page || 1}`;
    $('#stockCatalogPrev').disabled = (stockCatalogMeta.current_page || 1) <= 1;
    $('#stockCatalogNext').disabled = (stockCatalogMeta.current_page || 1) >= (stockCatalogMeta.last_page || 1);
  }
  async function refreshStockCatalogSummary() {
    const response = await apiRequest('/inventory/summary');
    const summary = response.summary || {};
    $('#stockCatalogStatItems').textContent = inventoryQuantity(summary.items || 0);
    $('#stockCatalogStatStocked').textContent = inventoryQuantity(summary.stocked_items || 0);
    $('#stockCatalogStatLow').textContent = inventoryQuantity(summary.low_stock || 0);
    $('#stockCatalogStatExpiry').textContent = inventoryQuantity(summary.expiring_batches || 0);
  }
  async function refreshStockCatalog() {
    if (!apiMode) throw new Error('Data stok hanya tersedia saat terhubung ke database server.');
    const params = new URLSearchParams({
      search: $('#stockCatalogSearch').value.trim(),
      type: $('#stockCatalogTypeFilter').value || 'all',
      status: $('#stockCatalogStatusFilter').value || 'all',
      page: String(stockCatalogPage),
    });
    const response = await apiRequest(`/inventory/items?${params.toString()}`);
    stockCatalogItems = response.data || [];
    stockCatalogMeta = response.meta || { current_page: 1, last_page: 1, total: stockCatalogItems.length };
    renderStockCatalog();
  }
  async function refreshStockCatalogPage() {
    await Promise.all([refreshStockCatalogSummary(), refreshStockCatalog()]);
  }

  function addInventoryItem() {
    inventoryItemEditingId = null;
    const form = $('#inventoryItemForm');
    form.reset();
    form.elements.minimum_stock.value = '0';
    form.elements.track_expiry.checked = false;
    form.elements.is_active.value = '1';
    form.elements.item_type.value = 'consumable';
    $('#inventoryItemModalTitle').textContent = 'Tambah barang';
    showModal('inventoryItemModal');
  }
  function editInventoryItem(id) {
    const item = inventoryItems.find((entry) => String(entry.id) === String(id));
    if (!item) return;
    inventoryItemEditingId = item.id;
    const form = $('#inventoryItemForm');
    Object.entries({
      sku: item.sku, name: item.name, category: item.category, item_type: item.item_type,
      brand: item.brand, specification: item.specification, unit: item.unit,
      minimum_stock: item.minimum_stock, is_active: item.is_active ? '1' : '0'
    }).forEach(([key, value]) => { form.elements.namedItem(key).value = value ?? ''; });
    form.elements.track_expiry.checked = Boolean(item.track_expiry);
    $('#inventoryItemModalTitle').textContent = `Edit barang · ${item.sku}`;
    showModal('inventoryItemModal');
  }
  async function saveInventoryItem(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true; submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      values.track_expiry = form.elements.track_expiry.checked ? 1 : 0;
      values.is_active = String(values.is_active) === '1' ? 1 : 0;
      const path = `/inventory/items${inventoryItemEditingId ? `/${encodeURIComponent(inventoryItemEditingId)}` : ''}`;
      await apiRequest(path, { method: inventoryItemEditingId ? 'PUT' : 'POST', body: values });
      inventoryItemPage = 1;
      closeModal('inventoryItemModal');
      inventoryItemEditingId = null;
      await Promise.all([refreshInventoryItems(), refreshInventorySummary(), refreshInventoryOptions()]);
      toast('Master barang berhasil disimpan.', 'success');
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }
  async function deleteInventoryItem(id) {
    const item = inventoryItems.find((entry) => String(entry.id) === String(id));
    if (!item) return;
    if (!(await confirmDialog({
      title: 'Hapus master barang?',
      message: `${item.name} (${item.sku}) hanya dapat dihapus jika belum memiliki saldo, mutasi, atau jadwal pemeliharaan. Barang yang sudah memiliki histori harus dinonaktifkan melalui form edit.`,
      confirmText: 'Hapus barang', danger: true
    }))) return;
    try {
      await apiRequest(`/inventory/items/${encodeURIComponent(id)}`, { method: 'DELETE' });
      await Promise.all([refreshInventoryItems(), refreshInventorySummary(), refreshInventoryOptions()]);
      toast('Barang berhasil dihapus.', 'success');
    } catch (error) { showError(error.message); }
  }
  async function addInventoryMovement() {
    const form = $('#inventoryMovementForm');
    form.reset();
    await refreshInventoryOptions();
    form.elements.movement_type.value = 'stock_in';
    form.elements.quantity.value = '';
    form.elements.unit_cost.value = '0';
    syncInventoryMovementFields();
    $('#inventoryMovementModalTitle').textContent = 'Catat mutasi stok';
    showModal('inventoryMovementModal');
  }
  function syncInventoryMovementFields() {
    const form = $('#inventoryMovementForm');
    if (!form) return;
    const type = form.elements.movement_type.value || 'stock_in';
    const inbound = ['stock_in', 'adjustment_in'].includes(type);
    const technicianRequired = ['distribution', 'usage', 'return'].includes(type);
    const hasOrder = type === 'usage';
    const hasCost = ['stock_in', 'adjustment_in'].includes(type);
    const hasSupplier = ['stock_in', 'adjustment_in'].includes(type);
    const needsNote = ['stock_out', 'adjustment_in', 'adjustment_out', 'write_off'].includes(type);
    $('#inventoryInboundFields').hidden = !inbound;
    $('#inventoryBatchNumberField').hidden = false;
    $('#inventorySupplierFields').hidden = !hasSupplier;
    $('#inventoryTechnicianFields').hidden = !technicianRequired;
    $('#inventoryOrderFields').hidden = !hasOrder;
    $('#inventoryMovementCostField').hidden = !hasCost;
    form.elements.technician_id.required = technicianRequired;
    form.elements.technician_id.disabled = !technicianRequired;
    form.elements.enquiry_id.required = hasOrder;
    form.elements.enquiry_id.disabled = !hasOrder;
    form.elements.unit_cost.disabled = !hasCost;
    form.elements.batch_number.disabled = !inbound;
    form.elements.expires_at.disabled = !inbound;
    form.elements.supplier_name.disabled = !hasSupplier;
    form.elements.invoice_number.disabled = !hasSupplier;
    form.elements.notes.required = needsNote;
    const item = inventoryOptions.items.find((entry) => String(entry.id) === String(form.elements.item_id.value));
    if (item) {
      const warehouseHint = `Stok gudang saat ini: ${inventoryQuantity(item.stock_on_hand)} ${item.unit}.`;
      $('#inventoryMovementStockHint').textContent = type === 'usage'
        ? 'Pemakaian akan mengurangi saldo barang pada teknisi yang dipilih, bukan stok gudang langsung.'
        : type === 'return'
          ? 'Pengembalian mengambil batch yang sama dari saldo teknisi dan mengembalikannya ke gudang beserta tanggal kedaluwarsa serta harga asal.'
          : type === 'write_off'
            ? `${warehouseHint} Pemusnahan dapat mengambil batch kedaluwarsa.`
            : `${warehouseHint} Batch kedaluwarsa ditahan dari pengeluaran normal.`;
      const expiry = form.elements.expires_at;
      expiry.required = Boolean(item.track_expiry && inbound);
      $('#inventoryExpiryHint').textContent = item.track_expiry ? 'Wajib diisi untuk barang ini. Tanggal akan ikut terlacak saat didistribusikan ke teknisi.' : 'Isi jika barang memiliki tanggal kedaluwarsa.';
    } else {
      $('#inventoryMovementStockHint').textContent = 'Pilih barang untuk melihat saldo gudang.';
      form.elements.expires_at.required = false;
      $('#inventoryExpiryHint').textContent = 'Isi jika barang memiliki tanggal kedaluwarsa.';
    }
  }
  async function saveInventoryMovement(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true; submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      await apiRequest('/inventory/movements', { method: 'POST', body: values });
      closeModal('inventoryMovementModal');
      await Promise.all([refreshInventorySummary(), refreshInventoryItems(), refreshInventoryOptions()]);
      if (inventoryTab === 'movements') { inventoryMovementPage = 1; await refreshInventoryMovements(); }
      if (inventoryTab === 'technicians') { inventoryTechStockPage = 1; await refreshInventoryTechnicianStocks(); }
      toast('Mutasi stok berhasil dicatat dan saldo diperbarui.', 'success');
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }
  async function addInventoryMaintenance() {
    const form = $('#inventoryMaintenanceForm');
    form.reset();
    await refreshInventoryOptions();
    form.elements.scheduled_date.value = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
    form.elements.cost.value = '0';
    showModal('inventoryMaintenanceModal');
  }
  async function saveInventoryMaintenance(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true; submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      await apiRequest('/inventory/maintenance', { method: 'POST', body: values });
      closeModal('inventoryMaintenanceModal');
      inventoryMaintenancePage = 1;
      await Promise.all([refreshInventoryMaintenance(), refreshInventorySummary()]);
      toast('Jadwal pemeliharaan berhasil dibuat.', 'success');
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }
  async function updateInventoryMaintenance(id, status) {
    const row = inventoryMaintenanceRows.find((item) => String(item.id) === String(id));
    if (!row) return;
    try {
      await apiRequest(`/inventory/maintenance/${encodeURIComponent(id)}/status`, { method: 'PATCH', body: { status } });
      await Promise.all([refreshInventoryMaintenance(), refreshInventorySummary()]);
      toast(`Status pemeliharaan ${inventoryStatusLabel(status).toLowerCase()}.`, 'success');
    } catch (error) {
      showError(error.message);
      await refreshInventoryMaintenance();
    }
  }


  function clone(value) { return JSON.parse(JSON.stringify(value)); }
  function loadLocal(key, fallback) {
    try { const value = JSON.parse(localStorage.getItem(key)); return value ?? clone(fallback); }
    catch { return clone(fallback); }
  }
  function saveLocal(key, value) {
    try { localStorage.setItem(key, JSON.stringify(value)); return true; }
    catch { showError('Browser tidak dapat menyimpan perubahan.'); return false; }
  }
  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  }
  function normalizeBookingStatus(status) {
    // Status legacy 'contacted' dilebur ke Follow-up agar UI hanya memiliki empat status.
    const value = status || 'new';
    return value === 'contacted' ? 'followup' : value;
  }
  function statusLabel(status) {
    return ({ new: 'Baru', followup: 'Follow-up', converted: 'Deal Order', lost: 'Tidak Deal' })[normalizeBookingStatus(status)] || status;
  }
  function statusClass(status) {
    return ({ new: 'status-pending', followup: 'status-followup', converted: 'status-converted', lost: 'status-lost' })[normalizeBookingStatus(status)] || 'status-pending';
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
  // Format angka Indonesia: titik sebagai pemisah ribuan, koma sebagai desimal.
  function formatThousandsInput(raw, maxDecimals) {
    const text = String(raw ?? '');
    const hasComma = maxDecimals > 0 && text.includes(',');
    const [intPart, ...rest] = text.split(',');
    const digits = intPart.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    if (!hasComma) return grouped;
    const decimals = rest.join('').replace(/\D/g, '').slice(0, maxDecimals);
    return `${grouped || '0'},${decimals}`;
  }
  function parseThousandsInput(value) {
    return String(value ?? '').replace(/\./g, '').replace(',', '.').trim();
  }
  function bindThousandsInputs(form) {
    form.querySelectorAll('input[data-format="thousands"]').forEach((input) => {
      input.addEventListener('input', () => {
        const maxDecimals = Number(input.dataset.maxDecimals || 0);
        const caret = input.selectionStart ?? input.value.length;
        const digitsBeforeCaret = input.value.slice(0, caret).replace(/[^\d,]/g, '').length;
        input.value = formatThousandsInput(input.value, maxDecimals);
        let pos = 0;
        let seen = 0;
        while (pos < input.value.length && seen < digitsBeforeCaret) {
          if (/[\d,]/.test(input.value[pos])) seen += 1;
          pos += 1;
        }
        try { input.setSelectionRange(pos, pos); } catch (error) { /* abaikan */ }
      });
    });
  }
  function formatRupiah(value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
  }
  function formatDateLong(value) {
    if (!value) return '-';
    const date = new Date(`${value}T00:00:00+07:00`);
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'Asia/Jakarta' }).format(date);
  }
  function greeting() {
    const hour = Number(new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', hourCycle: 'h23' }).format(new Date()));
    return hour < 11 ? 'pagi' : hour < 15 ? 'siang' : hour < 18 ? 'sore' : 'malam';
  }
  function buildWhatsAppMessage(booking) {
    const clean = (value, fallback = '-') => {
      const text = String(value ?? '').replace(/[\r\n\t]+/g, ' ').replace(/[\*_~`]/g, '').trim();
      return text || fallback;
    };
    const company = clean(booking.company, '');
    const name = clean(booking.name);
    const displayName = company || name;
    const numericArea = Number(String(booking.area ?? '').replace(',', '.'));
    const area = Number.isFinite(numericArea)
      ? new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numericArea)
      : '-';
    const lines = [
      `Halo Bapak/Ibu *${displayName}* 👋`,
      '',
      'Terima kasih telah menghubungi *CV Jaya Abadi Solusi (JASPEST)*.',
      '',
      'Kami telah menerima permintaan layanan Anda melalui formulir website JASPEST. Berikut ringkasan data yang Anda kirimkan:',
      '',
      '*DETAIL PENGAJUAN*',
      `Nomor Pengajuan: *${clean(booking.reference || booking.id)}*`,
      `Nama: ${name}`,
    ];
    if (company) lines.push(`Nama Perusahaan: ${company}`);
    lines.push(
      `Nomor WhatsApp: ${clean(formatPhone(booking.whatsapp))}`,
      `Layanan: ${clean(booking.need)}`,
      `Jenis Lokasi: ${booking.property === 'bisnis' ? 'Bisnis' : 'Rumah'}`,
      `Perkiraan Luasan: ${area} m²`,
      `Alamat Lokasi: ${clean(booking.address)}`,
      `Keterangan: ${clean(booking.description)}`,
      '',
      'Tim kami akan meninjau kebutuhan tersebut dan segera menghubungi Anda untuk tindak lanjut.',
      'Apabila ada informasi tambahan, silakan membalas pesan ini.',
      '',
      'Terima kasih atas kepercayaan Anda.',
      '',
      '*CV JAYA ABADI SOLUSI*',
      '*JASPEST — Professional Pest Control*'
    );
    return lines.join('\n');
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
    let response;
    try {
      response = await fetch(`${apiBase}${path}`, {
        ...options, body, headers, credentials: 'same-origin', redirect: 'follow'
      });
    } catch {
      throw new Error('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    }
    const contentType = response.headers.get('content-type') || '';
    let payload = null;
    if (contentType.includes('application/json')) {
      try { payload = await response.json(); } catch { payload = null; }
    }
    if (!response.ok) {
      if (response.status === 401 || response.status === 419) {
        if (config.loginUrl) setTimeout(() => { window.location.href = config.loginUrl; }, 1500);
        throw new Error('Sesi login berakhir. Anda akan diarahkan ke halaman masuk.');
      }
      if (response.status === 403) throw new Error('Akun Anda tidak memiliki izin untuk tindakan ini.');
      if (response.status === 413) throw new Error('Ukuran gambar melebihi batas server. Pilih gambar yang lebih kecil.');
      if (response.status === 422) {
        const messages = Object.values(payload?.errors || {}).flat();
        const first = String(messages[0] || payload?.message || '');
        if (/failed to upload/i.test(first)) throw new Error('Gambar gagal diunggah. Ukuran file melebihi batas server; coba gambar yang lebih kecil.');
        throw new Error(localizeMessage(first, 'Periksa kembali isian Anda.'));
      }
      if (response.status === 404) throw new Error('Data tidak ditemukan atau sudah dihapus.');
      if (response.status === 429) throw new Error('Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
      if (response.status >= 500) throw new Error(localizeMessage(payload?.message, 'Terjadi kesalahan pada server. Silakan coba kembali.'));
      throw new Error(localizeMessage(payload?.message, `Permintaan gagal (${response.status}).`));
    }
    if (!payload) throw new Error('Respons server tidak sesuai format JSON. Periksa rute dan konfigurasi Laravel.');
    return payload;
  }

  function updateStats(stats) {
    const values = stats || {
      total: bookings.length,
      new: bookings.filter((item) => normalizeBookingStatus(item.status) === 'new').length,
      followup: bookings.filter((item) => normalizeBookingStatus(item.status) === 'followup').length,
      converted: bookings.filter((item) => normalizeBookingStatus(item.status) === 'converted').length,
      lost: bookings.filter((item) => normalizeBookingStatus(item.status) === 'lost').length
    };
    knownTotal = Number(values.total ?? bookings.length);
    const newCount = Number(values.new ?? 0);
    const followupCount = Number(values.followup ?? 0);
    const actionableCount = newCount + followupCount;
    const navBadge = $('#bookingNavCount');
    navBadge.textContent = actionableCount;
    navBadge.hidden = actionableCount === 0;
    navBadge.title = `${newCount} booking baru, ${followupCount} booking follow-up`;
    document.title = `${actionableCount > 0 ? `(${actionableCount}) ` : ''}Admin Dashboard | JASPEST`;
    const totalHint = $('#statTotalHint');
    if (totalHint) {
      const totalNow = Number(values.total ?? 0);
      totalHint.textContent = totalNow > 0 && values.converted !== undefined
        ? `${Math.round((Number(values.converted) / totalNow) * 100)}% dari booking berhasil menjadi Deal Order`
        : 'Sejak website aktif';
    }
    $('#statNew').textContent = newCount;
    $('#statTotal').textContent = values.total ?? 0;
    $('#statConverted').textContent = values.converted ?? 0;
    $('#statFollow').textContent = followupCount;
    $('#bookingTotal').textContent = values.total ?? 0;
    $('#bookingNew').textContent = newCount;
    $('#bookingFollow').textContent = followupCount;
    $('#bookingConverted').textContent = values.converted ?? 0;
    $('#bookingLost').textContent = values.lost ?? 0;
    $('#attentionFollow').textContent = `${followupCount} klien follow-up`;
    $('#attentionConverted').textContent = `${values.converted ?? 0} Deal Order`;
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
        <td><div class="status-cell"><span class="status-pill ${statusClass(booking.status)}">${statusLabel(booking.status)}</span></div></td>
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
      (status === 'all' || normalizeBookingStatus(booking.status) === status) &&
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
        <td><div class="status-cell"><span class="status-pill ${statusClass(booking.status)}">${statusLabel(booking.status)}</span></div></td>
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

  function renderAttentionHint() {
    const hint = $('#attentionFollowHint');
    if (!hint) return;
    hint.textContent = 'Pengajuan yang sudah ditindaklanjuti oleh tim.';
  }
  function eventLabel(event) {
    return ({ status_changed: 'Status diperbarui', whatsapp_opened: 'Tindak lanjut dimulai', automatic_whatsapp_followup: 'Tindak lanjut WhatsApp', note_added: 'Catatan admin', order_confirmed: 'Order dikonfirmasi', manual_order_created: 'Deal Order ditambahkan', client_linked: 'Klien ditautkan' })[event.event] || 'Pengajuan masuk';
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
    if (note.length < 2) { showWarning('Tulis catatan terlebih dahulu.'); input.focus(); return; }
    if (!apiMode) {
      currentBooking.events = [...(currentBooking.events || []), { event: 'note_added', note, created_at: new Date().toISOString() }];
      input.value = '';
      $('#detailEvents').innerHTML = renderEventList(currentBooking.events);
      toast('Catatan ditambahkan (mode preview).', 'success');
      return;
    }
    button.disabled = true;
    try {
      const created = await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/notes`, { method: 'POST', body: { note } });
      currentBooking.events = [...(currentBooking.events || []), created];
      input.value = '';
      $('#detailEvents').innerHTML = renderEventList(currentBooking.events);
      toast('Catatan disimpan.', 'success');
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
        announceNewBooking(fresh === 1 ? '1 pengajuan baru masuk.' : `${fresh} pengajuan baru masuk.`);
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
  let photoLightboxTrigger = null;
  let photoLightboxPreviousOverflow = '';

  function ensurePhotoLightbox() {
    if ($('#photoLightbox')) return;
    const lightbox = document.createElement('div');
    lightbox.id = 'photoLightbox';
    lightbox.className = 'photo-lightbox';
    lightbox.hidden = true;
    lightbox.innerHTML = `
      <section class="photo-lightbox__dialog" role="dialog" aria-modal="true" aria-labelledby="photoLightboxCaption">
        <button type="button" class="photo-lightbox__close" data-photo-lightbox-close aria-label="Tutup foto" title="Tutup (Esc)">×</button>
        <figure class="photo-lightbox__figure">
          <img class="photo-lightbox__image" id="photoLightboxImage" alt="" draggable="false">
          <figcaption class="photo-lightbox__caption" id="photoLightboxCaption"></figcaption>
        </figure>
      </section>`;
    document.body.append(lightbox);
    lightbox.addEventListener('click', (event) => {
      if (event.target === lightbox || event.target.closest('[data-photo-lightbox-close]')) closePhotoLightbox();
    });
    $('#photoLightboxImage', lightbox).addEventListener('error', () => {
      showError('Foto tidak dapat dimuat. Periksa koneksi atau akses file, lalu coba kembali.');
      closePhotoLightbox();
    });
  }

  function openPhotoLightbox(url, name, trigger) {
    ensurePhotoLightbox();
    const lightbox = $('#photoLightbox');
    const image = $('#photoLightboxImage');
    photoLightboxTrigger = trigger || null;
    photoLightboxPreviousOverflow = document.body.style.overflow;
    image.alt = name || 'Foto klien';
    image.src = url;
    $('#photoLightboxCaption').textContent = name || 'Foto klien';
    lightbox.hidden = false;
    document.body.style.overflow = 'hidden';
    $('[data-photo-lightbox-close]', lightbox).focus();
  }

  function closePhotoLightbox() {
    const lightbox = $('#photoLightbox');
    if (!lightbox || lightbox.hidden) return;
    lightbox.hidden = true;
    const image = $('#photoLightboxImage', lightbox);
    image.removeAttribute('src');
    image.alt = '';
    document.body.style.overflow = photoLightboxPreviousOverflow;
    if (photoLightboxTrigger?.isConnected) photoLightboxTrigger.focus();
    photoLightboxTrigger = null;
  }

  function renderPhoto(photo, index) {
    if (photo && typeof photo === 'object' && photo.url) {
      const name = photo.name || `Foto temuan ${index + 1}`;
      const url = esc(photo.url);
      const safeName = esc(name);
      return `<button type="button" class="photo-link" data-photo-url="${url}" data-photo-name="${safeName}" aria-label="Perbesar foto: ${safeName}" title="Klik untuk melihat foto lebih besar"><img src="${url}" alt="${safeName}" loading="lazy" decoding="async" referrerpolicy="same-origin"><span>${safeName}<small>Lihat foto ↗</small></span></button>`;
    }
    return `<div class="photo-placeholder">▧<br>${esc(typeof photo === 'string' ? photo : `Foto ${index + 1}`)}</div>`;
  }
  function renderDetailStatus(booking) {
    if (!booking) return;
    const status = normalizeBookingStatus(booking.status);
    $('#detailStatus').innerHTML = `<span class="status-pill ${statusClass(status)}">${statusLabel(status)}</span>`;
    $$('#detailStatusActions [data-booking-status]').forEach((button) => {
      const active = button.dataset.bookingStatus === status;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
      button.disabled = status === 'converted' && !active;
    });
    const followupButton = $('#detailFollowupWhatsapp');
    if (followupButton) {
      const followupActive = status === 'followup';
      followupButton.classList.toggle('is-active', followupActive);
      followupButton.setAttribute('aria-pressed', String(followupActive));
      followupButton.disabled = !booking.consent || ['converted', 'lost'].includes(status);
    }
  }

  function renderWhatsappNotificationPanel(notification, section, container, retry) {
    if (!notification) {
      section.hidden = true;
      retry.hidden = true;
      container.innerHTML = '';
      return;
    }

    const states = {
      queued: ['Menunggu pengiriman', 'notification-neutral'],
      sending: ['Sedang diproses', 'notification-info'],
      accepted: ['Dalam antrean pengiriman', 'notification-info'],
      sent: ['Terkirim', 'notification-success'],
      failed: ['Belum berhasil dikirim', 'notification-danger'],
      not_configured: ['Pengiriman belum tersedia', 'notification-warning'],
      skipped_consent: ['Persetujuan pelanggan belum tercatat', 'notification-neutral'],
      skipped_invalid_number: ['Nomor pelanggan perlu diperiksa', 'notification-warning'],
      unknown: ['Status pengiriman belum dapat dipastikan', 'notification-warning']
    };
    const [label, tone] = states[notification.status] || ['Status tidak tersedia', 'notification-neutral'];
    const details = [
      `<div class="whatsapp-notification-status"><span class="notification-status-dot ${tone}"></span><strong>${esc(label)}</strong></div>`,
      notification.recipient ? `<small>Tujuan: +${esc(notification.recipient)}</small>` : '',
      notification.accepted_at ? `<small>Diterima untuk diproses: ${esc(new Date(notification.accepted_at).toLocaleString('id-ID'))}</small>` : '',
      notification.sent_at ? `<small>Waktu terkirim: ${esc(new Date(notification.sent_at).toLocaleString('id-ID'))}</small>` : '',
      notification.last_error ? `<p class="whatsapp-notification-error">${esc(notification.last_error)}</p>` : '',
      notification.status === 'unknown' ? '<p class="whatsapp-notification-help">Periksa riwayat pesan sebelum mencoba kembali agar pelanggan tidak menerima pesan ganda.</p>' : ''
    ].filter(Boolean);
    container.innerHTML = details.join('');
    section.hidden = false;
    retry.hidden = notification.retryable !== true;
    retry.disabled = false;
  }

  function renderWhatsappNotification(notification) {
    renderWhatsappNotificationPanel(
      notification,
      $('#detailWhatsappNotificationSection'),
      $('#detailWhatsappNotification'),
      $('#retryDealWhatsapp')
    );
  }


  async function retryDealWhatsappNotification() {
    if (!currentBooking || !apiMode) return;
    const button = $('#retryDealWhatsapp');
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span>Mengirim…</span>';
    try {
      const result = await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/whatsapp-notification/retry`, { method: 'POST' });
      button.innerHTML = original;
      currentBooking.whatsapp_notification = result.notification;
      renderWhatsappNotification(result.notification);
      if (result.notification.status === 'accepted' || result.notification.status === 'sent') {
        toast('Pesan konfirmasi berhasil diproses.', 'success');
      } else {
        showWarning(result.notification.last_error || 'Pesan konfirmasi belum berhasil diproses. Periksa detail dan coba kembali.');
      }
    } catch (error) {
      showError(error.message || 'Notifikasi gagal dikirim ulang.');
      button.disabled = false;
      button.innerHTML = original;
    }
  }


  async function openDetail(id) {
    try {
      let booking = bookings.find((item) => item.id === id || item.reference === id);
      if (apiMode) booking = await apiRequest(`/bookings/${encodeURIComponent(id)}`);
      if (!booking) return;
      currentBooking = booking;
      $('#detailName').textContent = booking.name;
      $('#detailRef').textContent = `${booking.id} · ${booking.date}`;
      renderDetailStatus(booking);
      const fields = [
        ['Nama lengkap', booking.name], ['Nomor WhatsApp', formatPhone(booking.whatsapp)],
        ['Alamat lokasi', booking.address, true], ['Perkiraan luasan', `${booking.area} m²`],
        ['Kebutuhan layanan', booking.need], ['Jenis lokasi', booking.property === 'bisnis' ? 'Bisnis' : 'Rumah'],
        ...(booking.company ? [['Nama perusahaan', booking.company]] : []),
        ['Keterangan', booking.description, true], ['Tanggal pengajuan', booking.date],
        ...(normalizeBookingStatus(booking.status) === 'converted' ? [
          ['Harga order', formatRupiah(booking.order_price)],
          ['Luasan final', booking.order_area ? `${booking.order_area} m²` : '-'],
          ['Tanggal pelaksanaan', formatDateLong(booking.scheduled_date)],
          ['Teknisi ditugaskan', booking.assigned_technician_name || '-']
        ] : [])
      ];
      $('#detailFields').innerHTML = fields.map(([label, value, full]) => `<div class="detail-field ${full ? 'full' : ''}"><small>${esc(label)}</small><strong>${esc(value || '-')}</strong></div>`).join('');
      $('#detailPhotos').innerHTML = booking.photos?.length ? booking.photos.map(renderPhoto).join('') : '<div class="photo-placeholder">Belum ada foto</div>';
      $('#detailConsent').textContent = booking.consent ? 'Klien menyetujui untuk dihubungi oleh tim JASPEST.' : 'Persetujuan kontak belum tercatat.';
      renderWhatsappNotification(booking.whatsapp_notification || null);
      $('#detailFollowupWhatsapp').disabled = !booking.consent || ['converted', 'lost'].includes(normalizeBookingStatus(booking.status));
      const events = booking.events || [];
      $('#detailEvents').innerHTML = renderEventList(events);
      $('#detailNoteInput').value = '';
      showModal('detailModal');
    } catch (error) { showError(error.message); }
  }
  async function openWhatsApp(id) {
    const booking = (currentBooking?.id === id || currentBooking?.reference === id ? currentBooking : null) || bookings.find((item) => item.id === id || item.reference === id) || recentBookings.find((item) => item.id === id || item.reference === id);
    if (!booking) { showError('Data booking tidak ditemukan.'); return; }
    if (!booking.consent) { showWarning('Klien belum menyetujui untuk dihubungi.'); return; }

    const phone = phoneForWhatsApp(booking.whatsapp);
    if (apiMode && !/^62\d{8,15}$/.test(phone)) { showWarning('Nomor WhatsApp tidak valid. Periksa data klien.'); return; }

    if (!apiMode) {
      // Preview mode mirrors the live status behavior without a server connection.
      if (!['converted', 'lost'].includes(normalizeBookingStatus(booking.status))) {
        booking.status = 'followup';
        booking.followedUpAt = new Date().toISOString();
      }
      if (currentBooking && currentBooking.id === id) {
        currentBooking = { ...currentBooking, ...booking };
        renderDetailStatus(currentBooking);
      }
      saveLocal('jaspest-admin-bookings', bookings);
      updateStats(); renderRecent(); renderBookings();
      $('#whatsappPreviewMessage').textContent = buildWhatsAppMessage(booking);
      showModal('whatsappPreviewModal');
      toast(['converted', 'lost'].includes(normalizeBookingStatus(booking.status)) ? 'Status akhir booking tetap dipertahankan.' : 'Pratinjau pesan Follow-up siap dibuka di WhatsApp.', 'info');
      return;
    }

    const url = `https://wa.me/${phone}?text=${encodeURIComponent(buildWhatsAppMessage(booking))}`;
    // Open synchronously from the click to avoid popup blockers.
    let whatsappWindow = null;
    try {
      whatsappWindow = window.open(url, '_blank');
      if (!whatsappWindow) {
        showWarning('Jendela percakapan tidak dapat dibuka. Izinkan pop-up untuk halaman admin ini, lalu coba kembali.');
        return;
      }
      whatsappWindow.opener = null;
    } catch (openError) {
      showWarning('Percakapan tidak dapat dibuka. Periksa pengaturan browser, lalu coba kembali.');
      return;
    }

    try {
      const updated = await apiRequest(`/bookings/${encodeURIComponent(id)}/whatsapp-opened`, { method: 'POST' });
      if (currentBooking && (currentBooking.id === id || currentBooking.reference === id)) {
        currentBooking = { ...currentBooking, ...updated };
        if (!$('#detailModal').hidden) {
          // Reload the detail resource so the newly recorded WhatsApp activity is visible immediately.
          try { currentBooking = { ...currentBooking, ...(await apiRequest(`/bookings/${encodeURIComponent(id)}`)) }; }
          catch { /* The status update has succeeded; keep the returned booking state if the detail refresh fails. */ }
          $('#detailEvents').innerHTML = renderEventList(currentBooking.events || []);
        }
        renderDetailStatus(currentBooking);
      }
      await Promise.all([refreshDashboard(), refreshBookings()]);
      toast(updated.status === 'converted'
        ? 'Percakapan dibuka. Status Deal Order tetap dipertahankan.'
        : updated.status === 'lost'
          ? 'Percakapan dibuka. Status Tidak Deal tetap dipertahankan.'
          : 'Pesan tindak lanjut siap ditinjau dan dikirim.', 'success');
    } catch (error) {
      showError(error.message || 'Status booking belum berhasil diperbarui.');
    }
  }
  async function openOrderConversion() {
    if (!currentBooking) return;
    if (!apiMode) {
      showWarning('Konfirmasi order memerlukan koneksi database Laravel.');
      return;
    }

    const form = $('#orderForm');
    form.reset();
    form.elements.order_area.value = Number(currentBooking.area) > 0 ? formatThousandsInput(String(currentBooking.area).replace('.', ','), 2) : '';
    $('#orderBookingSummary').textContent = `${currentBooking.name} · ${currentBooking.id} · ${currentBooking.need || 'Layanan JASPEST'}`;
    const select = $('#orderTechnicianSelect');
    const submit = $('#orderSubmitButton');
    const hint = $('#orderTechnicianHint');
    select.innerHTML = '<option value="">Memuat daftar teknisi…</option>';
    select.disabled = true;
    submit.disabled = true;
    hint.textContent = 'Memuat teknisi yang tersedia…';
    hint.classList.remove('is-warning');
    showModal('orderModal');

    try {
      const response = await apiRequest('/booking-technicians');
      const list = Array.isArray(response.data) ? response.data : [];
      const available = list.filter((item) => item.is_available === true || item.operational_status === 'available');
      const statusText = { available: 'Tersedia', assigned: 'Sedang bertugas', off_duty: 'Di luar tugas' };
      if (list.length === 0) {
        select.innerHTML = '<option value="">Belum ada data teknisi</option>';
        hint.textContent = 'Tambahkan teknisi terlebih dahulu melalui menu Data Teknisi.';
        hint.classList.add('is-warning');
        select.disabled = true;
        submit.disabled = true;
        return;
      }

      select.innerHTML = '<option value="">Pilih teknisi yang tersedia</option>' + list.map((technician) => {
        const isAvailable = technician.is_available === true || technician.operational_status === 'available';
        const label = `${technician.technician_code || 'TKN'} — ${technician.full_name} · ${technician.specialization || 'Teknisi'}`;
        const suffix = isAvailable ? '' : ` (${statusText[technician.operational_status] || 'Tidak tersedia'})`;
        return `<option value="${Number(technician.id)}" ${isAvailable ? '' : 'disabled'}>${esc(label + suffix)}</option>`;
      }).join('');
      select.disabled = available.length === 0;
      submit.disabled = available.length === 0;
      hint.textContent = available.length
        ? `${available.length} teknisi tersedia. Teknisi yang sudah bertugas atau di luar tugas tidak dapat dipilih.`
        : 'Tidak ada teknisi yang tersedia. Ubah status teknisi menjadi Tersedia di menu Data Teknisi.';
      hint.classList.toggle('is-warning', available.length === 0);
    } catch (error) {
      closeModal('orderModal');
      showError(error.message || 'Daftar teknisi gagal dimuat.');
    }
  }

  async function saveOrderConversion(event) {
    event.preventDefault();
    if (!currentBooking) return;
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const submit = $('#orderSubmitButton');
    const original = submit.innerHTML;
    const values = Object.fromEntries(new FormData(form).entries());
    values.order_price = parseThousandsInput(values.order_price);
    values.order_area = parseThousandsInput(values.order_area);
    const technicianLabel = $('#orderTechnicianSelect').selectedOptions[0]?.textContent || 'Teknisi';
    submit.disabled = true;
    const confirmed = await confirmDialog({
      title: 'Konfirmasi Deal Order?',
      message: `Booking ${currentBooking.id} akan dikonfirmasi sebagai Deal Order dengan harga ${formatRupiah(values.order_price)}, luasan ${Number(values.order_area).toLocaleString('id-ID')} m², pelaksanaan ${formatDateLong(values.scheduled_date)}, dan teknisi ${technicianLabel}. Tindakan ini tidak dapat dibatalkan dari halaman booking.`,
      confirmText: 'Konfirmasi Deal Order'
    });
    if (!confirmed) {
      submit.disabled = false;
      return;
    }

    submit.innerHTML = '<span>Menyimpan order…</span>';
    try {
      const updated = await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/status`, {
        method: 'PATCH',
        body: {
          status: 'converted',
          order_price: values.order_price,
          order_area: values.order_area,
          scheduled_date: values.scheduled_date,
          assigned_technician_id: values.assigned_technician_id
        }
      });
      currentBooking = { ...currentBooking, ...updated };
      closeModal('orderModal');
      closeModal('detailModal');
      try {
        await Promise.all([refreshDashboard(), refreshBookings()]);
      } catch (refreshError) {
        showWarning('Order sudah tersimpan, tetapi ringkasan belum dapat diperbarui. Muat ulang halaman untuk melihat data terbaru.');
        return;
      }
      const notice = updated.whatsapp_notification;
      if (notice?.status === 'accepted' || notice?.status === 'sent') {
        toast('Deal Order berhasil dikonfirmasi. Pesan konfirmasi masuk antrean pengiriman.', 'success');
      } else if (notice?.status === 'not_configured') {
        showWarning('Deal Order berhasil disimpan, tetapi pesan konfirmasi belum dapat dikirim. Hubungi administrator sistem.');
      } else if (notice?.status === 'failed') {
        showWarning('Deal Order berhasil disimpan, tetapi pesan konfirmasi belum berhasil dikirim. Buka detail order untuk mencoba kembali.');
      } else if (notice?.status === 'unknown') {
        showWarning('Deal Order berhasil disimpan, tetapi status pengiriman belum dapat dipastikan. Periksa riwayat pesan sebelum mencoba kembali.');
      } else if (notice?.status === 'skipped_consent' || notice?.status === 'skipped_invalid_number') {
        showWarning('Deal Order berhasil disimpan, tetapi notifikasi tidak dikirim. Periksa persetujuan dan nomor WhatsApp pelanggan.');
      } else {
        toast('Deal Order berhasil dikonfirmasi. Teknisi sekarang berstatus Sedang bertugas.', 'success');
      }
    } catch (error) {
      showError(error.message || 'Order belum berhasil disimpan. Periksa data dan ketersediaan teknisi.');
    } finally {
      submit.disabled = false;
      submit.innerHTML = original;
    }
  }

  function updateManualCompanyField() {
    const isBusiness = $('#manualPropertyType').value === 'bisnis';
    const wrapper = $('#manualCompanyField');
    const input = $('#manualCompanyName');
    wrapper.hidden = !isBusiness;
    input.required = isBusiness;
    if (!isBusiness) input.value = '';
  }

  async function openManualBooking() {
    if (!apiMode) {
      showWarning('Penambahan Deal Order memerlukan koneksi ke server.');
      return;
    }

    const form = $('#manualBookingForm');
    const serviceSelect = $('#manualServiceSelect');
    const technicianSelect = $('#manualTechnicianSelect');
    const submit = $('#manualBookingSubmit');
    const hint = $('#manualTechnicianHint');

    form.reset();
    updateManualCompanyField();
    serviceSelect.innerHTML = '<option value="">Memuat layanan…</option>';
    technicianSelect.innerHTML = '<option value="">Memuat teknisi…</option>';
    technicianSelect.disabled = true;
    submit.disabled = true;
    hint.textContent = 'Memuat daftar teknisi…';
    hint.classList.remove('is-warning');
    showModal('manualBookingModal');

    try {
      const [serviceResponse, technicianResponse] = await Promise.all([
        apiRequest('/content/services'),
        apiRequest('/booking-technicians')
      ]);

      const services = (Array.isArray(serviceResponse.data) ? serviceResponse.data : [])
        .filter((item) => item.status === 'Aktif' && item.slug)
        .map((item) => ({ slug: item.slug, title: item.title }));
      serviceSelect.innerHTML = '<option value="">Pilih layanan</option>'
        + '<option value="konsultasi">Konsultasi</option>'
        + services.map((service) => `<option value="${esc(service.slug)}">${esc(service.title)}</option>`).join('');

      const technicians = Array.isArray(technicianResponse.data) ? technicianResponse.data : [];
      const available = technicians.filter((item) => item.is_available === true || item.operational_status === 'available');
      const statusText = { available: 'Tersedia', assigned: 'Sedang bertugas', off_duty: 'Di luar tugas' };
      technicianSelect.innerHTML = '<option value="">Pilih teknisi</option>' + technicians.map((technician) => {
        const isAvailable = technician.is_available === true || technician.operational_status === 'available';
        const label = `${technician.technician_code || 'TKN'} — ${technician.full_name}`;
        const suffix = isAvailable ? '' : ` (${statusText[technician.operational_status] || 'Tidak tersedia'})`;
        return `<option value="${Number(technician.id)}" ${isAvailable ? '' : 'disabled'}>${esc(label + suffix)}</option>`;
      }).join('');
      technicianSelect.disabled = available.length === 0;
      submit.disabled = available.length === 0;
      hint.textContent = available.length
        ? `${available.length} teknisi tersedia.`
        : 'Belum ada teknisi yang tersedia. Perbarui status teknisi sebelum menambahkan Deal Order.';
      hint.classList.toggle('is-warning', available.length === 0);
    } catch (error) {
      closeModal('manualBookingModal');
      showError(error.message || 'Form Deal Order gagal dimuat.');
    }
  }

  async function saveManualBooking(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;

    const submit = $('#manualBookingSubmit');
    const original = submit.innerHTML;
    const values = Object.fromEntries(new FormData(form).entries());
    values.order_price = parseThousandsInput(values.order_price);
    values.order_area = parseThousandsInput(values.order_area);
    values.company_name = values.property_type === 'bisnis' ? String(values.company_name || '').trim() : null;
    values.description = String(values.description || '').trim();
    values.consent = true;

    const price = Number(values.order_price);
    const area = Number(values.order_area);
    if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(area) || area <= 0) {
      showWarning('Periksa kembali harga dan luasan pekerjaan. Keduanya harus lebih dari nol.');
      return;
    }

    const customer = values.company_name || values.full_name;
    const technicianLabel = $('#manualTechnicianSelect').selectedOptions[0]?.textContent || 'Teknisi';
    submit.disabled = true;
    const confirmed = await confirmDialog({
      title: 'Simpan Deal Order?',
      message: `${customer} akan langsung dicatat sebagai Deal Order dengan nilai ${formatRupiah(price)}, luasan ${area.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} m², pelaksanaan ${formatDateLong(values.scheduled_date)}, dan teknisi ${technicianLabel}.`,
      confirmText: 'Simpan Deal Order'
    });
    if (!confirmed) {
      submit.disabled = false;
      return;
    }

    submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const created = await apiRequest('/bookings/manual', { method: 'POST', body: values });
      closeModal('manualBookingModal');
      $('#bookingSearch').value = '';
      $('#statusFilter').value = 'converted';
      $('#typeFilter').value = 'all';
      $('#dateFrom').value = '';
      $('#dateTo').value = '';
      bookingPage = 1;
      syncDateLimits();
      syncSummaryFilter();
      const refreshResults = await Promise.allSettled([refreshDashboard(), refreshBookings()]);
      if (refreshResults.some((result) => result.status === 'rejected')) {
        showWarning('Deal Order berhasil disimpan. Sebagian ringkasan belum diperbarui; muat ulang halaman untuk melihat data terbaru.');
      } else {
        const notice = created.whatsapp_notification;
        if (notice?.status === 'accepted' || notice?.status === 'sent') {
          toast(`Deal Order ${created.reference || created.id} berhasil ditambahkan. Pesan konfirmasi masuk antrean pengiriman.`, 'success');
        } else if (notice?.status === 'not_configured' || notice?.status === 'failed' || notice?.status === 'unknown') {
          showWarning(`Deal Order ${created.reference || created.id} berhasil ditambahkan, tetapi pesan konfirmasi belum dapat dipastikan terkirim. Periksa detail order.`);
        } else if (notice?.status === 'skipped_consent' || notice?.status === 'skipped_invalid_number') {
          showWarning('Deal Order berhasil ditambahkan. Periksa persetujuan dan nomor kontak pelanggan.');
        } else {
          toast(`Deal Order ${created.reference || created.id} berhasil ditambahkan.`, 'success');
        }
      }
      if ($('#page-clients')?.classList.contains('active')) {
        refreshClients().catch((error) => showError(error.message));
      }
      if ($('#page-technicians')?.classList.contains('active')) {
        refreshTechnicians().catch((error) => showError(error.message));
      }
    } catch (error) {
      showError(error.message || 'Deal Order belum berhasil disimpan. Periksa kembali data dan ketersediaan teknisi.');
    } finally {
      submit.disabled = false;
      submit.innerHTML = original;
    }
  }

  function closeAllModals() {
    $$('.modal-backdrop:not([hidden])').forEach((modal) => closeModal(modal.id));
  }
  async function saveBookingStatus(status) {
    if (status === 'converted') {
      await openOrderConversion();
      return false;
    }
    if (!currentBooking) return false;
    const fromStatus = currentBooking.status;
    if (status === fromStatus) return false;
    // Konfirmasi selalu muncul untuk semua perubahan status.
    const confirmed = await confirmDialog({
      title: `Ubah status menjadi “${statusLabel(status)}”?`,
      message: `Status akan diubah dari “${statusLabel(fromStatus)}” menjadi “${statusLabel(status)}”.`,
      confirmText: 'Ya, simpan'
    });
    if (!confirmed) return false;
    try {
      if (apiMode) {
        const updated = await apiRequest(`/bookings/${encodeURIComponent(currentBooking.id)}/status`, { method: 'PATCH', body: { status } });
        currentBooking = { ...currentBooking, ...updated };
        await Promise.all([refreshDashboard(), refreshBookings()]);
      } else {
        const target = bookings.find((item) => item.id === currentBooking.id);
        if (target) {
          target.status = status;
          if (status === 'followup') target.followedUpAt = new Date().toISOString();
          saveLocal('jaspest-admin-bookings', bookings);
        }
        currentBooking.status = status;
        renderDetailStatus(currentBooking);
        updateStats(); renderRecent(); renderBookings();
      }
      // Setelah tersimpan, semua popup (konfirmasi + detail) langsung ditutup.
      closeAllModals();
      toast('Status booking berhasil diperbarui.', 'success');
      return true;
    } catch (error) { showError(error.message); return false; }
  }
  async function handleExport() {
    try {
      if (!apiMode) {
        showWarning('Export Excel memerlukan koneksi database Laravel. Mode preview hanya menyimpan data contoh di browser.');
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
      toast('File Excel berhasil diunduh.', 'success');
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
        ${(item.image_url || item.default_image_url) ? `<img class="content-row-image" src="${esc(item.image_url || item.default_image_url)}" data-fallbacks="${esc(item.image_url && item.default_image_url ? item.default_image_url : '')}" alt="" loading="lazy">` : `<div class="content-row-icon">${contentIcon(currentContentType, item)}</div>`}
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
    if (currentContentType === 'pages') { showWarning('Slot halaman utama sudah ditentukan. Edit slot yang tersedia.'); return; }
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
  // Perkecil foto besar di browser (maks. 1600 px, WebP) agar tidak terkena batas upload PHP/server.
  async function optimizeImage(file) {
    if (!(file instanceof File) || !file.size || !/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
    const MAX_SIDE = 1600, LIMIT = 1.5 * 1024 * 1024;
    let bitmap;
    try { bitmap = await createImageBitmap(file); } catch { return file; }
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    if (scale === 1 && file.size <= LIMIT) { bitmap.close?.(); return file; }
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    let blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85));
    if (!blob || blob.type !== 'image/webp') blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
    if (!blob || blob.size >= file.size) return file;
    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
    return new File([blob], `${file.name.replace(/\.[^.]+$/, '') || 'gambar'}.${ext}`, { type: blob.type });
  }
  async function saveContent(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true; submit.textContent = 'Menyimpan…';
    try {
      const formData = new FormData(form);
      if (!form.elements.remove_image.checked) formData.delete('remove_image');
      const picked = formData.get('image');
      if (picked instanceof File && picked.size) {
        const optimized = await optimizeImage(picked);
        if (optimized.size > 5 * 1024 * 1024) throw new Error('Ukuran gambar maksimal 5 MB.');
        formData.set('image', optimized, optimized.name);
      } else {
        formData.delete('image');
      }
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
        if (values.image?.size) toast('Upload gambar baru aktif setelah modul Laravel/MySQL dipasang.', 'warning');
      }
      renderContent(); closeModal('contentModal'); toast(apiMode ? 'Konten berhasil disimpan ke database.' : 'Perubahan preview tersimpan di browser.', 'success');
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
      renderContent(); toast(apiMode ? 'Konten berhasil dihapus.' : 'Konten dihapus dari preview.', 'success');
    } catch (error) { showError(error.message); }
  }
  function accountStatusLabel(active) { return active ? 'Aktif' : 'Nonaktif'; }
  function accountStatusClass(active) { return active ? 'status-contacted' : 'status-new'; }
  function divisionLabel(role) {
    return ({ direksi: 'Direksi', finance: 'Finance', logistik: 'Logistik', marketing: 'Marketing' })[role] || 'Tidak diketahui';
  }
  function technicianStatusLabel(status) {
    return ({ available: 'Tersedia', assigned: 'Sedang bertugas', off_duty: 'Di luar tugas' })[status] || status;
  }
  function technicianStatusClass(status) {
    return ({ available: 'technician-state-available', assigned: 'technician-state-assigned', off_duty: 'technician-state-off-duty' })[status] || 'technician-state-off-duty';
  }
  function updateAccountSummary(summary = {}) {
    $('#accountTotal').textContent = Number(summary.total || 0);
    $('#accountActive').textContent = Number(summary.active || 0);
    $('#accountInactive').textContent = Number(summary.inactive || 0);
  }
  function updateTechnicianSummary(summary = {}) {
    $('#technicianTotal').textContent = Number(summary.total || 0);
    $('#technicianAvailable').textContent = Number(summary.available || 0);
    $('#technicianAssigned').textContent = Number(summary.assigned || 0);
    $('#technicianOffDuty').textContent = Number(summary.off_duty || 0);
  }
  function renderAccountRows() {
    $('#accountRows').innerHTML = accounts.map((account) => `
      <tr>
        <td><div class="master-person"><span class="client-initial">${esc(initials(account.name))}</span><span><strong>${esc(account.name)}</strong><small>@${esc(account.username)}</small></span></div></td>
        <td class="master-cell"><strong>${esc(account.role_label || divisionLabel(account.role))}</strong></td>
        <td class="master-contact">${esc(account.phone || '—')}</td>
        <td><span class="status-pill ${accountStatusClass(account.is_active)}">${esc(accountStatusLabel(account.is_active))}</span></td>
        <td class="master-actions"><div class="table-actions"><button type="button" class="table-action" data-edit-account="${esc(account.id)}" title="Edit akun" aria-label="Edit akun ${esc(account.name)}">${uiIcon('edit')}<span>Edit</span></button><button type="button" class="table-action delete" data-delete-account="${esc(account.id)}" title="Hapus akun" aria-label="Hapus akun ${esc(account.name)}">${uiIcon('trash')}<span>Hapus</span></button></div></td>
      </tr>`).join('');
    $('#emptyAccounts').hidden = accounts.length > 0;
    $('#accountCount').textContent = `${accounts.length} akun`;
    $('#accountTableCount').textContent = `Menampilkan ${accounts.length} dari ${accountMeta.total} akun`;
    $('#accountPageLabel').textContent = `${accountMeta.current_page || 1} / ${accountMeta.last_page || 1}`;
    $('#accountsPrev').disabled = (accountMeta.current_page || 1) <= 1;
    $('#accountsNext').disabled = (accountMeta.current_page || 1) >= (accountMeta.last_page || 1);
    $$('[data-account-filter]').forEach((item) => item.setAttribute('aria-pressed', String(item.dataset.accountFilter === ($('#accountStatusFilter').value || 'all'))));
  }
  async function refreshAccounts() {
    if (!canManageAccounts) return;
    if (!apiMode) { showWarning('Pengelolaan akun memerlukan koneksi database server.'); return; }
    const params = new URLSearchParams({
      search: $('#accountSearch').value.trim(),
      status: $('#accountStatusFilter').value || 'all',
      page: String(accountPage)
    });
    const response = await apiRequest(`/accounts?${params.toString()}`);
    accounts = response.data || [];
    accountMeta = response.meta || { current_page: 1, last_page: 1, total: accounts.length };
    updateAccountSummary(response.summary || {});
    renderAccountRows();
  }
  function resetAccountForm() {
    const form = $('#accountForm');
    form.reset();
    form.elements.role.value = '';
    form.elements.is_active.value = '1';
    form.elements.password.value = '';
    form.elements.password_confirmation.value = '';
    form.elements.password.required = true;
    $('#accountPasswordRequired').hidden = false;
    $('#accountPasswordHint').textContent = 'Wajib diisi saat membuat akun.';
  }
  function addAccount() {
    if (!canManageAccounts) return;
    accountEditingId = null;
    resetAccountForm();
    $('#accountModalTitle').textContent = 'Buat akun';
    showModal('accountModal');
  }
  function editAccount(id) {
    if (!canManageAccounts) return;
    const account = accounts.find((item) => String(item.id) === String(id));
    if (!account) return;
    accountEditingId = account.id;
    const form = $('#accountForm');
    Object.entries({ name: account.name, username: account.username, phone: account.phone || '', role: account.role || '', is_active: account.is_active ? '1' : '0' })
      .forEach(([key, value]) => { form.elements.namedItem(key).value = value ?? ''; });
    form.elements.password.value = '';
    form.elements.password_confirmation.value = '';
    form.elements.password.required = false;
    $('#accountPasswordRequired').hidden = true;
    $('#accountPasswordHint').textContent = 'Kosongkan bila tidak ingin mengganti password.';
    $('#accountModalTitle').textContent = 'Edit akun';
    showModal('accountModal');
  }
  async function saveAccount(event) {
    event.preventDefault();
    if (!canManageAccounts) return;
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true;
    submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      if (!values.password) {
        delete values.password;
        delete values.password_confirmation;
      }
      const path = `/accounts${accountEditingId ? `/${encodeURIComponent(accountEditingId)}` : ''}`;
      await apiRequest(path, { method: accountEditingId ? 'PUT' : 'POST', body: values });
      accountPage = 1;
      await refreshAccounts();
      closeModal('accountModal');
      toast(accountEditingId ? 'Akun berhasil diperbarui.' : 'Akun berhasil dibuat.', 'success');
      accountEditingId = null;
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }
  async function deleteAccount(id) {
    if (!canManageAccounts) return;
    const account = accounts.find((item) => String(item.id) === String(id));
    if (!account) return;
    if (!(await confirmDialog({
      title: 'Hapus akun?',
      message: `Akun ${account.name} (@${account.username}) akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`,
      confirmText: 'Hapus akun', danger: true
    }))) return;
    try {
      await apiRequest(`/accounts/${encodeURIComponent(id)}`, { method: 'DELETE' });
      if (accountPage > 1 && accounts.length === 1) accountPage -= 1;
      await refreshAccounts();
      toast('Akun berhasil dihapus.', 'success');
    } catch (error) { showError(error.message); }
  }

  function clientStatusLabel(status) { return status === 'active' ? 'Aktif' : 'Nonaktif'; }
  function clientStatusClass(status) { return status === 'active' ? 'status-contacted' : 'status-new'; }
  function updateClientSummary(summary = {}) {
    $('#clientTotal').textContent = Number(summary.total || 0);
    $('#clientActive').textContent = Number(summary.active || 0);
    $('#clientLinkedOrders').textContent = Number(summary.linked_orders || 0);
  }
  function renderClientRows() {
    $('#clientRows').innerHTML = clients.map((client) => `
      <tr>
        <td><div class="master-person"><span class="client-initial">${esc(initials(client.full_name))}</span><span><strong>${esc(client.full_name)}</strong><small>${esc(client.client_code)}</small></span></div></td>
        <td class="master-cell">${esc(client.company_name || 'Perorangan')}</td>
        <td class="master-contact">${esc(formatPhone(client.whatsapp))}</td>
        <td><span class="client-order-count">${Number(client.order_count || 0)} order</span></td>
        <td class="master-cell"><strong>${esc(formatRupiah(client.lifetime_value || 0))}</strong></td>
        <td><span class="status-pill ${clientStatusClass(client.status)}">${esc(clientStatusLabel(client.status))}</span></td>
        <td class="master-actions"><div class="table-actions"><button type="button" class="table-action" data-edit-client="${esc(client.id)}" title="Edit data klien" aria-label="Edit data klien ${esc(client.full_name)}">${uiIcon('edit')}<span>Edit</span></button></div></td>
      </tr>`).join('');
    $('#emptyClients').hidden = clients.length > 0;
    $('#clientCount').textContent = `${clientMeta.total || 0} klien`;
    $('#clientDirectoryCount').textContent = `${clientMeta.total || 0} klien`;
    $('#clientTableCount').textContent = `Menampilkan ${clients.length} dari ${clientMeta.total || 0} klien`;
    $('#clientPageLabel').textContent = `${clientMeta.current_page || 1} / ${clientMeta.last_page || 1}`;
    $('#clientsPrev').disabled = (clientMeta.current_page || 1) <= 1;
    $('#clientsNext').disabled = (clientMeta.current_page || 1) >= (clientMeta.last_page || 1);
  }
  async function refreshClients() {
    if (!apiMode) { showWarning('Data Klien memerlukan koneksi database server.'); return; }
    const params = new URLSearchParams({
      search: $('#clientSearch').value.trim(),
      status: $('#clientStatusFilter').value || 'all',
      page: String(clientPage)
    });
    const response = await apiRequest(`/clients?${params.toString()}`);
    clients = response.data || [];
    clientMeta = response.meta || { current_page: 1, last_page: 1, total: clients.length };
    updateClientSummary(response.summary || {});
    renderClientRows();
    if (Number(response.sync_issues || 0) > 0) {
      showWarning(`${Number(response.sync_issues)} Deal Order belum dapat disinkronkan karena data identitas perlu diperiksa.`);
    }
  }
  function editClient(id) {
    const client = clients.find((item) => String(item.id) === String(id));
    if (!client) return;
    clientEditingId = client.id;
    const form = $('#clientForm');
    Object.entries({
      full_name: client.full_name, company_name: client.company_name || '', whatsapp: client.whatsapp,
      email: client.email || '', address: client.address || '', status: client.status || 'active', notes: client.notes || ''
    }).forEach(([key, value]) => { form.elements.namedItem(key).value = value ?? ''; });
    $('#clientCodeReadonly').textContent = `Kode klien · ${client.client_code}`;
    $('#clientModalTitle').textContent = 'Edit data klien';
    showModal('clientModal');
  }
  async function saveClient(event) {
    event.preventDefault();
    if (!clientEditingId) return;
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true;
    submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      const response = await apiRequest(`/clients/${encodeURIComponent(clientEditingId)}`, { method: 'PUT', body: values });
      await refreshClients();
      closeModal('clientModal');
      toast(response.message || 'Data klien berhasil diperbarui.', 'success');
      clientEditingId = null;
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }

  function renderTechnicianRows() {
    $('#technicianRows').innerHTML = technicians.map((technician) => `
      <tr>
        <td><div class="master-person"><span class="client-initial">${esc(initials(technician.full_name))}</span><span><strong>${esc(technician.full_name)}</strong><small>${esc(technician.technician_code)}</small></span></div></td>
        <td class="master-cell"><strong>${esc(technician.specialization)}</strong><small>${esc(technician.certification || 'Sertifikasi belum dicatat')}</small></td>
        <td class="master-cell">${esc(technician.service_area || 'Belum ditentukan')}<small>${esc(formatPhone(technician.whatsapp))}</small></td>
        <td><span class="status-pill technician-status-chip ${technicianStatusClass(technician.operational_status)}">${esc(technicianStatusLabel(technician.operational_status))}</span></td>
        <td class="master-actions"><div class="table-actions"><button type="button" class="table-action" data-edit-technician="${esc(technician.id)}" title="Edit teknisi" aria-label="Edit ${esc(technician.full_name)}">${uiIcon('edit')}<span>Edit</span></button><button type="button" class="table-action delete" data-delete-technician="${esc(technician.id)}" title="Hapus teknisi" aria-label="Hapus teknisi ${esc(technician.full_name)}">${uiIcon('trash')}<span>Hapus</span></button></div></td>
      </tr>`).join('');
    $('#emptyTechnicians').hidden = technicians.length > 0;
    $('#technicianCount').textContent = `${technicians.length} data`;
    $('#technicianTableCount').textContent = `Menampilkan ${technicians.length} dari ${technicianMeta.total} teknisi`;
    $('#technicianPageLabel').textContent = `${technicianMeta.current_page || 1} / ${technicianMeta.last_page || 1}`;
    $('#techniciansPrev').disabled = (technicianMeta.current_page || 1) <= 1;
    $('#techniciansNext').disabled = (technicianMeta.current_page || 1) >= (technicianMeta.last_page || 1);
    $$('[data-technician-filter]').forEach((item) => item.setAttribute('aria-pressed', String(item.dataset.technicianFilter === ($('#technicianStatusFilter').value || 'all'))));
  }
  async function refreshTechnicians() {
    if (!apiMode) { showWarning('Data teknisi memerlukan koneksi database server.'); return; }
    const params = new URLSearchParams({
      search: $('#technicianSearch').value.trim(),
      status: $('#technicianStatusFilter').value || 'all',
      page: String(technicianPage)
    });
    const response = await apiRequest(`/technicians?${params.toString()}`);
    technicians = response.data || [];
    technicianMeta = response.meta || { current_page: 1, last_page: 1, total: technicians.length };
    updateTechnicianSummary(response.summary || {});
    renderTechnicianRows();
  }
  function addTechnician() {
    technicianEditingId = null;
    const form = $('#technicianForm');
    form.reset();
    form.elements.operational_status.value = 'available';
    $('#technicianModalTitle').textContent = 'Tambah teknisi';
    showModal('technicianModal');
  }
  function editTechnician(id) {
    const technician = technicians.find((item) => String(item.id) === String(id));
    if (!technician) return;
    technicianEditingId = technician.id;
    const form = $('#technicianForm');
    Object.entries({
      full_name: technician.full_name, whatsapp: technician.whatsapp,
      address: technician.address, technician_code: technician.technician_code,
      specialization: technician.specialization, service_area: technician.service_area,
      certification: technician.certification, operational_status: technician.operational_status,
      notes: technician.notes
    }).forEach(([key, value]) => { form.elements.namedItem(key).value = value ?? ''; });
    $('#technicianModalTitle').textContent = 'Edit data teknisi';
    showModal('technicianModal');
  }
  async function saveTechnician(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const original = submit.innerHTML;
    submit.disabled = true;
    submit.innerHTML = '<span>Menyimpan…</span>';
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      const path = `/technicians${technicianEditingId ? `/${encodeURIComponent(technicianEditingId)}` : ''}`;
      await apiRequest(path, { method: technicianEditingId ? 'PUT' : 'POST', body: values });
      technicianPage = 1;
      await refreshTechnicians();
      closeModal('technicianModal');
      toast(technicianEditingId ? 'Data teknisi berhasil diperbarui.' : 'Data teknisi berhasil ditambahkan.', 'success');
      technicianEditingId = null;
    } catch (error) { showError(error.message); }
    finally { submit.disabled = false; submit.innerHTML = original; }
  }
  async function deleteTechnician(id) {
    const technician = technicians.find((item) => String(item.id) === String(id));
    if (!technician) return;
    if (!(await confirmDialog({
      title: 'Hapus data teknisi?',
      message: `Data teknisi ${technician.full_name} (${technician.technician_code}) akan dihapus permanen.`,
      confirmText: 'Hapus teknisi', danger: true
    }))) return;
    try {
      await apiRequest(`/technicians/${encodeURIComponent(id)}`, { method: 'DELETE' });
      if (technicianPage > 1 && technicians.length === 1) technicianPage -= 1;
      await refreshTechnicians();
      toast('Data teknisi berhasil dihapus.', 'success');
    } catch (error) { showError(error.message); }
  }

  function navigate(page) {
    // Keep the Data Master group expanded while either child workspace is active.
    if (page === 'personnel') page = 'technicians';
    if (page === 'accounts' && !canManageAccounts) {
      page = 'dashboard';
      showWarning('Anda tidak memiliki akses ke Data Master.');
    }
    $$('.page-view').forEach((section) => section.classList.remove('active'));
    const target = page === 'content' ? 'content' : page;
    $(`#page-${target}`)?.classList.add('active');
    $$('.nav-link[data-page]').forEach((button) => button.classList.toggle('active', button.dataset.page === page || (page === 'content' && button.dataset.page === currentContentType)));
    const masterNavToggle = $('#masterNavToggle');
    const masterSubmenu = $('#masterSubmenu');
    const masterPageActive = ['accounts', 'technicians', 'clients', 'stock-catalog'].includes(page);
    if (masterNavToggle && masterSubmenu) {
      masterNavToggle.classList.toggle('active', masterPageActive);
      masterNavToggle.setAttribute('aria-expanded', String(masterPageActive));
      masterSubmenu.hidden = !masterPageActive;
    }
    const pageTitles = { bookings: 'Data Booking', accounts: 'Data Karyawan', technicians: 'Data Teknisi', clients: 'Data Klien', 'stock-catalog': 'Data Stok & Bahan', inventory: 'Inventaris & Gudang' };
    const label = page === 'content' ? contentLabels[currentContentType] : (pageTitles[page] || 'Ringkasan');
    $('#breadcrumbCurrent').textContent = label;
    history.replaceState(null, '', `#${page === 'content' ? currentContentType : page}`);
    $('#sidebar').classList.remove('open');
    document.body.classList.remove('admin-menu-open');
    $('#mobileMenu').setAttribute('aria-expanded', 'false');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (page === 'bookings') refreshBookings().catch((error) => showError(error.message));
    if (page === 'accounts' && canManageAccounts) refreshAccounts().catch((error) => showError(error.message));
    if (page === 'technicians') refreshTechnicians().catch((error) => showError(error.message));
    if (page === 'clients') refreshClients().catch((error) => showError(error.message));
    if (page === 'stock-catalog') refreshStockCatalogPage().catch((error) => showError(error.message));
    if (page === 'inventory') refreshInventory().catch((error) => showError(error.message));
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
          if ($('#page-clients')?.classList.contains('active')) await refreshClients();

          // Keep an open detail modal in sync when its record was the one updated.
          const reference = payload.reference;
          if (!$('#detailModal')?.hidden && currentBooking &&
              (!reference || String(currentBooking.id) === String(reference))) {
            await openDetail(currentBooking.id);
          }
        } while (realtimeRefreshRequested);

        if (realtimeNewReference) {
          announceNewBooking(`Pengajuan baru masuk (${realtimeNewReference}). Semua data dashboard diperbarui.`);
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
    ensurePhotoLightbox();
    document.addEventListener('click', (event) => {
      const photoButton = event.target.closest('#detailPhotos [data-photo-url]');
      if (photoButton) {
        event.preventDefault();
        openPhotoLightbox(photoButton.dataset.photoUrl, photoButton.dataset.photoName || 'Foto klien', photoButton);
        return;
      }
      const navToggle = event.target.closest('[data-nav-toggle="master"]');
      if (navToggle) {
        const submenu = $('#masterSubmenu');
        const expanded = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!expanded));
        if (submenu) submenu.hidden = expanded;
        return;
      }
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
      const remove = event.target.closest('[data-delete-content]'); if (remove) { deleteContent(remove.dataset.deleteContent); return; }
      const editAccountButton = event.target.closest('[data-edit-account]'); if (editAccountButton) { editAccount(editAccountButton.dataset.editAccount); return; }
      const removeAccountButton = event.target.closest('[data-delete-account]'); if (removeAccountButton) { deleteAccount(removeAccountButton.dataset.deleteAccount); return; }
      const editTechnicianButton = event.target.closest('[data-edit-technician]'); if (editTechnicianButton) { editTechnician(editTechnicianButton.dataset.editTechnician); return; }
      const removeTechnicianButton = event.target.closest('[data-delete-technician]'); if (removeTechnicianButton) { deleteTechnician(removeTechnicianButton.dataset.deleteTechnician); return; }
      const editClientButton = event.target.closest('[data-edit-client]'); if (editClientButton) { editClient(editClientButton.dataset.editClient); return; }
      const editInventoryButton = event.target.closest('[data-edit-inventory-item]'); if (editInventoryButton) { editInventoryItem(editInventoryButton.dataset.editInventoryItem); return; }
      const removeInventoryButton = event.target.closest('[data-delete-inventory-item]'); if (removeInventoryButton) { deleteInventoryItem(removeInventoryButton.dataset.deleteInventoryItem); return; }
      const inventoryTabButton = event.target.closest('[data-inventory-tab]'); if (inventoryTabButton) { setInventoryTab(inventoryTabButton.dataset.inventoryTab).catch((error) => showError(error.message)); return; }
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
    const bindMasterSummary = (selector, filterSelector, pageName, refresh, pageSetter) => {
      $$(selector).forEach((card) => {
        const choose = () => {
          const target = card.dataset[filterSelector];
          const select = pageName === 'accounts' ? $('#accountStatusFilter') : $('#technicianStatusFilter');
          select.value = select.value === target && target !== 'all' ? 'all' : target;
          pageSetter(1);
          refresh().catch((error) => showError(error.message));
        };
        card.addEventListener('click', choose);
        card.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(); }
        });
      });
    };
    bindMasterSummary('[data-account-filter]', 'accountFilter', 'accounts', refreshAccounts, (page) => { accountPage = page; });
    bindMasterSummary('[data-technician-filter]', 'technicianFilter', 'technicians', refreshTechnicians, (page) => { technicianPage = page; });
    $('#accountSearch').addEventListener('input', () => {
      clearTimeout(accountSearchTimer); accountSearchTimer = setTimeout(() => { accountPage = 1; refreshAccounts().catch((error) => showError(error.message)); }, 320);
    });
    $('#accountStatusFilter').addEventListener('change', () => { accountPage = 1; refreshAccounts().catch((error) => showError(error.message)); });
    $('#technicianSearch').addEventListener('input', () => {
      clearTimeout(technicianSearchTimer); technicianSearchTimer = setTimeout(() => { technicianPage = 1; refreshTechnicians().catch((error) => showError(error.message)); }, 320);
    });
    $('#technicianStatusFilter').addEventListener('change', () => { technicianPage = 1; refreshTechnicians().catch((error) => showError(error.message)); });
    $('#clientSearch').addEventListener('input', () => {
      clearTimeout(clientSearchTimer); clientSearchTimer = setTimeout(() => { clientPage = 1; refreshClients().catch((error) => showError(error.message)); }, 320);
    });
    $('#clientStatusFilter').addEventListener('change', () => { clientPage = 1; refreshClients().catch((error) => showError(error.message)); });
    $('#accountsPrev').addEventListener('click', () => { if (accountPage > 1) { accountPage -= 1; refreshAccounts().catch((error) => showError(error.message)); } });
    $('#accountsNext').addEventListener('click', () => { if (accountPage < accountMeta.last_page) { accountPage += 1; refreshAccounts().catch((error) => showError(error.message)); } });
    $('#techniciansPrev').addEventListener('click', () => { if (technicianPage > 1) { technicianPage -= 1; refreshTechnicians().catch((error) => showError(error.message)); } });
    $('#techniciansNext').addEventListener('click', () => { if (technicianPage < technicianMeta.last_page) { technicianPage += 1; refreshTechnicians().catch((error) => showError(error.message)); } });
    $('#clientsPrev').addEventListener('click', () => { if (clientPage > 1) { clientPage -= 1; refreshClients().catch((error) => showError(error.message)); } });
    $('#clientsNext').addEventListener('click', () => { if (clientPage < clientMeta.last_page) { clientPage += 1; refreshClients().catch((error) => showError(error.message)); } });
    $('#stockCatalogSearch').addEventListener('input', () => {
      clearTimeout(stockCatalogSearchTimer);
      stockCatalogSearchTimer = setTimeout(() => { stockCatalogPage = 1; refreshStockCatalog().catch((error) => showError(error.message)); }, 320);
    });
    $('#stockCatalogTypeFilter').addEventListener('change', () => { stockCatalogPage = 1; refreshStockCatalog().catch((error) => showError(error.message)); });
    $('#stockCatalogStatusFilter').addEventListener('change', () => { stockCatalogPage = 1; refreshStockCatalog().catch((error) => showError(error.message)); });
    $('#stockCatalogPrev').addEventListener('click', () => { if (stockCatalogPage > 1) { stockCatalogPage -= 1; refreshStockCatalog().catch((error) => showError(error.message)); } });
    $('#stockCatalogNext').addEventListener('click', () => { if (stockCatalogPage < stockCatalogMeta.last_page) { stockCatalogPage += 1; refreshStockCatalog().catch((error) => showError(error.message)); } });
    $('#clientForm').addEventListener('submit', saveClient);
    $('#addAccount').addEventListener('click', addAccount);
    $('#emptyAddAccount').addEventListener('click', addAccount);
    $('#accountForm').addEventListener('submit', saveAccount);
    $('#addTechnician').addEventListener('click', addTechnician);
    $('#emptyAddTechnician').addEventListener('click', addTechnician);
    $('#technicianForm').addEventListener('submit', saveTechnician);
    $('#inventoryAddItem').addEventListener('click', addInventoryItem);
    $('#inventoryEmptyAddItem').addEventListener('click', addInventoryItem);
    $('#inventoryAddMovement').addEventListener('click', () => addInventoryMovement().catch((error) => showError(error.message)));
    $('#inventoryAddMaintenance').addEventListener('click', () => addInventoryMaintenance().catch((error) => showError(error.message)));
    $('#inventoryItemForm').addEventListener('submit', saveInventoryItem);
    $('#inventoryMovementForm').addEventListener('submit', saveInventoryMovement);
    $('#inventoryMaintenanceForm').addEventListener('submit', saveInventoryMaintenance);
    $('#inventoryMovementType').addEventListener('change', syncInventoryMovementFields);
    $('#inventoryMovementItem').addEventListener('change', syncInventoryMovementFields);
    $('#inventoryItemSearch').addEventListener('input', () => {
      clearTimeout(inventorySearchTimers.items);
      inventorySearchTimers.items = setTimeout(() => { inventoryItemPage = 1; refreshInventoryItems().catch((error) => showError(error.message)); }, 320);
    });
    $('#inventoryItemTypeFilter').addEventListener('change', () => { inventoryItemPage = 1; refreshInventoryItems().catch((error) => showError(error.message)); });
    $('#inventoryItemStatusFilter').addEventListener('change', () => { inventoryItemPage = 1; refreshInventoryItems().catch((error) => showError(error.message)); });
    $('#inventoryItemsPrev').addEventListener('click', () => { if (inventoryItemPage > 1) { inventoryItemPage -= 1; refreshInventoryItems().catch((error) => showError(error.message)); } });
    $('#inventoryItemsNext').addEventListener('click', () => { if (inventoryItemPage < inventoryItemMeta.last_page) { inventoryItemPage += 1; refreshInventoryItems().catch((error) => showError(error.message)); } });
    $('#inventoryMovementSearch').addEventListener('input', () => {
      clearTimeout(inventorySearchTimers.movements);
      inventorySearchTimers.movements = setTimeout(() => { inventoryMovementPage = 1; refreshInventoryMovements().catch((error) => showError(error.message)); }, 320);
    });
    $('#inventoryMovementTypeFilter').addEventListener('change', () => { inventoryMovementPage = 1; refreshInventoryMovements().catch((error) => showError(error.message)); });
    $('#inventoryMovementsPrev').addEventListener('click', () => { if (inventoryMovementPage > 1) { inventoryMovementPage -= 1; refreshInventoryMovements().catch((error) => showError(error.message)); } });
    $('#inventoryMovementsNext').addEventListener('click', () => { if (inventoryMovementPage < inventoryMovementMeta.last_page) { inventoryMovementPage += 1; refreshInventoryMovements().catch((error) => showError(error.message)); } });
    $('#inventoryTechStockSearch').addEventListener('input', () => {
      clearTimeout(inventorySearchTimers.technicians);
      inventorySearchTimers.technicians = setTimeout(() => { inventoryTechStockPage = 1; refreshInventoryTechnicianStocks().catch((error) => showError(error.message)); }, 320);
    });
    $('#inventoryTechStocksPrev').addEventListener('click', () => { if (inventoryTechStockPage > 1) { inventoryTechStockPage -= 1; refreshInventoryTechnicianStocks().catch((error) => showError(error.message)); } });
    $('#inventoryTechStocksNext').addEventListener('click', () => { if (inventoryTechStockPage < inventoryTechStockMeta.last_page) { inventoryTechStockPage += 1; refreshInventoryTechnicianStocks().catch((error) => showError(error.message)); } });
    $('#inventoryMaintenanceStatusFilter').addEventListener('change', () => { inventoryMaintenancePage = 1; refreshInventoryMaintenance().catch((error) => showError(error.message)); });
    $('#inventoryMaintenancePrev').addEventListener('click', () => { if (inventoryMaintenancePage > 1) { inventoryMaintenancePage -= 1; refreshInventoryMaintenance().catch((error) => showError(error.message)); } });
    $('#inventoryMaintenanceNext').addEventListener('click', () => { if (inventoryMaintenancePage < inventoryMaintenanceMeta.last_page) { inventoryMaintenancePage += 1; refreshInventoryMaintenance().catch((error) => showError(error.message)); } });
    $('#inventoryMaintenanceRows').addEventListener('change', (event) => {
      const select = event.target.closest('[data-maintenance-status]');
      if (select) updateInventoryMaintenance(select.dataset.maintenanceStatus, select.value);
    });
    $('#saveBookingNote').addEventListener('click', saveBookingNote);
    $('#addManualBooking').addEventListener('click', openManualBooking);
    $('#manualPropertyType').addEventListener('change', updateManualCompanyField);
    bindThousandsInputs($('#manualBookingForm'));
    $('#manualBookingForm').addEventListener('submit', saveManualBooking);
    bindThousandsInputs($('#orderForm'));
    $('#orderForm').addEventListener('submit', saveOrderConversion);
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
      document.addEventListener('visibilitychange', () => { if (!document.hidden) pollNewBookings(); });
    }
    $('#bookingsPrev').addEventListener('click', () => { if (bookingPage > 1) { bookingPage -= 1; refreshBookings().catch((error) => showError(error.message)); } });
    $('#bookingsNext').addEventListener('click', () => { if (bookingPage < bookingMeta.last_page) { bookingPage += 1; refreshBookings().catch((error) => showError(error.message)); } });
    $('#exportBookings').addEventListener('click', handleExport);
    $('#detailFollowupWhatsapp').addEventListener('click', () => currentBooking && openWhatsApp(currentBooking.id));
    $('#retryDealWhatsapp').addEventListener('click', retryDealWhatsappNotification);
    $('#copyWhatsAppPreview').addEventListener('click', async () => {
      const message = $('#whatsappPreviewMessage').textContent;
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(message);
        else { const field = document.createElement('textarea'); field.value = message; document.body.append(field); field.select(); document.execCommand('copy'); field.remove(); }
        toast('Format chat berhasil disalin.', 'success');
      } catch { showError('Tidak dapat menyalin otomatis. Silakan pilih dan salin teks pesan.'); }
    });
    $('#confirmOk').addEventListener('click', () => { settleConfirm(true); closeModal('confirmModal'); });
    // Each booking status is an independent action button.
    $('#detailStatusActions').addEventListener('click', async (event) => {
      const button = event.target.closest('[data-booking-status]');
      if (!button || !currentBooking || button.disabled) return;
      const nextStatus = button.dataset.bookingStatus;
      if (normalizeBookingStatus(currentBooking.status) === nextStatus) return;
      const buttons = $$('#detailStatusActions [data-booking-status]');
      buttons.forEach((item) => { item.disabled = true; });
      const saved = await saveBookingStatus(nextStatus);
      buttons.forEach((item) => { item.disabled = false; });
      if (!saved && currentBooking) renderDetailStatus(currentBooking);
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
      if (image instanceof HTMLImageElement && image.classList.contains('content-row-image')) {
        const next = image.dataset.fallbacks;
        if (next) { image.dataset.fallbacks = ''; image.src = next; return; }
        const icon = document.createElement('div');
        icon.className = 'content-row-icon';
        icon.innerHTML = contentIcon(currentContentType);
        image.replaceWith(icon);
        return;
      }
      if (!(image instanceof HTMLImageElement) || !image.closest('#detailPhotos')) return;
      image.classList.add('photo-load-failed');
      const hint = image.closest('.photo-link')?.querySelector('small');
      if (hint) hint.textContent = 'Gagal dimuat — klik untuk mencoba kembali';
    }, true);
    document.addEventListener('keydown', (event) => {
      if ($('#photoLightbox') && !$('#photoLightbox').hidden) {
        if (event.key === 'Escape') { event.preventDefault(); closePhotoLightbox(); }
        return;
      }
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
    } else if (['#bookings', '#accounts', '#employees', '#technicians', '#personnel', '#clients', '#stock-catalog', '#inventory'].includes(location.hash)) {
      const legacyPage = location.hash.slice(1);
      // Keep old bookmarks working while directing them to the newly separated pages.
      const destination = legacyPage === 'employees'
        ? (canManageAccounts ? 'accounts' : 'technicians')
        : (legacyPage === 'personnel' ? 'technicians' : legacyPage);
      navigate(destination);
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
        updateStats({ total: 0, new: 0, followup: 0, converted: 0, lost: 0 });
        knownTotal = null;
        renderRecent(); renderBookings(); renderChart([]);
        $('#demoNotice').hidden = false;
        $('#demoNotice').innerHTML = `<span>!</span><div><strong>Dashboard belum dapat memuat data server</strong><small>${esc(error.message)}</small></div>`;
      }
    }
  }

  init();
})();

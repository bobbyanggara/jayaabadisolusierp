/* Halaman Pemasukan (kas masuk, termasuk ekspor Excel) dan Piutang. Memakai data keuangan yang sudah ada
   (invoice, pembayaran, rekening) lewat endpoint /finance/cash dan /finance/receivables. */
(function () {
  'use strict';
  var cfg = window.JASPEST_ADMIN_CONFIG || {};
  var base = (cfg.apiBase || '').replace(/\/$/, '');
  var $ = function (id) { return document.getElementById(id); };
  var rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  var money = function (n) { return rupiah.format(Number(n) || 0).replace(/\s/g, ''); };
  var esc = function (v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var fmtDate = function (iso) {
    if (!iso) return '–';
    var d = new Date(iso + 'T00:00:00');
    return isNaN(d) ? iso : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  };
  var debounce = function (fn, ms) { var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); }; };

  function get(path, params) {
    var qs = new URLSearchParams();
    Object.keys(params || {}).forEach(function (k) { if (params[k] && params[k] !== 'all') qs.set(k, params[k]); });
    var url = base + path + (qs.toString() ? '?' + qs.toString() : '');
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (r.status === 401 || r.status === 419) { if (cfg.loginUrl) location.href = cfg.loginUrl; throw new Error('auth'); }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      });
  }

  function toast(msg) {
    var stack = $('toastStack');
    if (!stack) return;
    var el = document.createElement('div');
    el.className = 'toast toast-error';
    el.textContent = msg;
    stack.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }

  /* ---------------- Pemasukan (kas masuk) ---------------- */
  var CASH_PER_PAGE = 10;
  var cash = { account: 'all', page: 1, rows: [], truncated: false, total: 0 };

  function loadCash(silent) {
    return get('/finance/cash', { from: $('cashFrom').value, to: $('cashTo').value, account: cash.account, q: $('cashSearch').value.trim() })
      .then(renderCash)
      .catch(function (e) { if (e.message !== 'auth' && silent !== true) toast('Data penerimaan kas belum dapat dimuat. Periksa koneksi internet Anda.'); });
  }

  function renderCash(res) {
    var s = res.summary || {};
    $('cashTotalIn').textContent = money(s.total_in);
    $('cashMonthIn').textContent = money(s.month_in);
    $('cashCashIn').textContent = money(s.cash_in);
    $('cashBankIn').textContent = money(s.bank_in);

    var accounts = res.accounts || [];
    var sel = $('cashAccountFilter');
    var keep = cash.account;
    sel.innerHTML = '<option value="all">Semua akun</option>' + accounts.map(function (a) {
      return '<option value="' + esc(a.key) + '">' + esc(a.label) + '</option>';
    }).join('');
    sel.value = accounts.some(function (a) { return a.key === keep; }) ? keep : 'all';

    cash.rows = res.data || [];
    cash.truncated = !!res.truncated;
    cash.total = s.count || 0;
    renderCashPage();
  }

  function renderCashPage() {
    var rows = cash.rows;
    var last = Math.max(1, Math.ceil(rows.length / CASH_PER_PAGE));
    if (cash.page > last) cash.page = last;
    if (cash.page < 1) cash.page = 1;
    var from = (cash.page - 1) * CASH_PER_PAGE;
    var view = rows.slice(from, from + CASH_PER_PAGE);

    $('cashEmpty').hidden = rows.length > 0;
    $('cashCountLabel').textContent = cash.total + ' transaksi';
    $('cashTableCount').textContent = rows.length
      ? 'Menampilkan ' + (from + 1) + '–' + (from + view.length) + ' dari ' + rows.length + ' transaksi' + (cash.truncated ? ' terbaru (dibatasi 300)' : '')
      : 'Tidak ada transaksi';
    $('cashPageLabel').textContent = cash.page + ' / ' + last;
    $('cashPrev').disabled = cash.page <= 1;
    $('cashNext').disabled = cash.page >= last;
    $('cashRows').innerHTML = view.map(function (p) {
      return '<tr><td>' + fmtDate(p.date) + '</td>' +
        '<td><strong>' + esc(p.invoice_number || '–') + '</strong><small class="finance-subline">' + esc(p.customer || '') + '</small></td>' +
        '<td>' + esc(p.method_label) + '</td><td>' + esc(p.account_label) + '</td>' +
        '<td>' + esc(p.reference || '–') + '</td><td class="align-right"><strong>' + money(p.amount) + '</strong></td></tr>';
    }).join('');
  }


  /* ---------------- Ringkasan Keuangan: klik akun membuka riwayat Pemasukan ---------------- */
  function openAccountHistory(tr) {
    if (!tr) return;
    cash.account = tr.getAttribute('data-account');
    cash.page = 1;
    var link = document.querySelector('#financeSubmenu [data-page="cash"]');
    if (link) link.click(); else loadCash();
  }

  /* ---------------- Ekspor Excel Pemasukan ---------------- */
  function notify(msg, type) {
    var shared = window.JASPEST_ADMIN_UI && window.JASPEST_ADMIN_UI.toast;
    if (typeof shared === 'function') { shared(msg, type || 'success'); return; }
    toast(msg);
  }

  /* Unduh file Excel dari endpoint ekspor dengan filter yang sedang aktif. Dipakai Kas & Bank dan Piutang. */
  function downloadExcel(opts) {
    var btn = $(opts.button);
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    var label = btn.querySelector('span');
    var labelText = label ? label.textContent : '';
    if (label) label.textContent = 'Menyiapkan…';
    var qs = new URLSearchParams();
    Object.keys(opts.filters).forEach(function (k) {
      var v = opts.filters[k];
      if (v && v !== 'all') qs.set(k, v);
    });
    var done = function () {
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
      if (label) label.textContent = labelText;
    };
    fetch(base + opts.path + (qs.toString() ? '?' + qs.toString() : ''), {
      credentials: 'same-origin', cache: 'no-store',
      headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) {
      var type = (r.headers.get('content-type') || '').toLowerCase();
      if (r.status === 401 || r.status === 419) { if (cfg.loginUrl) location.href = cfg.loginUrl; throw new Error('Sesi login admin berakhir. Silakan login kembali.'); }
      if (r.status === 403) throw new Error('Akun ini tidak memiliki izin mengekspor laporan keuangan.');
      if (r.status === 429) throw new Error('Terlalu banyak permintaan ekspor. Tunggu sebentar lalu coba lagi.');
      if (!r.ok) throw new Error('Ekspor Excel gagal (HTTP ' + r.status + ').');
      if (type.indexOf('spreadsheetml') === -1) throw new Error('Server tidak mengirim file Excel yang valid. Periksa log Laravel.');
      return r.blob().then(function (blob) { return { blob: blob, disposition: r.headers.get('content-disposition') || '' }; });
    }).then(function (res) {
      if (!res.blob.size) throw new Error('File Excel kosong. Tidak ada data yang dapat diunduh.');
      var m = res.disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
      var name = m ? decodeURIComponent(m[1] || m[2]) : opts.fallbackName;
      var url = URL.createObjectURL(res.blob);
      var a = document.createElement('a');
      a.href = url; a.download = name;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
      notify(opts.success, 'success');
    }).then(done, function (e) { done(); notify(e.message || 'Ekspor Excel gagal.', 'error'); });
  }

  function exportCash() {
    downloadExcel({
      button: 'cashExport',
      path: '/finance/cash/export',
      filters: { from: $('cashFrom').value, to: $('cashTo').value, account: cash.account, q: $('cashSearch').value.trim() },
      fallbackName: 'JASPEST-Laporan-Kas-Bank.xlsx',
      success: 'Laporan penerimaan kas berhasil diekspor ke Excel.'
    });
  }

  function exportRecv() {
    downloadExcel({
      button: 'recvExport',
      path: '/finance/receivables/export',
      filters: { bucket: recv.bucket, q: $('recvSearch').value.trim() },
      fallbackName: 'JASPEST-Laporan-Piutang-Usaha.xlsx',
      success: 'Laporan piutang usaha berhasil diekspor ke Excel.'
    });
  }

  /* ---------------- Piutang ---------------- */
  var recv = { bucket: 'all' };

  function loadRecv(silent) {
    return get('/finance/receivables', { bucket: recv.bucket, q: $('recvSearch').value.trim() })
      .then(renderRecv)
      .catch(function (e) { if (e.message !== 'auth' && silent !== true) toast('Data piutang usaha belum dapat dimuat. Periksa koneksi internet Anda.'); });
  }

  function ageLabel(r) {
    if (r.days_overdue > 0) return '<span class="cash-chip is-late">Terlambat ' + r.days_overdue + ' hari</span>';
    return '<span class="cash-chip is-ok">Belum jatuh tempo</span>';
  }

  function renderRecv(res) {
    var s = res.summary || {};
    $('recvOutstanding').textContent = money(s.outstanding);
    $('recvCount').textContent = s.invoice_count || 0;
    $('recvCustomers').textContent = (s.customer_count || 0) + ' pelanggan';
    $('recvOverdue').textContent = money(s.overdue_total);
    $('recvOverdueCount').textContent = (s.overdue_count || 0) + ' invoice melewati jatuh tempo';

    var rows = res.data || [];
    $('recvEmpty').hidden = rows.length > 0;
    $('recvCountLabel').textContent = rows.length + ' invoice';
    $('recvRows').innerHTML = rows.map(function (r) {
      return '<tr><td><strong>' + esc(r.invoice_number || '–') + '</strong><small class="finance-subline">' + esc(r.order_reference || '') + '</small></td>' +
        '<td>' + esc(r.billing_name) + '<small class="finance-subline">' + esc(r.job_type || '') + '</small></td>' +
        '<td>' + fmtDate(r.due_date) + '</td><td class="align-right">' + money(r.total_amount) + '</td>' +
        '<td class="align-right">' + money(r.paid_amount) + '</td><td class="align-right"><strong>' + money(r.outstanding_amount) + '</strong></td>' +
        '<td>' + ageLabel(r) + '</td></tr>';
    }).join('');
  }

  /* ---------------- Event ---------------- */
  var reloadCash = debounce(loadCash, 300), reloadRecv = debounce(loadRecv, 300);

  $('cashSearch').addEventListener('input', function () { cash.page = 1; reloadCash(); });
  $('cashExport').addEventListener('click', exportCash);
  $('recvExport').addEventListener('click', exportRecv);
  ['cashFrom', 'cashTo'].forEach(function (id) { $(id).addEventListener('change', function () { cash.page = 1; loadCash(); }); });
  $('cashAccountFilter').addEventListener('change', function () { cash.account = this.value; cash.page = 1; loadCash(); });
  $('sumAccountRows').addEventListener('click', function (e) { openAccountHistory(e.target.closest('tr[data-account]')); });
  $('sumAccountRows').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openAccountHistory(e.target.closest('tr[data-account]')); } });
  $('cashPrev').addEventListener('click', function () { if (cash.page > 1) { cash.page--; renderCashPage(); } });
  $('cashNext').addEventListener('click', function () { cash.page++; renderCashPage(); });
  $('recvSearch').addEventListener('input', reloadRecv);
  $('recvBucketFilter').addEventListener('change', function () { recv.bucket = this.value; loadRecv(); });

  document.addEventListener('jaspest:page', function (e) {
    if (e.detail.name === 'cash') { cash.page = 1; loadCash(); }
    if (e.detail.name === 'receivables') loadRecv();
  });

  // Realtime: data Pemasukan dan Piutang menyegarkan diri saat halamannya tampil dan tab browser terlihat.
  function pageActive(id) { var el = $(id); return !!el && el.classList.contains('active') && !document.hidden; }
  function silentRefresh() {
    if (pageActive('page-cash')) loadCash(true);
    if (pageActive('page-receivables')) loadRecv(true);
  }
  window.addEventListener('jaspest:orders-changed', silentRefresh);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) silentRefresh(); });
  setInterval(silentRefresh, 15000);
})();

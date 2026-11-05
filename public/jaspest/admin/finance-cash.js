/* Halaman Kas & Bank dan Piutang. Memakai data keuangan yang sudah ada
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

  /* ---------------- Kas & Bank ---------------- */
  var cash = { account: 'all' };

  function loadCash() {
    return get('/finance/cash', { from: $('cashFrom').value, to: $('cashTo').value, account: cash.account, q: $('cashSearch').value.trim() })
      .then(renderCash)
      .catch(function (e) { if (e.message !== 'auth') toast('Data kas belum dapat dimuat. Coba muat ulang.'); });
  }

  function renderCash(res) {
    var s = res.summary || {};
    $('cashTotalIn').textContent = money(s.total_in);
    $('cashMonthIn').textContent = money(s.month_in);
    $('cashCashIn').textContent = money(s.cash_in);
    $('cashBankIn').textContent = money(s.bank_in);

    var accounts = res.accounts || [];
    $('cashAccountCount').textContent = accounts.length + ' akun';
    $('cashAccountsEmpty').hidden = accounts.length > 0;
    $('cashAccountRows').innerHTML = accounts.map(function (a) {
      var active = cash.account === a.key ? ' class="is-selected"' : '';
      return '<tr data-account="' + esc(a.key) + '"' + active + ' tabindex="0" style="cursor:pointer"><td><strong>' + esc(a.label) + '</strong></td><td>' + a.count + ' transaksi</td><td class="align-right"><strong>' + money(a.total) + '</strong></td></tr>';
    }).join('');

    var sel = $('cashAccountFilter');
    var keep = cash.account;
    sel.innerHTML = '<option value="all">Semua akun</option>' + accounts.map(function (a) {
      return '<option value="' + esc(a.key) + '">' + esc(a.label) + '</option>';
    }).join('');
    sel.value = accounts.some(function (a) { return a.key === keep; }) ? keep : 'all';

    var rows = res.data || [];
    $('cashEmpty').hidden = rows.length > 0;
    $('cashCountLabel').textContent = (s.count || 0) + ' transaksi';
    $('cashTableCount').textContent = rows.length ? 'Menampilkan ' + rows.length + ' transaksi' + (res.truncated ? ' terbaru (dibatasi 300)' : '') : 'Tidak ada transaksi';
    $('cashRows').innerHTML = rows.map(function (p) {
      return '<tr><td>' + fmtDate(p.date) + '</td>' +
        '<td><strong>' + esc(p.invoice_number || '–') + '</strong><br><small>' + esc(p.customer || '') + '</small></td>' +
        '<td>' + esc(p.method_label) + '</td><td>' + esc(p.account_label) + '</td>' +
        '<td>' + esc(p.reference || '–') + '</td><td class="align-right"><strong>' + money(p.amount) + '</strong></td></tr>';
    }).join('');
  }

  /* ---------------- Piutang ---------------- */
  var recv = { bucket: 'all' };
  var BUCKETS = [
    ['current', 'Belum jatuh tempo', 'is-ok'], ['d1_30', '1–30 hari', 'is-warn'], ['d31_60', '31–60 hari', 'is-warn'],
    ['d61_90', '61–90 hari', 'is-late'], ['d90_plus', '> 90 hari', 'is-late']
  ];

  function loadRecv() {
    return get('/finance/receivables', { bucket: recv.bucket, q: $('recvSearch').value.trim() })
      .then(renderRecv)
      .catch(function (e) { if (e.message !== 'auth') toast('Data piutang belum dapat dimuat. Coba muat ulang.'); });
  }

  function ageLabel(r) {
    if (r.days_overdue > 0) return '<span class="cash-chip is-late">Terlambat ' + r.days_overdue + ' hari</span>';
    return '<span class="cash-chip is-ok">Belum jatuh tempo</span>';
  }

  function renderRecv(res) {
    var s = res.summary || {}, b = res.buckets || {};
    $('recvOutstanding').textContent = money(s.outstanding);
    $('recvCount').textContent = s.invoice_count || 0;
    $('recvCustomers').textContent = (s.customer_count || 0) + ' pelanggan';
    $('recvOverdue').textContent = money(s.overdue_total);
    $('recvOverdueCount').textContent = (s.overdue_count || 0) + ' invoice melewati jatuh tempo';
    $('recvCurrent').textContent = money((b.current || {}).total);

    $('recvBuckets').innerHTML = BUCKETS.map(function (x) {
      var d = b[x[0]] || { total: 0, count: 0 };
      return '<button type="button" class="cash-aging-item ' + x[2] + (recv.bucket === x[0] ? ' is-active' : '') + '" data-bucket="' + x[0] + '"><span>' + x[1] + '</span><strong>' + money(d.total) + '</strong><small>' + d.count + ' invoice</small></button>';
    }).join('');

    var customers = res.customers || [];
    $('recvCustomerRows').innerHTML = customers.length ? customers.map(function (c) {
      return '<tr><td><strong>' + esc(c.name) + '</strong></td><td>' + c.invoice_count + ' invoice</td><td>' + (c.max_days_overdue > 0 ? c.max_days_overdue + ' hari' : '–') + '</td><td class="align-right"><strong>' + money(c.outstanding) + '</strong></td></tr>';
    }).join('') : '<tr><td colspan="4"><small>Tidak ada piutang berjalan.</small></td></tr>';

    var rows = res.data || [];
    $('recvEmpty').hidden = rows.length > 0;
    $('recvCountLabel').textContent = rows.length + ' invoice';
    $('recvRows').innerHTML = rows.map(function (r) {
      return '<tr><td><strong>' + esc(r.invoice_number || '–') + '</strong><br><small>' + esc(r.order_reference || '') + '</small></td>' +
        '<td>' + esc(r.billing_name) + '<br><small>' + esc(r.job_type || '') + '</small></td>' +
        '<td>' + fmtDate(r.due_date) + '</td><td class="align-right">' + money(r.total_amount) + '</td>' +
        '<td class="align-right">' + money(r.paid_amount) + '</td><td class="align-right"><strong>' + money(r.outstanding_amount) + '</strong></td>' +
        '<td>' + ageLabel(r) + '</td></tr>';
    }).join('');
  }

  /* ---------------- Event ---------------- */
  var reloadCash = debounce(loadCash, 300), reloadRecv = debounce(loadRecv, 300);

  $('cashRefresh').addEventListener('click', loadCash);
  $('receivablesRefresh').addEventListener('click', loadRecv);
  $('cashSearch').addEventListener('input', reloadCash);
  ['cashFrom', 'cashTo'].forEach(function (id) { $(id).addEventListener('change', loadCash); });
  $('cashAccountFilter').addEventListener('change', function () { cash.account = this.value; loadCash(); });
  $('cashAccountRows').addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-account]');
    if (!tr) return;
    cash.account = cash.account === tr.getAttribute('data-account') ? 'all' : tr.getAttribute('data-account');
    loadCash();
  });
  $('recvSearch').addEventListener('input', reloadRecv);
  $('recvBucketFilter').addEventListener('change', function () { recv.bucket = this.value; loadRecv(); });
  $('recvBuckets').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bucket]');
    if (!btn) return;
    recv.bucket = recv.bucket === btn.getAttribute('data-bucket') ? 'all' : btn.getAttribute('data-bucket');
    $('recvBucketFilter').value = recv.bucket;
    loadRecv();
  });

  document.addEventListener('jaspest:page', function (e) {
    if (e.detail.name === 'cash') loadCash();
    if (e.detail.name === 'receivables') loadRecv();
  });
})();

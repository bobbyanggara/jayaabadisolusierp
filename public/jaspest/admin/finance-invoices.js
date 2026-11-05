/* Halaman Invoice: daftar, filter, cetak, ekspor, dan pencatatan pembayaran.
   Memakai endpoint keuangan yang sudah ada (/finance/invoices, /summary, /references, /export). */
(function () {
  'use strict';
  var cfg = window.JASPEST_ADMIN_CONFIG || {};
  var base = (cfg.apiBase || '').replace(/\/$/, '');
  var $ = function (id) { return document.getElementById(id); };
  if (!$('page-invoices')) return;

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
  var todayIso = function () { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
  var debounce = function (fn, ms) { var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); }; };
  var csrf = function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; };
  var METHOD = { transfer: 'Transfer', cash: 'Tunai', bg: 'BG', cheque_giro: 'Cek / Giro', other: 'Lainnya' };
  var STATUS = { unpaid: ['Belum dibayar', 'is-late'], partial: ['Dibayar sebagian', 'is-warn'], paid: ['Lunas', 'is-ok'] };

  function toast(msg, ok) {
    var stack = $('toastStack');
    if (!stack) return;
    var el = document.createElement('div');
    el.className = 'toast ' + (ok ? 'toast-success' : 'toast-error');
    el.textContent = msg;
    stack.appendChild(el);
    setTimeout(function () { el.remove(); }, 4500);
  }
  function request(path, opts) {
    opts = opts || {};
    opts.credentials = 'same-origin';
    opts.headers = Object.assign({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
    return fetch(base + path, opts).then(function (r) {
      if (r.status === 401 || r.status === 419) { if (cfg.loginUrl) location.href = cfg.loginUrl; throw new Error('auth'); }
      return r.json().catch(function () { return {}; }).then(function (body) {
        if (!r.ok) { var err = new Error(body.message || 'HTTP ' + r.status); err.body = body; throw err; }
        return body;
      });
    });
  }
  function query() {
    var qs = new URLSearchParams();
    var map = { status: $('invStatus').value, q: $('invSearch').value.trim(), from: $('invFrom').value, to: $('invTo').value };
    Object.keys(map).forEach(function (k) { if (map[k] && map[k] !== 'all') qs.set(k, map[k]); });
    return qs;
  }

  var state = { page: 1, last: 1, open: {}, invoices: {} };

  /* ---------- Daftar ---------- */
  function load() {
    var qs = query(); qs.set('page', state.page);
    return request('/finance/invoices?' + qs.toString()).then(function (res) {
      state.last = (res.meta && res.meta.last_page) || 1;
      state.page = Math.min(state.page, state.last);
      render(res.data || [], res.meta || {});
    }).catch(function (e) { if (e.message !== 'auth') toast('Data invoice belum dapat dimuat. Coba muat ulang.'); });
  }
  function loadStats() {
    // Hitung dari seluruh invoice (tanpa filter) agar ringkasan tidak ikut berubah saat memfilter.
    var all = { unpaid: 0, partial: 0, overdue: 0 };
    return Promise.all([
      request('/finance/summary'),
      request('/finance/invoices?status=unpaid'), request('/finance/invoices?status=partial')
    ]).then(function (r) {
      var s = r[0];
      $('invStatTotal').textContent = money(s.total_invoiced);
      $('invStatUnpaid').textContent = (r[1].meta && r[1].meta.total) || 0;
      $('invStatPartial').textContent = (r[2].meta && r[2].meta.total) || 0;
      $('invStatOverdue').textContent = s.overdue_count || 0;
      var n = s.unbilled_order_count || 0;
      $('invUnbilled').hidden = n === 0;
      $('invUnbilledText').textContent = n + ' order belum ditagih';
      return all;
    }).catch(function () {});
  }
  function dueChip(i) {
    if (i.status === 'paid') return '';
    if (i.is_overdue) return '<span class="cash-chip is-late">Lewat tempo</span>';
    return '';
  }
  function render(rows, meta) {
    state.invoices = {};
    $('invEmpty').hidden = rows.length > 0;
    $('invCountLabel').textContent = (meta.total || rows.length) + ' invoice';
    $('invTableCount').textContent = rows.length ? 'Halaman ' + state.page + ' dari ' + state.last : 'Tidak ada invoice';
    $('invPageLabel').textContent = state.page + ' / ' + state.last;
    $('invPrev').disabled = state.page <= 1;
    $('invNext').disabled = state.page >= state.last;
    $('invRows').innerHTML = rows.map(function (i) {
      state.invoices[i.id] = i;
      var st = STATUS[i.status] || [i.status, ''];
      var canPay = i.outstanding_amount > 0;
      var html = '<tr class="inv-row" data-id="' + i.id + '" tabindex="0">' +
        '<td><strong>' + esc(i.invoice_number || '–') + '</strong><br><small>' + esc(i.order_reference || '') + '</small></td>' +
        '<td>' + esc(i.billing_name) + '<br><small>' + esc(i.job_type || '') + '</small></td>' +
        '<td>' + fmtDate(i.invoice_date) + '<br><small>Tempo: ' + fmtDate(i.due_date) + '</small> ' + dueChip(i) + '</td>' +
        '<td class="align-right">' + money(i.total_amount) + '</td>' +
        '<td class="align-right"><strong>' + money(i.outstanding_amount) + '</strong></td>' +
        '<td><span class="cash-chip ' + st[1] + '">' + st[0] + '</span></td>' +
        '<td class="align-right inv-actions"><a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="' + esc(base + '/finance/invoices/' + i.id + '/print') + '">Cetak</a> ' +
        (canPay ? '<button type="button" class="btn btn-primary btn-sm" data-pay="' + i.id + '">Catat Bayar</button>' : '') + '</td></tr>';
      if (state.open[i.id]) html += detail(i);
      return html;
    }).join('');
  }
  function detail(i) {
    var pays = (i.payments || []);
    var body = pays.length ? pays.map(function (p) {
      var acc = p.receiving_account_name ? (p.receiving_account_code + ' · ' + p.receiving_account_name) : [p.received_bank_name, p.received_bank_account_number].filter(Boolean).join(' ');
      return '<li><span>' + fmtDate(p.date) + '</span><span>' + esc(METHOD[p.method] || p.method) + (acc ? ' → ' + esc(acc) : '') + (p.reference ? ' · ' + esc(p.reference) : '') + '</span><strong>' + money(p.amount) + '</strong></li>';
    }).join('') : '<li class="inv-none">Belum ada pembayaran tercatat.</li>';
    return '<tr class="inv-detail"><td colspan="7"><div class="inv-detail-box"><div class="inv-detail-head"><span>Riwayat pembayaran</span><span>Terbayar ' + money(i.paid_amount) + ' dari ' + money(i.total_amount) + '</span></div><ul>' + body + '</ul></div></td></tr>';
  }

  /* ---------- Modal pembayaran ---------- */
  var refs = null, current = null;
  function loadRefs() {
    if (refs) return Promise.resolve(refs);
    return request('/finance/references').then(function (r) { refs = r; return r; });
  }
  function syncMethod() {
    var m = $('invPayMethod').value;
    $('invPayCashField').hidden = m !== 'cash';
    $('invPayBankField').hidden = m === 'cash';
  }
  function openPay(id) {
    current = state.invoices[id];
    if (!current) return;
    loadRefs().then(function (r) {
      var banks = (r.bank_accounts || []).filter(function (b) { return b.is_active !== false; });
      $('invPayBank').innerHTML = banks.map(function (b) {
        return '<option value="' + b.id + '">' + esc(b.bank_name + ' ' + b.account_number + ' · ' + b.account_holder) + '</option>';
      }).join('');
      if (current.bank_account_id) $('invPayBank').value = String(current.bank_account_id);
      $('invPayCash').innerHTML = (r.cash_bank_accounts || []).filter(function (c) { return c.account_code === '111.01' || c.account_code === '111.02'; })
        .map(function (c) { return '<option value="' + esc(c.account_code) + '">' + esc(c.account_code + ' · ' + c.account_name) + '</option>'; }).join('');
      var f = $('invPayForm');
      f.reset();
      f.payment_date.value = todayIso();
      f.amount.value = current.outstanding_amount;
      f.amount.max = current.outstanding_amount;
      $('invPayHint').textContent = 'Sisa tagihan ' + money(current.outstanding_amount);
      $('invPayInfo').textContent = (current.invoice_number || '') + ' · ' + current.billing_name;
      $('invPayError').hidden = true;
      syncMethod();
      $('invPayModal').hidden = false;
      f.amount.focus();
    }).catch(function (e) { if (e.message !== 'auth') toast('Data rekening belum dapat dimuat.'); });
  }
  function closePay() { $('invPayModal').hidden = true; current = null; }

  function submitPay(ev) {
    ev.preventDefault();
    if (!current) return;
    var f = $('invPayForm'), err = $('invPayError');
    var body = { payment_date: f.payment_date.value, amount: f.amount.value, method: f.method.value, reference: f.reference.value.trim() || null, notes: f.notes.value.trim() || null };
    if (body.method === 'cash') body.receiving_account_code = f.receiving_account_code.value;
    else body.bank_account_id = f.bank_account_id.value ? Number(f.bank_account_id.value) : null;
    if (!body.payment_date || !(Number(body.amount) > 0)) { err.textContent = 'Tanggal dan jumlah pembayaran wajib diisi.'; err.hidden = false; return; }
    if (Number(body.amount) > current.outstanding_amount + 0.001) { err.textContent = 'Jumlah melebihi sisa tagihan ' + money(current.outstanding_amount) + '.'; err.hidden = false; return; }
    var btn = $('invPaySubmit'); btn.disabled = true;
    request('/finance/invoices/' + current.id + '/payments', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) })
      .then(function () { closePay(); toast('Pembayaran berhasil dicatat.', true); return Promise.all([load(), loadStats()]); })
      .catch(function (e) {
        if (e.message === 'auth') return;
        var errors = e.body && e.body.errors; var first = errors && Object.keys(errors).length ? errors[Object.keys(errors)[0]][0] : e.message;
        err.textContent = first || 'Pembayaran gagal disimpan.'; err.hidden = false;
      }).then(function () { btn.disabled = false; });
  }

  /* ---------- Event ---------- */
  var reload = debounce(function () { state.page = 1; load(); }, 300);
  $('invSearch').addEventListener('input', reload);
  ['invStatus', 'invFrom', 'invTo'].forEach(function (id) { $(id).addEventListener('change', function () { state.page = 1; load(); }); });
  $('invRefresh').addEventListener('click', function () { load(); loadStats(); });
  $('invPrev').addEventListener('click', function () { if (state.page > 1) { state.page--; load(); } });
  $('invNext').addEventListener('click', function () { if (state.page < state.last) { state.page++; load(); } });
  $('invExport').addEventListener('click', function () {
    var qs = query(); qs.delete('from'); qs.delete('to');
    window.open(base + '/finance/export' + (qs.toString() ? '?' + qs.toString() : ''), '_blank', 'noopener');
  });
  $('invRows').addEventListener('click', function (e) {
    var pay = e.target.closest('[data-pay]');
    if (pay) { openPay(Number(pay.getAttribute('data-pay'))); return; }
    if (e.target.closest('a')) return;
    var row = e.target.closest('tr.inv-row');
    if (!row) return;
    var id = row.getAttribute('data-id');
    state.open[id] = !state.open[id];
    var next = row.nextElementSibling;
    if (next && next.classList.contains('inv-detail')) next.remove();
    else if (state.open[id]) row.insertAdjacentHTML('afterend', detail(state.invoices[id]));
  });
  $('invPayMethod').addEventListener('change', syncMethod);
  $('invPayForm').addEventListener('submit', submitPay);
  $('invPayModal').addEventListener('click', function (e) { if (e.target === this || e.target.closest('[data-inv-close]')) closePay(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('invPayModal').hidden) closePay(); });
  document.addEventListener('jaspest:page', function (e) { if (e.detail.name === 'invoices') { load(); loadStats(); } });
})();

/* Ringkasan Keuangan (omzet, pemasukan, pengeluaran, Year-on-Year) dan halaman Pengeluaran (pencatatan pengeluaran).
   Filter Rekening/Tahun ada di dua halaman (Ringkasan Keuangan dan Pengeluaran) dan selalu sinkron.
   Endpoint: /finance/cash/overview, /finance/expenses, /finance/bank-accounts. */
(function () {
  'use strict';
  var cfg = window.JASPEST_ADMIN_CONFIG || {};
  if (!cfg.canAccessFinance || !cfg.apiBase || !document.getElementById('cashExpensePanel') || !document.getElementById('cashInsightBank')) return;

  var base = String(cfg.apiBase).replace(/\/$/, '') + '/finance';
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.content : '';
  var $ = function (id) { return document.getElementById(id); };

  var MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var PAGE_SIZE = 10;

  var rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  var money = function (n) { return rupiah.format(Number(n) || 0).replace(/\s/g, ''); };
  var compactFmt = null;
  try { compactFmt = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }); } catch (e) { compactFmt = null; }
  var compact = function (n) {
    if (n === 0) return '0';
    if (compactFmt) return compactFmt.format(n);
    return n >= 1e6 ? (n / 1e6).toFixed(1).replace('.', ',') + ' jt' : String(Math.round(n));
  };
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
  var todayJakarta = function () {
    try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()); }
    catch (e) { return new Date().toISOString().slice(0, 10); }
  };
  var pct = function (n) {
    if (n == null) return '–';
    var abs = Math.abs(n).toFixed(1).replace('.', ',');
    return (n > 0 ? '▲ ' : n < 0 ? '▼ ' : '') + abs + '%';
  };

  var BANK_SELECTS = ['cashInsightBank', 'cashExpenseBank'];
  var YEAR_SELECTS = ['cashInsightYear', 'cashExpenseYear'];
  var eachSel = function (ids, fn) { ids.forEach(function (id) { var el = $(id); if (el) fn(el); }); };

  var state = {
    bank: 'all',
    year: Number(todayJakarta().slice(0, 4)),
    years: [],
    banks: [],
    overview: null,
    expenses: [],
    expensePage: 1,
    lastRes: null,
    editing: null,
    loadedOnce: false
  };

  /* ---------------- Util jaringan & notifikasi ---------------- */
  function notify(msg, type) {
    var shared = window.JASPEST_ADMIN_UI && window.JASPEST_ADMIN_UI.toast;
    if (typeof shared === 'function') { shared(msg, type || 'success'); return; }
    var el = $('financeFeedback');
    if (el) { el.hidden = false; el.dataset.type = type || 'success'; el.textContent = msg; }
  }

  function request(path, options) {
    options = options || {};
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    var body = options.body;
    if (body && typeof body !== 'string') { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
    return fetch(base + path, { method: options.method || 'GET', headers: headers, body: body, credentials: 'same-origin' })
      .catch(function () { throw new Error('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'); })
      .then(function (r) {
        var isJson = (r.headers.get('content-type') || '').indexOf('json') !== -1;
        return (isJson ? r.json().catch(function () { return null; }) : Promise.resolve(null)).then(function (payload) {
          if (!r.ok) {
            if (r.status === 401 || r.status === 419) throw new Error('Sesi login berakhir. Silakan masuk kembali.');
            var first = payload && payload.errors ? Object.keys(payload.errors).map(function (k) { return payload.errors[k][0]; })[0] : null;
            throw new Error(first || (payload && payload.message) || 'Permintaan gagal diproses.');
          }
          return payload;
        });
      });
  }

  function query(params) {
    var qs = new URLSearchParams();
    Object.keys(params).forEach(function (k) {
      var v = params[k];
      if (v !== '' && v != null && v !== 'all') qs.set(k, v);
    });
    var s = qs.toString();
    return s ? '?' + s : '';
  }

  /* ---------------- Rekening Master Data ---------------- */
  function bankLabel(b) {
    return b.bank_name + ' ' + b.account_number + ' · ' + b.account_holder + (b.is_active ? '' : ' (nonaktif)');
  }

  function loadBanks() {
    return request('/bank-accounts').then(function (res) {
      state.banks = (res && res.data) || [];
      var keep = state.bank;
      var html = '<option value="all">Semua rekening &amp; kas</option><option value="cash">Kas tunai (Kas Besar / Kas Kecil)</option>' +
        state.banks.map(function (b) { return '<option value="' + Number(b.id) + '">' + esc(bankLabel(b)) + '</option>'; }).join('');
      var valid = keep === 'all' || keep === 'cash' || state.banks.some(function (b) { return String(b.id) === String(keep); });
      state.bank = valid ? keep : 'all';
      eachSel(BANK_SELECTS, function (sel) { sel.innerHTML = html; sel.value = state.bank; });
    });
  }

  function fillExpenseBankSelect(selectedId) {
    var sel = $('cashExpenseFormBank');
    var rows = state.banks.filter(function (b) { return b.is_active || String(b.id) === String(selectedId); });
    sel.innerHTML = '<option value="">' + (rows.length ? 'Pilih bank dan nomor rekening' : 'Belum ada rekening aktif. Tambahkan di Master Data › Rekening Bank') + '</option>' +
      rows.map(function (b) { return '<option value="' + Number(b.id) + '">' + esc(bankLabel(b)) + '</option>'; }).join('');
    sel.value = selectedId ? String(selectedId) : '';
  }

  /* ---------------- Ringkasan (kartu) ---------------- */
  function deltaHtml(change, prevLabel, upIsGood) {
    if (change == null) return '<span>Belum ada pembanding pada ' + esc(prevLabel) + '</span>';
    var good = change === 0 ? null : (change > 0) === upIsGood;
    var cls = good === null ? '' : (good ? ' is-good' : ' is-bad');
    return '<span class="cash-delta' + cls + '">' + pct(change) + '</span> dibanding ' + esc(prevLabel);
  }

  function renderStats(res) {
    var m = res.month || {}, s = res.summary || {}, r = res.receivable || {};

    $('cashYearOmzetLabel').textContent = 'Pendapatan ' + res.year;
    $('cashYearOmzet').textContent = money(s.omzet_year);
    $('cashYearOmzetHint').innerHTML = esc(m.label) + ': ' + money(m.omzet) + ' · ' + (s.yoy_percent == null
      ? 'belum ada pembanding ' + res.previous_year
      : '<span class="cash-delta ' + (s.yoy_percent >= 0 ? 'is-good' : 'is-bad') + '">' + pct(s.yoy_percent) + '</span> YoY');

    $('finExpenseYearLabel').textContent = 'Pengeluaran ' + res.year;
    $('finExpenseYear').textContent = money(s.expense_year);
    $('finExpenseYearHint').textContent = (m.label || '') + ': ' + money(m.expense) + (s.omzet_year > 0
      ? ' · ' + Math.round(s.expense_year / s.omzet_year * 100) + '% dari pendapatan'
      : '');

    $('finNetYearLabel').textContent = 'Arus Kas Bersih ' + res.year;
    var net = $('finNetYear');
    net.textContent = money(s.net_year);
    net.classList.toggle('is-negative', Number(s.net_year) < 0);

    $('finOutstanding').textContent = money(r.outstanding);
    $('finOutstandingHint').textContent = (r.outstanding_count || 0) + ' invoice belum lunas' +
      (r.overdue_count ? ' · ' + money(r.overdue) + ' jatuh tempo' : '');
    $('finOutstandingCard').classList.toggle('finance-stat-alert', (r.overdue_count || 0) > 0);
  }

  /* ---------------- Grafik YoY (SVG) ---------------- */
  function niceStep(raw) {
    if (raw <= 0) return 1;
    var exp = Math.pow(10, Math.floor(Math.log10(raw)));
    var f = raw / exp;
    var nf = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return nf * exp;
  }

  function renderChart(res) {
    var host = $('cashYoyChart');
    var months = res.monthly || [];
    var peak = months.reduce(function (a, x) { return Math.max(a, x.omzet, x.omzet_prev, x.expense || 0); }, 0);
    var s = res.summary || {};

    $('cashYoySubtitle').textContent = 'Pendapatan bulanan ' + res.year + ' dibandingkan dengan ' + res.previous_year + ', dengan pengeluaran ' + res.year + ' sebagai garis merah.';
    $('cashLegendCur').textContent = String(res.year);
    $('cashLegendPrev').textContent = String(res.previous_year);
    $('cashLegendExp').textContent = String(res.year);

    var badge = $('cashYoyBadge');
    badge.className = 'cash-yoy-badge';
    if (s.yoy_percent == null) {
      badge.textContent = s.comparable_months > 0 ? 'Belum ada pendapatan ' + res.previous_year + ' sebagai pembanding' : 'Belum ada periode pembanding';
    } else {
      badge.classList.add(s.yoy_percent >= 0 ? 'is-good' : 'is-bad');
      badge.textContent = 'YoY ' + pct(s.yoy_percent) + ' · ' + s.comparable_label + ' ' + res.year + ' vs ' + res.previous_year;
    }

    $('cashYoyEmpty').hidden = peak > 0;
    host.parentNode.hidden = peak <= 0;
    $('cashYoyChart').closest('.panel').querySelector('.cash-chart-legend').hidden = peak <= 0;
    if (peak <= 0) { host.innerHTML = ''; return; }

    var W = 720, H = 300, pl = 58, pr = 16, pt = 16, pb = 32;
    var iw = W - pl - pr, ih = H - pt - pb;
    var step = niceStep(peak / 4), yMax = step * 4;
    var gw = iw / 12;
    var upTo = s.comparable_months > 0 ? s.comparable_months : 12;
    var curMonth = s.comparable_months > 0 && s.comparable_months < 12 ? s.comparable_months : 0;
    var y = function (v) { return pt + ih - (v / yMax) * ih; };
    var base = pt + ih;
    var xm = function (m) { return pl + gw * (m - 1) + gw / 2; };

    // Kurva monoton (tidak melampaui data) agar garis halus tanpa "gelombang" palsu.
    function smooth(pts) {
      var n = pts.length, i;
      if (!n) return '';
      var d = 'M' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1);
      if (n === 1) return d;
      var dx = [], m = [], t = [];
      for (i = 0; i < n - 1; i++) { dx[i] = pts[i + 1].x - pts[i].x; m[i] = (pts[i + 1].y - pts[i].y) / dx[i]; }
      t[0] = m[0]; t[n - 1] = m[n - 2];
      for (i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
      for (i = 0; i < n - 1; i++) {
        if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
        var a = t[i] / m[i], b = t[i + 1] / m[i], h = a * a + b * b;
        if (h > 9) { var k = 3 / Math.sqrt(h); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
      }
      for (i = 0; i < n - 1; i++) {
        d += 'C' + (pts[i].x + dx[i] / 3).toFixed(1) + ' ' + (pts[i].y + t[i] * dx[i] / 3).toFixed(1) + ' ' +
          (pts[i + 1].x - dx[i] / 3).toFixed(1) + ' ' + (pts[i + 1].y - t[i + 1] * dx[i] / 3).toFixed(1) + ' ' +
          pts[i + 1].x.toFixed(1) + ' ' + pts[i + 1].y.toFixed(1);
      }
      return d;
    }

    var curPts = [], prevPts = [], expPts = [];
    months.forEach(function (m) {
      prevPts.push({ x: xm(m.month), y: y(m.omzet_prev || 0), v: m.omzet_prev || 0 });
      if (m.month <= upTo) {
        curPts.push({ x: xm(m.month), y: y(m.omzet || 0), v: m.omzet || 0 });
        expPts.push({ x: xm(m.month), y: y(m.expense || 0), v: m.expense || 0 });
      }
    });

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" focusable="false" aria-hidden="true">' +
      '<defs><linearGradient id="ccAreaGrad" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" style="stop-color:var(--brand);stop-opacity:.34"/><stop offset="100%" style="stop-color:var(--brand);stop-opacity:.02"/></linearGradient></defs>';
    for (var i = 0; i <= 4; i++) {
      var gy = y(step * i);
      svg += '<line class="cc-grid" x1="' + pl + '" x2="' + (W - pr) + '" y1="' + gy + '" y2="' + gy + '"/>' +
        '<text class="cc-axis" x="' + (pl - 8) + '" y="' + (gy + 4) + '" text-anchor="end">' + esc(compact(step * i)) + '</text>';
    }
    months.forEach(function (m, idx) {
      svg += '<rect class="cc-hover" data-i="' + idx + '" tabindex="0" x="' + (pl + gw * idx + 1) + '" y="' + pt + '" width="' + (gw - 2) + '" height="' + ih + '" rx="6"/>';
      svg += '<text class="cc-axis' + (curMonth === idx + 1 ? ' cc-now' : '') + '" x="' + xm(m.month) + '" y="' + (H - 10) + '" text-anchor="middle">' + esc(m.label) + '</text>';
    });

    // Omzet tahun lalu: garis putus-putus.
    svg += '<path class="cc-line-prev" d="' + smooth(prevPts) + '" pointer-events="none"/>';
    // Omzet tahun ini: area bergradasi + garis.
    if (curPts.length) {
      var cd = smooth(curPts);
      svg += '<path class="cc-area" d="' + cd + ' L' + curPts[curPts.length - 1].x.toFixed(1) + ' ' + base + ' L' + curPts[0].x.toFixed(1) + ' ' + base + ' Z" pointer-events="none"/>';
      svg += '<path class="cc-line-cur" d="' + cd + '" pointer-events="none"/>';
    }
    // Pengeluaran: garis merah.
    if (expPts.some(function (p) { return p.v > 0; })) {
      svg += '<path class="cc-exp" d="' + smooth(expPts) + '" pointer-events="none"/>';
      expPts.forEach(function (p) { if (p.v > 0) svg += '<circle class="cc-exp-dot" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="3.2" pointer-events="none"/>'; });
    }
    curPts.forEach(function (p, k) {
      if (p.v > 0) svg += '<circle class="cc-cur-dot' + (k === curPts.length - 1 ? ' is-last' : '') + '" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="' + (k === curPts.length - 1 ? 4.6 : 3.4) + '" pointer-events="none"/>';
    });
    svg += '</svg><div class="cash-chart-tip" id="cashChartTip" hidden></div>';
    host.innerHTML = svg;
    host.setAttribute('aria-label', 'Grafik pendapatan bulanan ' + res.year + ' dibandingkan ' + res.previous_year + ', beserta pengeluaran ' + res.year + '. Arahkan kursor ke tiap bulan untuk melihat rincian.');
  }

  function showTip(target) {
    var res = state.overview, host = $('cashYoyChart'), tip = $('cashChartTip');
    if (!res || !tip || !target) return;
    var m = res.monthly[Number(target.getAttribute('data-i'))];
    if (!m) return;
    var change = m.omzet_prev > 0 ? ((m.omzet - m.omzet_prev) / m.omzet_prev) * 100 : null;
    tip.innerHTML = '<strong>' + MONTHS_LONG[m.month - 1] + '</strong>' +
      '<div><i>' + res.year + '</i><span>' + money(m.omzet) + '</span></div>' +
      '<div><i>' + res.previous_year + '</i><span>' + money(m.omzet_prev) + '</span></div>' +
      '<div><i>YoY</i><span>' + pct(change) + '</span></div>' +
      '<div class="cc-tip-sep"><i>Pengeluaran ' + res.year + '</i><span>' + money(m.expense) + '</span></div>' +
      '<div><i>Arus kas bersih</i><span>' + money(m.net) + '</span></div>';
    tip.hidden = false;
    var hostBox = host.getBoundingClientRect(), box = target.getBoundingClientRect();
    var left = box.left - hostBox.left + box.width / 2 - tip.offsetWidth / 2;
    left = Math.max(0, Math.min(left, hostBox.width - tip.offsetWidth));
    tip.style.left = left + 'px';
    tip.style.top = Math.max(0, box.top - hostBox.top + 8) + 'px';
    Array.prototype.forEach.call(host.querySelectorAll('.cc-hover.is-active'), function (el) { el.classList.remove('is-active'); });
    target.classList.add('is-active');
  }

  function hideTip() {
    var tip = $('cashChartTip');
    if (tip) tip.hidden = true;
    Array.prototype.forEach.call($('cashYoyChart').querySelectorAll('.cc-hover.is-active'), function (el) { el.classList.remove('is-active'); });
  }

  function renderYearSelect(res) {
    var years = (res.years && res.years.length) ? res.years : [res.year];
    var html = years.map(function (y) { return '<option value="' + y + '">' + y + '</option>'; }).join('');
    eachSel(YEAR_SELECTS, function (sel) { sel.innerHTML = html; sel.value = String(res.year); });
    state.year = res.year;
  }

  function renderAccounts(res) {
    var accounts = res.accounts || [];
    $('sumAccountSubtitle').textContent = 'Penerimaan, pengeluaran, dan arus kas bersih per akun kas dan rekening pada tahun ' + res.year + '. Pilih satu akun untuk melihat riwayat penerimaannya.';
    $('sumAccountCount').textContent = accounts.length + ' akun';
    $('sumAccountsEmpty').hidden = accounts.length > 0;
    $('sumAccountRows').innerHTML = accounts.map(function (a) {
      var neg = Number(a.net) < 0 ? ' class="align-right is-negative-text"' : ' class="align-right"';
      return '<tr data-account="' + esc(a.key) + '" tabindex="0" style="cursor:pointer"><td><strong>' + esc(a.label) + '</strong></td>' +
        '<td class="align-right">' + money(a.income) + '</td><td class="align-right">' + money(a.expense) + '</td>' +
        '<td' + neg + '><strong>' + money(a.net) + '</strong></td></tr>';
    }).join('');
  }

  function renderOverview(res) {
    state.overview = res;
    renderYearSelect(res);
    renderStats(res);
    renderChart(res);
    renderAccounts(res);
  }

  /* ---------------- Pengeluaran ---------------- */
  function ensureMonthOptions() {
    var sel = $('cashExpenseMonth');
    if (sel.options.length > 1) return;
    sel.innerHTML = '<option value="all">Semua bulan</option>' + MONTHS_LONG.map(function (n, i) { return '<option value="' + (i + 1) + '">' + n + '</option>'; }).join('');
  }

  function renderExpenses(res) {
    state.lastRes = res;
    var rows = res.data || [];
    state.expenses = rows;
    var pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    if (state.expensePage > pages) state.expensePage = pages;
    var slice = rows.slice((state.expensePage - 1) * PAGE_SIZE, state.expensePage * PAGE_SIZE);
    var summary = res.summary || {};

    $('cashExpenseTotal').textContent = money(summary.total);
    $('cashExpenseCountLabel').textContent = (summary.count || 0) + ' pengeluaran';
    $('cashExpenseCats').innerHTML = (res.by_category || []).map(function (c) {
      return '<span class="cash-cat">' + esc(c.label) + '<small>' + money(c.total) + '</small></span>';
    }).join('');
    $('cashExpenseEmpty').hidden = rows.length > 0;
    $('cashExpenseTableCount').textContent = rows.length
      ? 'Menampilkan ' + ((state.expensePage - 1) * PAGE_SIZE + 1) + '–' + ((state.expensePage - 1) * PAGE_SIZE + slice.length) + ' dari ' + (summary.count || rows.length) + (res.truncated ? ' (300 terbaru)' : '')
      : 'Tidak ada pengeluaran';
    $('cashExpensePageLabel').textContent = state.expensePage + ' / ' + pages;
    $('cashExpensePrev').disabled = state.expensePage <= 1;
    $('cashExpenseNext').disabled = state.expensePage >= pages;

    $('cashExpenseRows').innerHTML = slice.map(function (x) {
      return '<tr><td>' + fmtDate(x.expense_date) + '</td>' +
        '<td><strong>' + esc(x.description) + '</strong>' + (x.payee ? '<small class="finance-subline">' + esc(x.payee) + '</small>' : '') + '</td>' +
        '<td><span class="cash-cat">' + esc(x.category_label) + '</span></td>' +
        '<td>' + esc(x.method_label) + '<small class="finance-subline">' + esc(x.account_label) + '</small></td>' +
        '<td class="align-right"><strong>' + money(x.amount) + '</strong></td>' +
        '<td class="align-right"><div class="finance-row-actions"><button type="button" class="btn btn-outline btn-sm" data-expense-edit="' + Number(x.id) + '">Ubah</button><button type="button" class="btn btn-danger btn-sm" data-expense-delete="' + Number(x.id) + '">Hapus</button></div></td></tr>';
    }).join('');
  }

  /* ---------------- Muat data ---------------- */
  function failed(silent) {
    return function (e) { if (silent !== true) notify(e && e.message ? e.message : 'Data belum dapat dimuat.', 'error'); };
  }

  function loadOverview(silent) {
    return request('/cash/overview' + query({ year: state.year, bank: state.bank })).then(renderOverview).catch(failed(silent));
  }

  function loadExpenses(silent) {
    return request('/expenses' + query({
      year: state.year, bank: state.bank, month: $('cashExpenseMonth').value,
      category: $('cashExpenseCategory').value, q: $('cashExpenseSearch').value.trim()
    })).then(renderExpenses).catch(failed(silent));
  }

  function reload(silent) {
    return Promise.all([loadOverview(silent), loadExpenses(silent)]);
  }

  function open() {
    loadBanks().catch(failed(false)).then(function () { return reload(false); }).then(function () { state.loadedOnce = true; });
  }

  /* ---------------- Modal pengeluaran ---------------- */
  function applyMethodRules() {
    var cash = $('cashExpenseFormMethod').value === 'cash';
    $('cashExpenseBankField').hidden = cash;
    $('cashExpenseCashField').hidden = !cash;
    $('cashExpenseFormBank').required = !cash;
    $('cashExpenseFormCash').required = cash;
  }

  function openModal(expense) {
    var form = $('cashExpenseForm');
    state.editing = expense || null;
    form.reset();
    var els = form.elements;
    els.expense_date.value = expense ? expense.expense_date : todayJakarta();
    els.amount.value = expense ? String(expense.amount) : '';
    els.category.value = expense ? expense.category : '';
    els.method.value = expense ? expense.method : 'transfer';
    els.description.value = expense ? expense.description : '';
    els.payee.value = expense ? (expense.payee || '') : '';
    els.notes.value = expense ? (expense.notes || '') : '';
    fillExpenseBankSelect(expense && expense.method === 'transfer' ? expense.bank_account_id : (state.bank !== 'all' && state.bank !== 'cash' ? state.bank : ''));
    els.cash_account_code.value = expense && expense.cash_account_code ? expense.cash_account_code : '';
    if (!expense && state.bank === 'cash') els.method.value = 'cash';
    applyMethodRules();
    $('cashExpenseModalTitle').textContent = expense ? 'Ubah Pengeluaran' : 'Catat Pengeluaran';
    $('cashExpenseSubmit').textContent = expense ? 'Simpan Perubahan' : 'Simpan Pengeluaran';
    $('cashExpenseModal').hidden = false;
    document.body.style.overflow = 'hidden';
    setTimeout(function () { try { els.expense_date.focus(); } catch (e) { /* abaikan */ } }, 30);
  }

  function saveExpense(event) {
    event.preventDefault();
    var form = event.currentTarget, btn = $('cashExpenseSubmit');
    if (!form.reportValidity()) return;
    var els = form.elements, method = els.method.value;
    var payload = {
      expense_date: els.expense_date.value,
      amount: els.amount.value,
      category: els.category.value,
      method: method,
      description: els.description.value.trim(),
      payee: els.payee.value.trim(),
      notes: els.notes.value.trim(),
      bank_account_id: method === 'transfer' ? (els.bank_account_id.value || null) : null,
      cash_account_code: method === 'cash' ? (els.cash_account_code.value || null) : null
    };
    var editing = state.editing;
    btn.disabled = true;
    request(editing ? '/expenses/' + encodeURIComponent(editing.id) : '/expenses', { method: editing ? 'PUT' : 'POST', body: payload })
      .then(function (res) {
        closeModal();
        notify((res && res.message) || 'Pengeluaran berhasil disimpan.', 'success');
        // Tampilkan tahun transaksi bila berbeda dari tahun yang sedang dilihat.
        var y = Number(String(payload.expense_date).slice(0, 4));
        if (y && y !== state.year) state.year = y;
        return reload(true);
      })
      .catch(function (e) { notify(e.message, 'error'); })
      .then(function () { btn.disabled = false; });
  }

  function closeModal() {
    $('cashExpenseModal').hidden = true;
    if (!document.querySelector('.modal-backdrop:not([hidden])')) document.body.style.overflow = '';
  }

  function deleteExpense(id) {
    var item = state.expenses.filter(function (x) { return Number(x.id) === Number(id); })[0];
    if (!item) return;
    var confirmAction = window.JASPEST_ADMIN_UI && window.JASPEST_ADMIN_UI.confirmDialog;
    var ask = typeof confirmAction === 'function'
      ? confirmAction({
        title: 'Hapus pengeluaran?',
        message: item.description + ' sebesar ' + money(item.amount) + ' (' + fmtDate(item.expense_date) + ') akan dihapus dan tidak lagi dihitung pada rekap bulanan.',
        confirmText: 'Hapus pengeluaran', danger: true
      })
      : Promise.resolve(window.confirm('Hapus pengeluaran ini?'));
    Promise.resolve(ask).then(function (ok) {
      if (!ok) return;
      return request('/expenses/' + encodeURIComponent(item.id), { method: 'DELETE' })
        .then(function (res) { notify((res && res.message) || 'Pengeluaran berhasil dihapus.', 'success'); return reload(true); })
        .catch(function (e) { notify(e.message, 'error'); });
    });
  }

  /* ---------------- Event ---------------- */
  var reloadExpenses = debounce(function () { state.expensePage = 1; loadExpenses(); }, 300);

  eachSel(BANK_SELECTS, function (sel) {
    sel.addEventListener('change', function () { state.bank = this.value; eachSel(BANK_SELECTS, function (o) { o.value = state.bank; }); state.expensePage = 1; reload(); });
  });
  eachSel(YEAR_SELECTS, function (sel) {
    sel.addEventListener('change', function () { state.year = Number(this.value); eachSel(YEAR_SELECTS, function (o) { o.value = String(state.year); }); state.expensePage = 1; reload(); });
  });
  $('cashExpenseSearch').addEventListener('input', reloadExpenses);
  $('cashExpenseCategory').addEventListener('change', function () { state.expensePage = 1; loadExpenses(); });
  $('cashExpenseMonth').addEventListener('change', function () { state.expensePage = 1; loadExpenses(); });
  $('cashExpensePrev').addEventListener('click', function () { if (state.expensePage > 1 && state.lastRes) { state.expensePage -= 1; renderExpenses(state.lastRes); } });
  $('cashExpenseNext').addEventListener('click', function () { if (state.lastRes) { state.expensePage += 1; renderExpenses(state.lastRes); } });
  $('cashExpenseFormMethod').addEventListener('change', applyMethodRules);
  $('cashExpenseForm').addEventListener('submit', saveExpense);

  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-cash-expense-add]');
    if (add) { openModal(null); return; }
    var edit = e.target.closest('[data-expense-edit]');
    if (edit) {
      var found = state.expenses.filter(function (x) { return Number(x.id) === Number(edit.getAttribute('data-expense-edit')); })[0];
      if (found) openModal(found);
      return;
    }
    var del = e.target.closest('[data-expense-delete]');
    if (del) deleteExpense(del.getAttribute('data-expense-delete'));
  });

  var chart = $('cashYoyChart');
  chart.addEventListener('mouseover', function (e) { var t = e.target.closest('.cc-hover'); if (t) showTip(t); });
  chart.addEventListener('focusin', function (e) { var t = e.target.closest('.cc-hover'); if (t) showTip(t); });
  chart.addEventListener('click', function (e) { var t = e.target.closest('.cc-hover'); if (t) showTip(t); });
  chart.addEventListener('mouseleave', hideTip);
  chart.addEventListener('focusout', hideTip);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !$('cashExpenseModal').hidden) closeModal();
  });

  var OWN_PAGES = ['expenses', 'finance-summary'];
  document.addEventListener('jaspest:page', function (e) { if (e.detail && OWN_PAGES.indexOf(e.detail.name) !== -1) open(); });

  // Segarkan otomatis saat halaman tampil, selaras dengan halaman keuangan lainnya.
  function pageActive() {
    return !document.hidden && OWN_PAGES.some(function (n) { var el = $('page-' + n); return !!el && el.classList.contains('active'); });
  }
  function silentRefresh() {
    if (!pageActive() || !$('cashExpenseModal').hidden) return;
    reload(true);
  }
  window.addEventListener('jaspest:orders-changed', silentRefresh);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) silentRefresh(); });
  setInterval(silentRefresh, 30000);

  ensureMonthOptions();

  // Halaman dibuka langsung lewat hash/URL sebelum event 'jaspest:page' terpasang.
  if (OWN_PAGES.some(function (n) { return $('page-' + n) && $('page-' + n).classList.contains('active'); })) open();
})();

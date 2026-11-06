/* Tombol "Buat Invoice" pada halaman Invoice.
   Manual   : pilih nama order dari data Deal Order > isi harga dan urutan kunjungan yang dibayarkan > data lainnya.
   Otomatis : semua data diisi dari Deal Order, invoice langsung terbit dan dicetak. */
(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const config = window.JASPEST_ADMIN_CONFIG || {};
  if (!config.canAccessFinance || !config.apiBase) return;
  const base = String(config.apiBase).replace(/\/$/, '') + '/finance';
  const csrf = $('meta[name="csrf-token"]')?.content || '';
  const modal = $('#financeCreateModal');
  const form = $('#financeCreateForm');
  const openButton = $('#financeCreateInvoice');
  if (!modal || !form || !openButton) return;

  const state = { orders: [], refs: {}, order: null, mode: 'manual', loading: false };
  const STATUS = { done: 'Selesai', scheduled: 'Terjadwal', unscheduled: 'Belum dijadwalkan', skipped: 'Dilewati', cancelled: 'Dibatalkan' };

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0)).replace(/[\s\u00a0]/g, '');
  const localDate = (date = new Date()) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const plusDays = (date, days) => { const next = new Date(`${date}T00:00:00`); next.setDate(next.getDate() + days); return localDate(next); };
  const shortDate = (value) => {
    if (!value) return 'tanggal belum diisi';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`);
    return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(date);
  };

  async function request(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (csrf) headers.set('X-CSRF-TOKEN', csrf);
    let body = options.body;
    if (body && typeof body !== 'string') { headers.set('Content-Type', 'application/json'); body = JSON.stringify(body); }
    let response;
    try { response = await fetch(`${base}${path}`, { ...options, headers, body, credentials: 'same-origin' }); }
    catch { throw new Error('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'); }
    const type = response.headers.get('content-type') || '';
    const payload = type.includes('application/json') ? await response.json().catch(() => null) : null;
    if (!response.ok) {
      if (response.status === 401 || response.status === 419) throw new Error('Sesi login berakhir. Silakan masuk kembali.');
      const validation = Object.values(payload?.errors || {}).flat()[0];
      throw new Error(validation || payload?.message || 'Permintaan gagal diproses.');
    }
    return payload;
  }

  function feedback(message, type = 'success') {
    const sharedToast = window.JASPEST_ADMIN_UI?.toast;
    if (typeof sharedToast === 'function') { sharedToast(message, type); return; }
    const element = $('#financeFeedback');
    if (!element) return;
    element.hidden = false;
    element.dataset.type = type;
    element.textContent = message;
  }

  function closeModal() {
    modal.hidden = true;
    if (!$('.modal-backdrop:not([hidden])')) document.body.style.overflow = '';
    window.JASPEST_SCROLL_GUARD?.sync();
  }

  /* ---------- Ringkasan urutan kunjungan: [1,2,3,5] -> "1-3, 5" ---------- */
  function compressSequences(numbers) {
    const sorted = Array.from(new Set(numbers)).sort((a, b) => a - b);
    const parts = [];
    for (let i = 0; i < sorted.length;) {
      let j = i;
      while (j + 1 < sorted.length && sorted[j + 1] === sorted[j] + 1) j += 1;
      parts.push(j > i ? `${sorted[i]}-${sorted[j]}` : String(sorted[i]));
      i = j + 1;
    }
    return parts.join(', ');
  }

  /* ---------- Pilihan order ---------- */
  function orderLabel(order) {
    const kind = order.is_contract ? `Kontrak ${order.contract_number || ''}`.trim() : 'Order sekali';
    return `${order.id} — ${order.customer_name} · ${kind}`;
  }
  function renderOptions() {
    const select = form.elements.order;
    const current = select.value;
    // Order yang sudah ditagih (atau tidak ada yang tersisa untuk ditagih) tidak ditampilkan di dropdown.
    const rows = state.orders.filter((order) => order.can_bill);
    select.innerHTML = `<option value="">${rows.length ? 'Pilih nama order' : 'Tidak ada order yang siap ditagih'}</option>`
      + rows.map((order) => `<option value="${esc(order.id)}">${esc(orderLabel(order))}</option>`).join('');
    if (rows.some((order) => order.id === current)) select.value = current;
  }

  /* ---------- Mode ---------- */
  function setMode(mode) {
    state.mode = mode;
    const manual = mode === 'manual';
    $('#fcManual').hidden = !manual || !state.order;
    $('#fcAuto').hidden = manual || !state.order;
    $('#fcSubmit').textContent = manual ? 'Terbitkan Invoice' : 'Terbitkan & Cetak Otomatis';
    form.querySelectorAll('[data-manual-required]').forEach((field) => { field.required = manual && !!state.order; });
    refreshVisitRequirements();
    $('#fcSubmit').disabled = !state.order;
    renderAutoPlan();
  }
  function refreshVisitRequirements() {
  }

  /* ---------- Order terpilih ---------- */
  function selectOrder(reference) {
    const order = state.orders.find((item) => item.id === reference) || null;
    state.order = order;
    const info = $('#fcOrderInfo');
    info.hidden = !order;
    if (!order) { setMode(state.mode); return; }

    info.textContent = `${order.customer_name} · ${order.job_type || '-'} · ${order.is_contract ? `Kontrak ${order.contract_number}` : 'Order sekali'} · Nilai Deal Order ${rupiah(order.order_price)}`;
    form.elements.invoice_date.value = localDate();
    form.elements.due_date.value = plusDays(form.elements.invoice_date.value, 30);
    form.elements.due_date.dataset.auto = 'true';
    form.elements.billing_name.value = order.billing_name || '';
    form.elements.job_type.value = order.job_type || '';
    form.elements.job_address.value = order.job_address || '';
    // Periode pekerjaan tidak lagi diisi manual: kontrak dihitung server dari kunjungan, order sekali dari data order.
    form.elements.ppn_rate.value = '0';
    form.elements.bank_account_id.value = String(state.refs.default_invoice_bank_account_id || '');

    $('#fcPriceLabel').firstChild.textContent = order.is_contract ? 'Harga per Kunjungan (Rp) ' : 'Nilai Pekerjaan (Rp) ';
    form.elements.price.value = Number(order.unit_price) > 0 ? String(Number(order.unit_price)) : '';
    $('#fcVisitsBlock').hidden = !order.is_contract;
    renderChips();
    setMode(state.mode);
    updateTotal();
  }

  function renderChips() {
    const list = $('#fcSeqList');
    const order = state.order;
    if (!order?.is_contract) { list.innerHTML = ''; return; }
    const proposed = new Set(order.auto?.sequences || []);
    list.innerHTML = order.visits.map((visit) => {
      const disabled = !visit.billable;
      let note = '';
      if (visit.billed) note = `Sudah ditagih${visit.invoice_number ? ` (${esc(visit.invoice_number)})` : ''}`;
      else if (visit.status === 'unscheduled') note = '';
      else note = visit.scheduled_date ? `${esc(STATUS[visit.status] || visit.status)} · ${esc(shortDate(visit.scheduled_date))}` : esc(STATUS[visit.status] || visit.status);
      return `<label class="fin-seq-chip${disabled ? ' is-disabled' : ''}">
        <input type="checkbox" value="${Number(visit.sequence)}" ${disabled ? 'disabled' : ''} ${!disabled && proposed.has(visit.sequence) ? 'checked' : ''}>
        <span>Kunjungan ke-${Number(visit.sequence)}${visit.is_extra ? ' (tambahan)' : ''}${visit.period_label ? ` · Periode ${esc(visit.period_label)}` : ''}</span><small>${note}</small>
      </label>`;
    }).join('');
  }
  function selectedSequences() {
    return Array.from(document.querySelectorAll('#fcSeqList input[type="checkbox"]:checked')).map((box) => Number(box.value)).sort((a, b) => a - b);
  }

  function updateTotal() {
    const order = state.order;
    if (!order) return;
    const price = Number(form.elements.price.value || 0);
    const total = $('#fcTotal');
    const hint = $('#fcTotalHint');
    if (order.is_contract) {
      const count = selectedSequences().length;
      total.textContent = rupiah(price * count);
      hint.textContent = count ? `${count} kunjungan × ${rupiah(price)} (belum termasuk PPN).` : 'Centang kunjungan yang dibayarkan.';
    } else {
      total.textContent = rupiah(price);
      hint.textContent = 'Nilai pekerjaan invoice (belum termasuk PPN). Ubah bila hanya sebagian yang ditagihkan.';
    }
  }

  function renderAutoPlan() {
    const box = $('#fcAutoPlan');
    const order = state.order;
    if (!box) return;
    if (!order) { box.innerHTML = ''; return; }
    if (!order.is_contract) {
      box.innerHTML = `<strong>Nilai order penuh</strong><span>${esc(rupiah(order.auto?.amount || 0))}</span>`;
      return;
    }
    const sequences = order.auto?.sequences || [];
    box.innerHTML = sequences.length
      ? `<strong>Kunjungan ke-${esc(compressSequences(sequences))}</strong><span>${sequences.length} kunjungan × ${esc(rupiah(order.unit_price))} = ${esc(rupiah(order.auto.amount))}</span>`
      : '<strong>Tidak ada kunjungan yang dapat ditagih</strong>';
  }

  /* ---------- Buka modal ---------- */
  async function openModal() {
    if (state.loading) return;
    state.loading = true;
    openButton.disabled = true;
    try {
      const [orders, refs] = await Promise.all([request('/deal-orders'), request('/references')]);
      state.orders = orders.data || [];
      state.refs = refs || {};
    } catch (error) {
      feedback(error.message, 'error');
      return;
    } finally { state.loading = false; openButton.disabled = false; }

    form.reset();
    state.order = null;
    const banks = state.refs.bank_accounts || [];
    form.elements.bank_account_id.innerHTML = `<option value="">${banks.length ? 'Rekening utama (otomatis)' : 'Belum ada rekening aktif — tambahkan di Master Data Rekening'}</option>`
      + banks.map((a) => `<option value="${Number(a.id)}">${esc(`${a.bank_name} · ${a.account_number} · A.n. ${a.account_holder}${a.account_label ? ` · ${a.account_label}` : ''}`)}</option>`).join('');
    renderOptions();
    $('#fcOrderInfo').hidden = true;
    form.elements.mode.value = 'manual';
    setMode('manual');
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.order.focus();
  }

  /* ---------- Kirim ---------- */
  async function submit(event) {
    event.preventDefault();
    const order = state.order;
    if (!order) { feedback('Pilih nama order terlebih dahulu.', 'error'); return; }
    if (!form.reportValidity()) return;

    const automatic = state.mode === 'auto';
    const printAfter = automatic || form.elements.print_after.checked;
    let payload = {};
    let path;

    if (!automatic) {
      const price = Number(form.elements.price.value || 0);
      if (!(price > 0)) { feedback('Isi harga terlebih dahulu.', 'error'); return; }
      const common = {
        invoice_date: form.elements.invoice_date.value,
        due_date: form.elements.due_date.value,
        billing_name: form.elements.billing_name.value.trim(),
        job_type: form.elements.job_type.value.trim(),
        job_address: form.elements.job_address.value.trim(),
        work_period: order.is_contract ? '' : String(order.work_period || '').trim(),
        ppn_rate: Number(form.elements.ppn_rate.value || 0),
        bank_account_id: form.elements.bank_account_id.value || null,
      };
      if (order.is_contract) {
        const picked = selectedSequences();
        if (!picked.length) { feedback('Centang minimal satu kunjungan yang dibayarkan.', 'error'); return; }
        const bySequence = new Map(order.visits.map((visit) => [visit.sequence, visit]));
        payload = { ...common, visits: picked.map((n) => ({ id: bySequence.get(n).id, amount: price })) };
        if (!payload.work_period) delete payload.work_period;
        path = `/contracts/${encodeURIComponent(order.contract_id)}/invoice`;
      } else {
        payload = { ...common, work_amount: price };
        path = `/orders/${encodeURIComponent(order.id)}/invoice`;
      }
    } else {
      path = `/deal-orders/${encodeURIComponent(order.id)}/invoice/auto`;
    }

    const submitButton = $('#fcSubmit');
    submitButton.disabled = true;
    let printWindow = null;
    if (printAfter) {
      printWindow = window.open('about:blank', '_blank');
      if (printWindow) {
        try { printWindow.document.write('<title>Menyiapkan invoice…</title><p style="font:14px Arial;padding:24px">Menyiapkan dokumen invoice…</p>'); } catch (_) { /* tab akan dinavigasi */ }
      }
    }
    try {
      const result = await request(path, { method: 'POST', body: payload });
      closeModal();
      feedback(`Invoice ${result.invoice_number} berhasil diterbitkan.`);
      if (printAfter && printWindow) {
        printWindow.location.replace(`${base}/invoices/${encodeURIComponent(result.id)}/print?auto_print=1`);
      } else if (printAfter) {
        feedback('Invoice berhasil diterbitkan, tetapi pop-up diblokir. Gunakan tombol Cetak Invoice pada daftar invoice.', 'warning');
      }
      window.dispatchEvent(new CustomEvent('jaspest:finance-open'));
    } catch (error) {
      if (printWindow && !printWindow.closed) printWindow.close();
      feedback(error.message, 'error');
    } finally { submitButton.disabled = !state.order; }
  }

  openButton.addEventListener('click', openModal);
  form.addEventListener('submit', submit);
  form.elements.order.addEventListener('change', (event) => selectOrder(event.target.value));
  form.querySelectorAll('input[name="mode"]').forEach((radio) => radio.addEventListener('change', (event) => { if (event.target.checked) setMode(event.target.value); }));
  form.elements.price.addEventListener('input', updateTotal);
  $('#fcSeqList').addEventListener('change', updateTotal);
  form.elements.invoice_date.addEventListener('change', (event) => {
    const due = form.elements.due_date;
    if (due.dataset.auto === 'true') due.value = plusDays(event.target.value, 30);
  });
  form.elements.due_date.addEventListener('change', (event) => { event.target.dataset.auto = 'false'; });
})();

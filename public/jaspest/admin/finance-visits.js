/* Tab "Kunjungan Siap Ditagihkan" pada halaman Invoice.
   Mandiri: memuat kunjungan kontrak yang sudah selesai tetapi belum ditagih, lalu menerbitkan
   satu invoice dari kunjungan yang dicentang (satu bulan atau beberapa bulan sekaligus). */
(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const config = window.JASPEST_ADMIN_CONFIG || {};
  if (!config.canAccessFinance || !config.apiBase) return;
  const base = String(config.apiBase).replace(/\/$/, '') + '/finance';
  const csrf = $('meta[name="csrf-token"]')?.content || '';
  const state = { contracts: [], active: null, references: null, loading: false };

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
  const dateId = (value) => {
    if (!value) return 'Belum dijadwalkan';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`);
    return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(date);
  };
  const monthYear = (value) => {
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`);
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(date);
  };
  const localDate = (date = new Date()) => {
    const y = date.getFullYear(); const m = String(date.getMonth() + 1).padStart(2, '0'); const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  };
  const plusDays = (date, days) => { const next = new Date(`${date}T00:00:00`); next.setDate(next.getDate() + days); return localDate(next); };

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
    const modal = $('#financeVisitInvoiceModal');
    if (modal) modal.hidden = true;
    if (!$('.modal-backdrop:not([hidden])')) document.body.style.overflow = '';
    window.JASPEST_SCROLL_GUARD?.sync();
  }

  /* ---------- Tab ---------- */
  const tabInv = $('#finTabInvoices');
  const tabOrd = $('#finTabOrders');
  const tabVis = $('#finTabVisits');
  const panelInv = $('#financeInvoicesPanel');
  const panelOrd = $('#financeOrdersPanel');
  const panelVis = $('#financeVisitsPanel');
  if (tabVis && panelVis) {
    const setVisitsActive = (active) => {
      panelVis.hidden = !active;
      tabVis.classList.toggle('active', active);
      tabVis.setAttribute('aria-selected', active ? 'true' : 'false');
    };
    tabVis.addEventListener('click', () => {
      if (panelInv) panelInv.hidden = true;
      if (panelOrd) panelOrd.hidden = true;
      [tabInv, tabOrd].forEach((tab) => { if (tab) { tab.classList.remove('active'); tab.setAttribute('aria-selected', 'false'); } });
      setVisitsActive(true);
    });
    [tabInv, tabOrd].forEach((tab) => tab?.addEventListener('click', () => setVisitsActive(false)));
  }

  /* ---------- Daftar kontrak yang siap ditagih ---------- */
  function renderContracts() {
    const rows = state.contracts;
    const billableVisits = rows.filter((c) => !c.legacy_full_invoice).reduce((sum, c) => sum + Number(c.unbilled_visit_count || 0), 0);
    const badge = $('#finTabVisitsBadge');
    if (badge) { badge.textContent = String(billableVisits); badge.hidden = billableVisits === 0; }
    const count = $('#financeVisitContractCount');
    if (count) count.textContent = `${rows.length} kontrak`;
    const empty = $('#financeVisitsEmpty');
    if (empty) empty.hidden = rows.length !== 0;
    const body = $('#financeVisitRows');
    if (!body) return;
    body.innerHTML = rows.map((contract) => {
      const action = contract.legacy_full_invoice
        ? '<small class="finance-subline">Sudah ditagih penuh lewat invoice lama</small>'
        : `<button type="button" class="btn btn-primary btn-sm" data-visit-bill="${Number(contract.contract_id)}">Tagih Kunjungan</button>`;
      return `
      <tr>
        <td><strong>${esc(contract.contract_number)}</strong><small class="finance-subline">Order ${esc(contract.order_reference || '-')}</small></td>
        <td>${esc(contract.billing_name || '-')}</td>
        <td>${esc(contract.job_type || '-')}</td>
        <td><strong>${Number(contract.unbilled_visit_count || 0)} kunjungan</strong><small class="finance-subline">${Number(contract.done_visit_count || 0)} selesai · belum ditagih</small></td>
        <td class="finance-money">${Number(contract.unit_price) > 0 ? rupiah(contract.unit_price) : '<small class="finance-subline">Isi manual</small>'}</td>
        <td>${action}</td>
      </tr>`;
    }).join('');
  }

  async function loadVisits(options) {
    const silent = options === 'silent';
    if (state.loading) return;
    state.loading = true;
    try {
      const [billable, references] = await Promise.all([request('/contracts/billable'), request('/references')]);
      state.contracts = billable.data || [];
      state.references = references || {};
      renderContracts();
    } catch (error) { if (!silent) feedback(error.message, 'error'); }
    finally { state.loading = false; }
  }

  function silentRefresh() {
    if (document.hidden || !$('#page-finance')?.classList.contains('active')) return;
    if ($('.modal-backdrop:not([hidden])')) return;
    loadVisits('silent');
  }

  /* ---------- Modal buat invoice ---------- */
  function visitRowsHtml(contract) {
    return contract.visits.map((visit) => {
      const suggested = Number(visit.suggested_amount) > 0 ? Number(visit.suggested_amount) : '';
      const label = visit.is_extra ? 'Kunjungan tambahan' : `${esc(contract.period_unit || 'Periode')} ke-${Number(visit.period_index)} · kunjungan ${Number(visit.slot_no)}`;
      const statusText = { done: 'Selesai', scheduled: 'Terjadwal', unscheduled: 'Belum dijadwalkan' }[visit.status] || visit.status;
      // Hanya kunjungan yang sudah selesai yang tercentang otomatis; sisanya dicentang manual bila ditagih di muka.
      const checked = visit.status === 'done' ? 'checked' : '';
      return `
      <div class="fin-visit-row" data-visit-row="${Number(visit.id)}">
        <input type="checkbox" class="fin-visit-check" id="finVisit${Number(visit.id)}" ${checked} aria-label="Tagih kunjungan ini">
        <label for="finVisit${Number(visit.id)}">${label}<small>${esc(statusText)} · ${esc(visit.scheduled_date ? dateId(visit.scheduled_date) : 'tanggal belum diisi')}</small></label>
        <input type="number" data-rupiah class="fin-visit-amount" min="0.01" step="0.01" required value="${esc(suggested)}" aria-label="Nilai kunjungan (Rp)" data-date="${esc(visit.scheduled_date || '')}">
      </div>`;
    }).join('');
  }

  function selectedVisits() {
    return Array.from(document.querySelectorAll('#financeVisitList .fin-visit-row')).filter((row) => $('.fin-visit-check', row).checked);
  }

  function refreshSelection() {
    const form = $('#financeVisitInvoiceForm');
    const rows = Array.from(document.querySelectorAll('#financeVisitList .fin-visit-row'));
    let total = 0;
    rows.forEach((row) => {
      const checked = $('.fin-visit-check', row).checked;
      const amountInput = $('.fin-visit-amount', row);
      amountInput.disabled = !checked;
      row.classList.toggle('is-unchecked', !checked);
      if (checked) total += Number(amountInput.value || 0);
    });
    const chosen = selectedVisits();
    $('#financeVisitTotal').textContent = rupiah(total);
    $('#financeVisitTotalHint').textContent = chosen.length
      ? `${chosen.length} dari ${rows.length} kunjungan dipilih. Nilai bisa diubah per kunjungan bila ada kesepakatan khusus.`
      : 'Centang minimal satu kunjungan.';
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = chosen.length === 0;

    // Periode pekerjaan mengikuti kunjungan terpilih selama belum diubah manual oleh admin.
    const period = form.elements.work_period;
    if (period && period.dataset.auto === 'true') {
      const months = chosen.map((row) => $('.fin-visit-amount', row).dataset.date).filter(Boolean).sort().map(monthYear).filter(Boolean);
      if (months.length) {
        const first = months[0]; const last = months[months.length - 1];
        period.value = first === last ? first : `${first} - ${last}`;
      }
    }
  }

  function openModal(contractId) {
    const contract = state.contracts.find((item) => Number(item.contract_id) === Number(contractId));
    if (!contract) return;
    state.active = contract;
    const form = $('#financeVisitInvoiceForm');
    form.reset();
    form.elements.invoice_date.value = localDate();
    form.elements.due_date.value = plusDays(form.elements.invoice_date.value, 30);
    form.elements.due_date.dataset.auto = 'true';
    form.elements.billing_name.value = contract.billing_name || '';
    form.elements.job_type.value = contract.job_type || '';
    form.elements.job_address.value = contract.job_address || '';
    if (form.elements.work_period) {
      form.elements.work_period.value = contract.suggested_work_period || '';
      form.elements.work_period.dataset.auto = 'true';
    }
    form.elements.ppn_rate.value = '0';

    const refs = state.references || {};
    const banks = refs.bank_accounts || [];
    form.elements.bank_account_id.innerHTML = `<option value="">${banks.length ? 'Pilih bank dan nomor rekening' : 'Belum ada rekening aktif — tambahkan di Master Data Rekening'}</option>` +
      banks.map((a) => `<option value="${Number(a.id)}">${esc(`${a.bank_name} · ${a.account_number} · A.n. ${a.account_holder}${a.account_label ? ` · ${a.account_label}` : ''}${a.branch ? ` · ${a.branch}` : ''}`)}</option>`).join('');
    form.elements.bank_account_id.value = String(refs.default_invoice_bank_account_id || '');

    $('#financeVisitInvoiceInfo').textContent = `${contract.contract_number} · ${contract.billing_name || '-'} · ${Number(contract.unbilled_visit_count)} kunjungan belum ditagih (${Number(contract.done_visit_count || 0)} selesai)`;
    $('#financeVisitList').innerHTML = visitRowsHtml(contract);
    refreshSelection();
    $('#financeVisitInvoiceModal').hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.job_address?.focus();
  }

  async function saveInvoice(event) {
    event.preventDefault();
    const contract = state.active;
    if (!contract) return;
    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    if (!form.reportValidity()) return;
    const chosen = selectedVisits();
    if (!chosen.length) { feedback('Pilih minimal satu kunjungan yang akan ditagih.', 'error'); return; }
    submit.disabled = true;
    let printWindow = null;
    const printMode = form.elements.print_mode?.value || 'manual';
    if (printMode === 'auto') {
      printWindow = window.open('about:blank', '_blank');
      if (printWindow) {
        try { printWindow.document.write('<title>Menyiapkan invoice…</title><p style="font:14px Arial;padding:24px">Menyiapkan dokumen invoice…</p>'); } catch (_) { /* tab akan dinavigasi */ }
      }
    }
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      delete values.print_mode;
      values.ppn_rate = Number(values.ppn_rate || 0);
      values.visits = chosen.map((row) => ({ id: Number(row.dataset.visitRow), amount: Number($('.fin-visit-amount', row).value) }));
      const result = await request(`/contracts/${encodeURIComponent(contract.contract_id)}/invoice`, { method: 'POST', body: values });
      closeModal();
      feedback(`Invoice ${result.invoice_number} berhasil diterbitkan untuk ${chosen.length} kunjungan.`);
      if (printMode === 'auto' && printWindow) {
        printWindow.location.replace(`${base}/invoices/${encodeURIComponent(result.id)}/print?auto_print=1`);
      } else if (printMode === 'auto') {
        feedback('Invoice berhasil diterbitkan, tetapi pop-up diblokir. Gunakan tombol Cetak Invoice untuk mencetak secara manual.', 'warning');
      }
      // Memuat ulang daftar invoice dan daftar kunjungan.
      window.dispatchEvent(new CustomEvent('jaspest:finance-open'));
    } catch (error) {
      if (printWindow && !printWindow.closed) printWindow.close();
      feedback(error.message, 'error');
    } finally { submit.disabled = false; }
  }

  document.addEventListener('click', (event) => {
    const bill = event.target.closest('[data-visit-bill]');
    if (bill) openModal(bill.dataset.visitBill);
  });
  document.addEventListener('input', (event) => {
    if (event.target.closest?.('#financeVisitList')) refreshSelection();
  });
  document.addEventListener('change', (event) => {
    if (event.target.closest?.('#financeVisitList')) refreshSelection();
  });

  const form = $('#financeVisitInvoiceForm');
  form?.addEventListener('submit', saveInvoice);
  form?.elements.work_period?.addEventListener('input', (event) => { event.target.dataset.auto = 'false'; });
  form?.elements.invoice_date?.addEventListener('change', (event) => {
    const due = form.elements.due_date;
    if (due && (!due.value || due.dataset.auto === 'true')) { due.value = plusDays(event.target.value, 30); due.dataset.auto = 'true'; }
  });
  form?.elements.due_date?.addEventListener('change', (event) => { event.target.dataset.auto = 'false'; });
  $('#financeVisitsRefresh')?.addEventListener('click', loadVisits);

  window.addEventListener('jaspest:finance-open', loadVisits);
  window.addEventListener('jaspest:orders-changed', silentRefresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) silentRefresh(); });
  setInterval(silentRefresh, 15000);
  document.addEventListener('DOMContentLoaded', () => {
    if (location.hash === '#finance') loadVisits();
  });
})();

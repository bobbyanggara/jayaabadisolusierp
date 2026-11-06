(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const config = window.JASPEST_ADMIN_CONFIG || {};
  if (!config.canAccessFinance || !config.apiBase) return;
  const base = String(config.apiBase).replace(/\/$/, '') + '/finance';
  const csrf = $('meta[name="csrf-token"]')?.content || '';
  const state = { orders: [], invoices: [], references: null, bankAccounts: [], activeOrder: null, activeInvoice: null, activeBankAccount: null, searchTimer: null, bankSearchTimer: null, loading: false, invoicePage: 1, invoiceMeta: { current_page: 1, last_page: 1, total: 0 } };

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0)).replace(/[\s\u00a0]/g, '');
  const dateId = (value) => {
    if (!value) return '-';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00+07:00`);
    return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(date);
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
    if (typeof sharedToast === 'function') {
      sharedToast(message, type);
      return;
    }
    // Fallback hanya bila skrip UI utama gagal dimuat.
    const element = $('#financeFeedback');
    if (!element) return;
    element.hidden = false;
    element.dataset.type = type;
    element.textContent = message;
  }

  function statusLabel(invoice) {
    if (invoice.is_overdue) return '<span class="finance-status is-overdue">Jatuh tempo</span>';
    const labels = { paid: 'Lunas', partial: 'Dibayar sebagian', unpaid: 'Belum dibayar' };
    const classes = { paid: 'is-paid', partial: 'is-partial', unpaid: 'is-unpaid' };
    return `<span class="finance-status ${classes[invoice.status] || ''}">${labels[invoice.status] || 'Belum dibayar'}</span>`;
  }

  function renderSummary(summary) {
    $('#financeTotalInvoiced').textContent = rupiah(summary.total_invoiced);
    $('#financeCollected').textContent = rupiah(summary.collected);
    $('#financeOutstanding').textContent = rupiah(summary.outstanding);
    $('#financeOverdue').textContent = String(summary.overdue_count || 0);
    $('#financeInvoiceCount').textContent = `${Number(summary.invoice_count || 0)} invoice diterbitkan`;
  }

  function renderReferenceDropdowns() {
    const refs = state.references || {};
    const invoiceBankSelect = $('#financeInvoiceBankAccount');
    const paymentBankSelect = $('#financePaymentBankAccount');
    const paymentCashSelect = $('#financePaymentCashAccount');
    const paymentMethodSelect = $('#financePaymentMethod');
    const bankOptions = (refs.bank_accounts || []).map((account) => ({
      id: account.id,
      label: `${account.bank_name} · ${account.account_number} · A.n. ${account.account_holder}${account.account_label ? ` · ${account.account_label}` : ''}${account.branch ? ` · ${account.branch}` : ''}`,
    }));
    const bankEmptyLabel = 'Belum ada rekening aktif. Tambahkan di Master Data › Rekening Bank';
    const bankHtml = `<option value="">${bankOptions.length ? 'Pilih bank dan nomor rekening' : bankEmptyLabel}</option>` +
      bankOptions.map((account) => `<option value="${Number(account.id)}">${esc(account.label)}</option>`).join('');
    if (invoiceBankSelect) invoiceBankSelect.innerHTML = bankHtml;
    if (paymentBankSelect) paymentBankSelect.innerHTML = bankHtml;
    if (paymentCashSelect) {
      const cashAccounts = (refs.cash_bank_accounts || []).filter((account) => ['111.01', '111.02'].includes(account.account_code));
      paymentCashSelect.innerHTML = '<option value="">Pilih akun kas</option>' + cashAccounts.map((account) =>
        `<option value="${esc(account.account_code)}">${esc(account.account_code)} — ${esc(account.account_name)}</option>`
      ).join('');
    }
    if (paymentMethodSelect) {
      paymentMethodSelect.innerHTML = (refs.payment_methods || []).map((method) =>
        `<option value="${esc(method.value)}">${esc(method.label)}</option>`
      ).join('');
    }
  }

  function applyPaymentDestinationRules() {
    const method = $('#financePaymentMethod')?.value || 'transfer';
    const isCash = method === 'cash';
    const bankField = $('#financePaymentBankAccountField');
    const cashField = $('#financePaymentCashAccountField');
    const bankSelect = $('#financePaymentBankAccount');
    const cashSelect = $('#financePaymentCashAccount');
    if (bankField) bankField.hidden = isCash;
    if (cashField) cashField.hidden = !isCash;
    if (bankSelect) {
      bankSelect.required = !isCash;
      bankSelect.disabled = isCash;
      if (isCash) bankSelect.value = '';
    }
    if (cashSelect) {
      cashSelect.required = isCash;
      cashSelect.disabled = !isCash;
      if (!isCash) cashSelect.value = '';
    }
  }

  function renderInvoices() {
    $('#financeInvoiceCountLabel').textContent = `${state.invoiceMeta.total || 0} invoice`;
    $('#financeInvoiceTableCount').textContent = `${state.invoiceMeta.total || 0} invoice`;
    $('#financeInvoicePageLabel').textContent = `${state.invoiceMeta.current_page || 1} / ${state.invoiceMeta.last_page || 1}`;
    $('#financeInvoicesPrev').disabled = (state.invoiceMeta.current_page || 1) <= 1;
    $('#financeInvoicesNext').disabled = (state.invoiceMeta.current_page || 1) >= (state.invoiceMeta.last_page || 1);
    $('#financeInvoicesEmpty').hidden = state.invoices.length !== 0;
    $('#financeInvoiceRows').innerHTML = state.invoices.map((invoice) => `
      <tr>
        <td><strong>${esc(invoice.invoice_number)}</strong><small class="finance-subline">Order ${esc(invoice.order_reference || '-')}${invoice.contract_number ? ` · ${esc(invoice.contract_number)} · ${Number(invoice.visit_count || 0)} kunjungan` : ''}</small></td>
        <td>${esc(invoice.billing_name)}<small class="finance-subline">${esc(invoice.job_type)}</small></td>
        <td>${dateId(invoice.invoice_date)}<small class="finance-subline">Jatuh tempo: ${dateId(invoice.due_date)}</small></td>
        <td class="align-right finance-money">${rupiah(invoice.total_amount)}</td>
        <td class="align-right finance-money">${rupiah(invoice.paid_amount)}<small class="finance-subline">Sisa ${rupiah(invoice.outstanding_amount)}</small></td>
        <td>${statusLabel(invoice)}</td>
        <td class="align-right"><div class="finance-row-actions"><button type="button" class="btn btn-outline btn-sm" data-finance-download="${invoice.id}" title="Unduh invoice sebagai PDF">Unduh</button>${invoice.outstanding_amount > 0 ? `<button type="button" class="btn btn-export btn-sm" data-finance-pay="${invoice.id}">Catat Pembayaran</button>` : ''}</div></td>
      </tr>`).join('');
  }

  async function loadFinance(options) {
    const silent = options === 'silent';
    if (state.loading) return;
    state.loading = true;
    try {
      const status = $('#financeStatusFilter').value;
      const term = $('#financeInvoiceSearch').value.trim();
      const [summary, invoices, references] = await Promise.all([
        request('/summary'), request(`/invoices?status=${encodeURIComponent(status)}&q=${encodeURIComponent(term)}&page=${state.invoicePage}`), request('/references'),
      ]);
      renderSummary(summary);
      state.references = references || {};
      renderReferenceDropdowns();
      state.invoices = invoices.data || [];
      state.invoiceMeta = invoices.meta || state.invoiceMeta;
      renderInvoices();
    } catch (error) { if (!silent) feedback(error.message, 'error'); }
    finally { state.loading = false; }
  }

  // Segarkan otomatis tanpa mengganggu admin: hanya saat halaman Invoice tampil, tab terlihat, dan tidak ada form terbuka.
  function silentRefresh() {
    if (document.hidden || !$('#page-finance')?.classList.contains('active')) return;
    if ($('.modal-backdrop:not([hidden])')) return;
    loadFinance('silent');
  }

  function renderBankAccounts(payload) {
    const rows = payload?.data || [];
    state.bankAccounts = rows;
    const summary = payload?.summary || {};
    // API summary is global, so the count stays accurate regardless of table search/status filters.
    $('#financeBankActive').textContent = String(summary.active ?? rows.filter((item) => item.is_active).length);
    $('#financeBankCount').textContent = `${rows.length} rekening ditampilkan`;
    $('#financeBankEmpty').hidden = rows.length > 0;
    $('#financeBankRows').innerHTML = rows.map((account) => `
      <tr>
        <td><strong>${esc(account.bank_name)}</strong>${account.branch ? `<small class="finance-subline">${esc(account.branch)}</small>` : ''}</td>
        <td class="finance-money">${esc(account.account_number)}</td>
        <td>${esc(account.account_holder)}</td>
        <td>${esc(account.account_label || '–')}</td>
        <td><span class="finance-status ${account.is_active ? 'is-paid' : ''}">${account.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
        <td><div class="finance-row-actions"><button type="button" class="btn btn-outline btn-sm" data-finance-bank-edit="${Number(account.id)}">Ubah</button><button type="button" class="btn btn-danger btn-sm" data-finance-bank-delete="${Number(account.id)}">Hapus</button></div></td>
      </tr>`).join('');
  }

  async function loadBankAccounts() {
    const search = $('#financeBankSearch')?.value.trim() || '';
    const status = $('#financeBankStatus')?.value || 'all';
    const payload = await request(`/bank-accounts?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
    renderBankAccounts(payload);
  }

  function openBankAccountModal(account = null) {
    const form = $('#financeBankAccountForm');
    if (!form) return;
    state.activeBankAccount = account;
    form.reset();
    form.dataset.accountId = account ? String(account.id) : '';
    form.elements.bank_name.value = account?.bank_name || '';
    form.elements.account_number.value = account?.account_number || '';
    form.elements.account_holder.value = account?.account_holder || '';
    form.elements.account_label.value = account?.account_label || '';
    form.elements.branch.value = account?.branch || '';
    form.elements.sort_order.value = String(account?.sort_order ?? 0);
    $('#financeBankAccountModalTitle').textContent = account ? 'Ubah Rekening Bank' : 'Tambah Rekening Bank';
    form.querySelector('[type="submit"]').textContent = account ? 'Simpan Perubahan' : 'Simpan Rekening';
    $('#financeBankAccountModal').hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.bank_name.focus();
  }

  async function saveBankAccount(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const submit = form.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      values.sort_order = Number(values.sort_order || 0);
      const accountId = form.dataset.accountId;
      await request(accountId ? `/bank-accounts/${encodeURIComponent(accountId)}` : '/bank-accounts', {
        method: accountId ? 'PUT' : 'POST', body: values,
      });
      closeFinanceModal('financeBankAccountModal');
      feedback(accountId ? 'Rekening berhasil diperbarui.' : 'Rekening berhasil ditambahkan.');
      await Promise.all([loadBankAccounts(), loadFinance()]);
    } catch (error) { feedback(error.message, 'error'); }
    finally { submit.disabled = false; }
  }

  async function deleteBankAccount(accountId) {
    const account = state.bankAccounts.find((item) => Number(item.id) === Number(accountId));
    if (!account) return;
    const confirmAction = window.JASPEST_ADMIN_UI?.confirmDialog;
    const confirmed = typeof confirmAction === 'function'
      ? await confirmAction({
          title: 'Hapus rekening bank?',
          message: `Rekening ${account.bank_name} · ${account.account_number} atas nama ${account.account_holder} akan dihapus dari Master Data. Invoice dan pembayaran lama tetap menyimpan snapshot nama bank, nomor rekening, dan pemilik rekening.`,
          confirmText: 'Hapus rekening',
          danger: true,
        })
      : false;
    if (!confirmed) return;
    try {
      await request(`/bank-accounts/${encodeURIComponent(account.id)}`, { method: 'DELETE' });
      feedback('Rekening berhasil dihapus. Data rekening pada dokumen historis tetap tersimpan.');
      await Promise.all([loadBankAccounts(), loadFinance()]);
    } catch (error) { feedback(error.message, 'error'); }
  }

  function openPaymentModal(invoiceId) {
    const invoice = state.invoices.find((item) => Number(item.id) === Number(invoiceId));
    if (!invoice) return;
    state.activeInvoice = invoice;
    const form = $('#financePaymentForm');
    form.reset();
    form.elements.payment_date.value = localDate();
    form.elements.amount.value = Number(invoice.outstanding_amount).toFixed(2);
    form.elements.method.value = 'transfer';
    form.elements.bank_account_id.value = String(invoice.bank_account_id || state.references?.default_invoice_bank_account_id || '');
    form.elements.receiving_account_code.value = '';
    applyPaymentDestinationRules();
    $('#financePaymentInvoiceInfo').textContent = `${invoice.invoice_number} · ${invoice.billing_name} · Sisa piutang ${rupiah(invoice.outstanding_amount)}`;
    form.elements.amount.max = Number(invoice.outstanding_amount).toFixed(2);
    $('#financePaymentModal').hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.amount.focus();
  }


  function closeFinanceModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.hidden = true;
    if (!$('.modal-backdrop:not([hidden])')) document.body.style.overflow = '';
    window.JASPEST_SCROLL_GUARD?.sync();
  }

  async function savePayment(event) {
    event.preventDefault();
    if (!state.activeInvoice) return;
    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    if (!form.reportValidity()) return;
    submit.disabled = true;
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      values.amount = Number(values.amount);
      await request(`/invoices/${state.activeInvoice.id}/payments`, { method: 'POST', body: values });
      closeFinanceModal('financePaymentModal');
      feedback('Pembayaran berhasil dicatat. Saldo piutang telah diperbarui.');
      await loadFinance();
    } catch (error) { feedback(error.message, 'error'); }
    finally { submit.disabled = false; }
  }

  function printInvoice(invoiceId, autoPrint = false) {
    const url = `${base}/invoices/${encodeURIComponent(invoiceId)}/print${autoPrint ? '?auto_print=1' : ''}`;
    const popup = window.open(url, '_blank');
    if (!popup) feedback('Browser memblokir tab cetak. Izinkan pop-up untuk halaman admin ini.', 'error');
  }

  document.addEventListener('click', (event) => {
    const download = event.target.closest('[data-finance-download]');
    if (download) { printInvoice(download.dataset.financeDownload, true); return; }
    const print = event.target.closest('[data-finance-print]');
    if (print) { printInvoice(print.dataset.financePrint); return; }
    const payment = event.target.closest('[data-finance-pay]');
    if (payment) { openPaymentModal(payment.dataset.financePay); return; }
    const bankEdit = event.target.closest('[data-finance-bank-edit]');
    if (bankEdit) { openBankAccountModal(state.bankAccounts.find((item) => Number(item.id) === Number(bankEdit.dataset.financeBankEdit)) || null); return; }
    const bankDelete = event.target.closest('[data-finance-bank-delete]');
    if (bankDelete) { deleteBankAccount(bankDelete.dataset.financeBankDelete); return; }
    const close = event.target.closest('[data-finance-close]');
    if (close) closeFinanceModal(close.dataset.financeClose);
  });

  $('#financePaymentForm')?.addEventListener('submit', savePayment);
  $('#financeBankAccountForm')?.addEventListener('submit', saveBankAccount);
  $('#financeBankAdd')?.addEventListener('click', () => openBankAccountModal());
  $('#financeBankEmptyAdd')?.addEventListener('click', () => openBankAccountModal());
  $('#financeBankSearch')?.addEventListener('input', () => {
    clearTimeout(state.bankSearchTimer);
    state.bankSearchTimer = setTimeout(() => loadBankAccounts().catch((error) => feedback(error.message, 'error')), 300);
  });
  $('#financeBankStatus')?.addEventListener('change', () => loadBankAccounts().catch((error) => feedback(error.message, 'error')));
  $('#financePaymentMethod')?.addEventListener('change', applyPaymentDestinationRules);
  $('#financeInvoicesPrev')?.addEventListener('click', () => { if (state.invoicePage > 1) { state.invoicePage -= 1; loadFinance(); } });
  $('#financeInvoicesNext')?.addEventListener('click', () => { if (state.invoicePage < state.invoiceMeta.last_page) { state.invoicePage += 1; loadFinance(); } });
  $('#financeStatusFilter')?.addEventListener('change', () => { state.invoicePage = 1; loadFinance(); });
  $('#financeInvoiceSearch')?.addEventListener('input', () => {
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(() => { state.invoicePage = 1; loadFinance(); }, 350);
  });
  $('#financeInvoiceDate')?.addEventListener('change', (event) => {
    const due = $('#financeDueDate');
    if (due && (!due.value || due.dataset.auto === 'true')) { due.value = plusDays(event.target.value, 30); due.dataset.auto = 'true'; }
  });
  $('#financeDueDate')?.addEventListener('change', (event) => { event.target.dataset.auto = 'false'; });

  window.addEventListener('jaspest:finance-open', loadFinance);
  window.addEventListener('jaspest:orders-changed', silentRefresh);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) silentRefresh(); });
  setInterval(silentRefresh, 15000);
  window.addEventListener('jaspest:finance-bank-accounts-open', () => loadBankAccounts().catch((error) => feedback(error.message, 'error')));
  document.addEventListener('DOMContentLoaded', () => {
    if (location.hash === '#finance') loadFinance();
  });
})();

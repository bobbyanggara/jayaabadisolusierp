(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const config = window.JASPEST_ADMIN_CONFIG || {};
  if (!config.canAccessFinance || !config.apiBase) return;
  const base = String(config.apiBase).replace(/\/$/, '') + '/finance';
  const csrf = $('meta[name="csrf-token"]')?.content || '';
  const state = { orders: [], invoices: [], references: null, bankAccounts: [], activeOrder: null, activeInvoice: null, activeBankAccount: null, searchTimer: null, bankSearchTimer: null, loading: false, invoicePage: 1, invoiceMeta: { current_page: 1, last_page: 1, total: 0 } };

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
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
    const bankEmptyLabel = 'Belum ada rekening aktif — tambahkan di Master Data Rekening';
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

  function renderOrders() {
    $('#financeOrderCount').textContent = `${state.orders.length} order`;
    $('#financeOrdersEmpty').hidden = state.orders.length !== 0;
    $('#financeOrderRows').innerHTML = state.orders.map((order) => `
      <tr>
        <td><strong>${esc(order.id)}</strong><small class="finance-subline">${order.is_contract ? 'Kontrak berkala' : 'Order sekali jalan'}</small></td>
        <td>${esc(order.customer_name || order.billing_name)}</td>
        <td>${esc(order.job_type || '-')}</td>
        <td class="finance-money">${rupiah(order.work_amount)}</td>
        <td>${dateId(order.converted_at)}</td>
        <td><button type="button" class="btn btn-primary btn-sm" data-finance-create="${esc(order.id)}">Buat Invoice</button></td>
      </tr>`).join('');
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
        <td><strong>${esc(invoice.invoice_number)}</strong><small class="finance-subline">Order ${esc(invoice.order_reference || '-')}</small></td>
        <td>${esc(invoice.billing_name)}<small class="finance-subline">${esc(invoice.location_name)}</small></td>
        <td>${dateId(invoice.invoice_date)}<small class="finance-subline">Jatuh tempo: ${dateId(invoice.due_date)}</small></td>
        <td class="finance-money">${rupiah(invoice.total_amount)}</td>
        <td class="finance-money">${rupiah(invoice.paid_amount)}<small class="finance-subline">Sisa ${rupiah(invoice.outstanding_amount)}</small></td>
        <td>${statusLabel(invoice)}</td>
        <td><div class="finance-row-actions"><button type="button" class="btn btn-export btn-sm" data-finance-print="${invoice.id}">Cetak Invoice</button>${invoice.outstanding_amount > 0 ? `<button type="button" class="btn btn-secondary btn-sm" data-finance-pay="${invoice.id}">Catat Bayar</button>` : ''}</div></td>
      </tr>`).join('');
  }

  async function loadFinance() {
    if (state.loading) return;
    state.loading = true;
    $('#financeRefresh').disabled = true;
    try {
      const status = $('#financeStatusFilter').value;
      const term = $('#financeInvoiceSearch').value.trim();
      const [summary, orders, invoices, references] = await Promise.all([
        request('/summary'), request('/orders'), request(`/invoices?status=${encodeURIComponent(status)}&q=${encodeURIComponent(term)}&page=${state.invoicePage}`), request('/references'),
      ]);
      renderSummary(summary);
      state.references = references || {};
      renderReferenceDropdowns();
      state.orders = orders.data || [];
      state.invoices = invoices.data || [];
      state.invoiceMeta = invoices.meta || state.invoiceMeta;
      renderOrders(); renderInvoices();
    } catch (error) { feedback(error.message, 'error'); }
    finally { state.loading = false; $('#financeRefresh').disabled = false; }
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
        <td>${esc(account.account_label || '—')}</td>
        <td><span class="finance-status ${account.is_active ? 'is-paid' : 'is-unpaid'}">${account.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
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
      feedback(accountId ? 'Master rekening berhasil diperbarui.' : 'Rekening baru berhasil ditambahkan ke Master Data.');
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
      feedback('Rekening berhasil dihapus dari Master Data. Data rekening pada dokumen historis tetap dipertahankan.');
      await Promise.all([loadBankAccounts(), loadFinance()]);
    } catch (error) { feedback(error.message, 'error'); }
  }

  async function exportFinanceExcel() {
    const button = $('#financeExport');
    if (button) button.disabled = true;
    try {
      const params = new URLSearchParams({
        status: $('#financeStatusFilter')?.value || 'all',
        q: $('#financeInvoiceSearch')?.value.trim() || '',
      });
      const response = await fetch(`${base}/export?${params.toString()}`, {
        method: 'GET', credentials: 'same-origin', cache: 'no-store',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      if (!response.ok) {
        let payload = null;
        if (contentType.includes('application/json')) payload = await response.json().catch(() => null);
        if (response.status === 401 || response.status === 419) throw new Error('Sesi login admin berakhir. Silakan login kembali.');
        if (response.status === 403) throw new Error('Akun ini tidak memiliki izin mengekspor laporan keuangan.');
        throw new Error(payload?.message || `Ekspor Excel gagal (HTTP ${response.status}).`);
      }
      if (!contentType.includes('spreadsheetml')) throw new Error('Server tidak mengirim file Excel yang valid. Periksa route ekspor dan log Laravel.');
      const blob = await response.blob();
      if (!blob.size) throw new Error('File Excel kosong. Tidak ada data yang dapat diunduh.');
      const disposition = response.headers.get('content-disposition') || '';
      const filenameMatch = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
      const filename = filenameMatch
        ? decodeURIComponent(filenameMatch[1] || filenameMatch[2])
        : `JASPEST-Laporan-Keuangan-${new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date())}.xlsx`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = filename;
      document.body.append(anchor);
      anchor.click();
      anchor.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1500);
      feedback('Laporan Keuangan & Akuntansi berhasil diekspor ke Excel.');
    } catch (error) { feedback(error.message, 'error'); }
    finally { if (button) button.disabled = false; }
  }

  function openInvoiceModal(orderId) {
    const order = state.orders.find((item) => String(item.id) === String(orderId));
    if (!order) return;
    state.activeOrder = order;
    const form = $('#financeInvoiceForm');
    form.reset();
    form.elements.invoice_date.value = localDate();
    form.elements.due_date.value = plusDays(form.elements.invoice_date.value, 30);
    form.elements.due_date.dataset.auto = 'true';
    form.elements.billing_name.value = order.billing_name || '';
    form.elements.billing_address.value = order.billing_address || '';
    form.elements.job_type.value = order.job_type || '';
    form.elements.location_name.value = order.location_name || '';
    form.elements.job_address.value = order.job_address || '';
    form.elements.work_period.value = order.work_period || '';
    form.elements.ppn_rate.value = '0';
    form.elements.bank_account_id.value = String(state.references?.default_invoice_bank_account_id || '');
    $('#financeInvoiceOrderInfo').textContent = `${order.id} · ${order.customer_name || order.billing_name} · Nilai pekerjaan ${rupiah(order.work_amount)}`;
    $('#financeInvoiceOrderAmount').textContent = rupiah(order.work_amount);
    $('#financeInvoiceModal').hidden = false;
    document.body.style.overflow = 'hidden';
    form.elements.location_name.focus();
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
  }

  async function saveInvoice(event) {
    event.preventDefault();
    if (!state.activeOrder) return;
    const form = event.currentTarget;
    const submit = form.querySelector('[type="submit"]');
    if (!form.reportValidity()) return;
    submit.disabled = true;
    let printWindow = null;
    const printMode = form.elements.print_mode?.value || 'manual';
    if (printMode === 'auto') {
      // Dibuka langsung dari interaksi submit agar tidak diblokir popup blocker.
      printWindow = window.open('about:blank', '_blank');
      if (printWindow) {
        try { printWindow.document.write('<title>Menyiapkan invoice…</title><p style="font:14px Arial;padding:24px">Menyiapkan dokumen invoice…</p>'); } catch (_) { /* navigasi akan mengganti isi tab */ }
      }
    }
    try {
      const values = Object.fromEntries(new FormData(form).entries());
      values.ppn_rate = Number(values.ppn_rate || 0);
      delete values.print_mode;
      const result = await request(`/orders/${encodeURIComponent(state.activeOrder.id)}/invoice`, { method: 'POST', body: values });
      closeFinanceModal('financeInvoiceModal');
      feedback(`Invoice ${result.invoice_number} berhasil diterbitkan.`);
      if (printMode === 'auto' && printWindow) {
        printWindow.location.replace(`${base}/invoices/${encodeURIComponent(result.id)}/print?auto_print=1`);
      } else if (printMode === 'auto' && !printWindow) {
        feedback('Invoice berhasil diterbitkan, tetapi pop-up diblokir. Gunakan tombol Cetak Invoice untuk mencetak secara manual.', 'warning');
      }
      await loadFinance();
    } catch (error) {
      if (printWindow && !printWindow.closed) printWindow.close();
      feedback(error.message, 'error');
    } finally { submit.disabled = false; }
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

  function printInvoice(invoiceId) {
    const url = `${base}/invoices/${encodeURIComponent(invoiceId)}/print`;
    const popup = window.open(url, '_blank');
    if (!popup) feedback('Browser memblokir tab cetak. Izinkan pop-up untuk halaman admin ini.', 'error');
  }

  document.addEventListener('click', (event) => {
    const create = event.target.closest('[data-finance-create]');
    if (create) { openInvoiceModal(create.dataset.financeCreate); return; }
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

  $('#financeInvoiceForm')?.addEventListener('submit', saveInvoice);
  $('#financePaymentForm')?.addEventListener('submit', savePayment);
  $('#financeBankAccountForm')?.addEventListener('submit', saveBankAccount);
  $('#financeBankAdd')?.addEventListener('click', () => openBankAccountModal());
  $('#financeBankEmptyAdd')?.addEventListener('click', () => openBankAccountModal());
  $('#financeExport')?.addEventListener('click', exportFinanceExcel);
  $('#financeBankSearch')?.addEventListener('input', () => {
    clearTimeout(state.bankSearchTimer);
    state.bankSearchTimer = setTimeout(() => loadBankAccounts().catch((error) => feedback(error.message, 'error')), 300);
  });
  $('#financeBankStatus')?.addEventListener('change', () => loadBankAccounts().catch((error) => feedback(error.message, 'error')));
  $('#financePaymentMethod')?.addEventListener('change', applyPaymentDestinationRules);
  $('#financeRefresh')?.addEventListener('click', loadFinance);
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
  window.addEventListener('jaspest:finance-bank-accounts-open', () => loadBankAccounts().catch((error) => feedback(error.message, 'error')));
  document.addEventListener('DOMContentLoaded', () => {
    if (location.hash === '#finance') loadFinance();
  });
})();

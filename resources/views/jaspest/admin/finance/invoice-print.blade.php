<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice {{ $invoice->invoice_number }} - CV Jaya Abadi Solusi</title>
  @php
    $money = function ($value) {
      $amount = (float) $value;
      $precision = abs($amount - round($amount)) < 0.005 ? 0 : 2;
      return number_format($amount, $precision, ',', '.');
    };
    // Legacy invoices used the BNI account printed on the original invoice template.
    $paymentBankName = $invoice->bank_name ?: 'BNI';
    $paymentBankNumber = $invoice->bank_account_number ?: '5516615550';
    $paymentBankHolder = $invoice->bank_account_holder ?: 'CV. Jaya Abadi Solusi';
  @endphp
  <style>
    @page { size: A4 portrait; margin: 12mm 16mm 12mm; }
    :root { --label-w: 150px; --colon-w: 12px; --block-indent: 9px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.42; background: #fff; }
    .toolbar { display: flex; justify-content: flex-end; gap: 8px; max-width: 900px; margin: 18px auto 12px; }
    .toolbar button { border: 0; border-radius: 6px; padding: 10px 16px; color: #fff; background: #174b8b; font-weight: 700; cursor: pointer; }
    .invoice { width: 100%; max-width: 900px; margin: 0 auto; padding: 0; }
    .letterhead { display: grid; grid-template-columns: 132px 1fr; align-items: center; gap: 16px; padding: 2px 26px 10px; border-bottom: 2px solid #222; }
    .letterhead img { display: block; width: 112px; height: 90px; object-fit: contain; }
    .company-name { margin: 0 0 3px; color: #0663c7; font-size: 20pt; line-height: 1.15; font-weight: 800; }
    .company-name span { color: #e3292c; }
    .company-line { margin: 0; line-height: 1.42; }
    .document-title { margin: 15px 0 14px; text-align: center; font-size: 23pt; font-weight: 400; text-decoration: underline; }
    .invoice-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 0 5px 14px; }
    .recipient p, .meta-line { margin: 0 0 5px; }
    .recipient strong { font-weight: 700; }
    .meta-line { display: grid; grid-template-columns: 90px var(--colon-w) 1fr; gap: 0; }
    .metadata .meta-line:first-child span:last-child { font-size: 9pt; white-space: nowrap; }
    .asterisk { white-space: nowrap; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .invoice-table th, .invoice-table td { border: 1px solid #222; }
    .invoice-table th { padding: 4px 8px; text-align: center; font-weight: 700; }
    .invoice-table th:first-child { width: 68%; }
    .invoice-table th:last-child { width: 32%; }
    .invoice-table.visits-table th:first-child { width: 50%; }
    .invoice-table.visits-table th:last-child { width: 50%; }
    .work-cell { height: 145px; padding: 16px 8px 10px; vertical-align: top; }
    .work-line { display: grid; grid-template-columns: var(--label-w) var(--colon-w) 1fr; margin: 0 0 2px; }
    .work-cell.has-visits { height: auto; padding-bottom: 14px; }
    .amount-cell { text-align: right; padding: 0 12px; }
    .work-cell.amount-cell { padding: 16px 12px 10px; }
    .work-cell.amount-cell.has-visits { padding-bottom: 14px; }
    .work-price, .work-period { margin: 0 0 2px; }
    .work-price { font-weight: 700; }
    .visit-entry { display: flex; justify-content: space-between; gap: 10px; margin: 0 0 3px; font-size: 10px; }
    .visit-entry .visit-price { white-space: nowrap; font-weight: 700; }
    .has-visits .visit-entry { text-align: left; }
    .visit-heading td { padding: 8px 8px 3px; border-top: 0; border-bottom: 0; font-weight: 700; }
    .visit-row td { padding: 2px 8px; border-top: 0; border-bottom: 0; }
    .visit-row td:first-child { padding-left: 20px; }
    .visit-row, .visit-heading { break-inside: avoid; }
    .summary-row td { padding: 2px 8px; border-top: 0; border-bottom: 0; }
    .summary-row.first td { padding-top: 8px; }
    .summary-row.last td { padding-bottom: 8px; border-bottom: 1px solid #222; font-weight: 700; }
    .summary-row .summary-label { text-align: left; }
    .summary-row .amount-cell { text-align: right; }
    .words { display: grid; grid-template-columns: var(--label-w) var(--colon-w) 1fr; margin: 4px 4px 16px var(--block-indent); }
    .payment-block { margin: 0 4px 20px var(--block-indent); }
    .payment-title { margin: 0 0 2px; font-weight: 700; }
    .payment-line { display: grid; grid-template-columns: var(--label-w) var(--colon-w) 1fr; margin: 0 0 2px; }
    .notes { margin: 0 4px; }
    .notes-title { margin: 0 0 3px; font-weight: 700; font-style: italic; }
    .notes p { margin: 0 0 3px; }
    .signature { margin: 28px 4px 0; min-height: 100px; }
    .signature-company { margin: 0 0 34px; }
    .signature-name { margin: 0; font-weight: 700; }
    .signature-role { margin: 0; }
    @media print { body { font-size: 10.8pt; } .toolbar { display: none !important; } .invoice { max-width: none; } }
    @media screen and (max-width: 720px) { .invoice { padding: 16px; } .letterhead { grid-template-columns: 88px 1fr; padding-inline: 0; gap: 10px; } .letterhead img { width: 80px; height: 76px; } .company-name { font-size: 15pt; } .invoice-meta { grid-template-columns: 1fr; } .meta-line { grid-template-columns: 100px var(--colon-w) 1fr; } :root { --label-w: 130px; } .toolbar { padding: 0 16px; } }
  </style>
</head>
<body>
  <div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
  @if(request()->boolean('auto_print'))
    <script>
      window.addEventListener('load', () => window.setTimeout(() => window.print(), 300), { once: true });
    </script>
  @endif
  <main class="invoice">
    <header class="letterhead">
      <img src="{{ asset('jaspest/assets/logo-jas.png') }}" alt="Logo JASPEST">
      <div>
        <h1 class="company-name">CV. JAYA ABADI <span>SOLUSI</span></h1>
        <p class="company-line"><strong>Pest and termite control</strong></p>
        <p class="company-line">Telp / Fax : 031-85582382</p>
        <p class="company-line">email: cv.jayaabadisolusi@yahoo.com</p>
      </div>
    </header>

    <h2 class="document-title">INVOICE</h2>

    <section class="invoice-meta">
      <div class="recipient">
        <p>Kepada Yth.</p>
        <p><strong>{{ $invoice->billing_name }}</strong></p>
      </div>
      <div class="metadata">
        <p class="meta-line"><span class="asterisk">Invoice No *</span><span>:</span><span>{{ $invoice->invoice_number }}</span></p>
        <p class="meta-line"><span class="asterisk">Tanggal *</span><span>:</span><span>{{ $invoice->invoice_date->locale('id')->translatedFormat('d F Y') }}</span></p>
      </div>
    </section>

    <table class="invoice-table{{ $invoice->visits->isNotEmpty() ? ' visits-table' : '' }}" aria-label="Rincian pekerjaan dan nilai invoice">
      <thead><tr><th>Keterangan</th><th>Nilai Pekerjaan</th></tr></thead>
      <tbody>
        <tr>
          <td class="work-cell{{ $invoice->visits->isNotEmpty() ? ' has-visits' : '' }}">
            <p class="work-line"><span>Jenis Pekerjaan *</span><span>:</span><span>{{ $invoice->job_type }}</span></p>
            <p class="work-line"><span>Alamat *</span><span>:</span><span>{{ $invoice->job_address }}</span></p>
            <p class="work-line"><span>Periode Pekerjaan *</span><span>:</span><span>{{ $invoice->work_period }}</span></p>
          </td>
          <td class="work-cell amount-cell{{ $invoice->visits->isNotEmpty() ? ' has-visits' : '' }}">
            @if($invoice->visits->isNotEmpty())
              @php
                $periodLabel = function ($visit) use ($invoice): string {
                    $type = $invoice->contract?->period_type;
                    $start = $visit->period_start?->copy()->locale('id');
                    $end = $visit->period_end?->copy()->locale('id');
                    if (! $start) {
                        return '-';
                    }
                    if ($type === 'monthly' || ! $end) {
                        return $start->translatedFormat('F Y');
                    }

                    return $start->translatedFormat('d M Y').' - '.$end->translatedFormat('d M Y');
                };
                $sortedVisits = $invoice->visits->sortBy(fn ($v) => [(int) $v->is_extra, (int) $v->period_index, (int) $v->slot_no, (int) $v->id])->values();
              @endphp
              @foreach($sortedVisits as $visit)
                <div class="visit-entry">
                  <span class="visit-period">Kunjungan {{ $loop->iteration }} - {{ $periodLabel($visit) }}{{ $visit->is_extra ? ' (tambahan)' : '' }}</span>
                  <span class="visit-price">Rp {{ $money($visit->billed_amount) }}</span>
                </div>
              @endforeach
            @else
              <p class="work-price">Rp {{ $money($invoice->work_amount) }}</p>
              <p class="work-period">{{ $invoice->work_period }}</p>
            @endif
          </td>
        </tr>
        <tr class="summary-row first"><td class="summary-label">Jumlah</td><td class="amount-cell">Rp {{ $money($invoice->work_amount) }}</td></tr>
        <tr class="summary-row"><td class="summary-label">PPN{{ (float) $invoice->ppn_rate > 0 ? ' ('.rtrim(rtrim(number_format((float) $invoice->ppn_rate, 2, ',', '.'), '0'), ',').'%)' : '' }}</td><td class="amount-cell">{{ (float) $invoice->ppn_amount > 0 ? 'Rp '.$money($invoice->ppn_amount) : '' }}</td></tr>
        <tr class="summary-row last"><td class="summary-label">Total</td><td class="amount-cell">Rp {{ $money($invoice->total_amount) }}</td></tr>
      </tbody>
    </table>

    <p class="words"><span>Terbilang *</span><span>:</span><span><strong>{{ $invoice->amount_words }}</strong></span></p>

    <section class="payment-block">
      <p class="payment-title">Pembayaran dapat di transfer ke:</p>
      <p class="payment-line"><span>No. Rekening *</span><span>:</span><span>{{ $paymentBankNumber }}</span></p>
      <p class="payment-line"><span>Bank *</span><span>:</span><span>{{ $paymentBankName }}</span></p>
      <p class="payment-line"><span>Atas Nama *</span><span>:</span><span>{{ $paymentBankHolder }}</span></p>
    </section>

    <section class="notes">
      <p class="notes-title">Keterangan:</p>
      <p>Jatuh tempo pembayaran maksimal 30 (tiga puluh) hari setelah invoice diterima.</p>
      <p>Pembayaran transfer dianggap lunas apabila mengirimkan bukti transfer melalui WA Office (+62 858-0945-8555)</p>
    </section>

    <footer class="signature">
      <p class="signature-company">CV. JAYA ABADI SOLUSI</p>
      <p class="signature-name">Yunita A.</p>
      <p class="signature-role">FAT</p>
    </footer>
  </main>
</body>
</html>

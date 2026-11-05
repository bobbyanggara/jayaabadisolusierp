<?php

namespace App\Http\Controllers;

use App\Models\JaspestContract;
use App\Models\JaspestEnquiry;
use App\Models\JaspestFinanceBankAccount;
use App\Models\JaspestFinanceInvoice;
use App\Models\JaspestFinancePayment;
use App\Support\JaspestFinanceXlsx;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JaspestFinanceController extends Controller
{
    public function references(): JsonResponse
    {
        $references = config('jaspest_finance', []);
        $bankAccounts = JaspestFinanceBankAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('bank_name')->orderBy('id')
            ->get();
        $configuredDefault = (int) ($references['default_invoice_bank_account_id'] ?? 0);
        $defaultBankAccountId = $bankAccounts->firstWhere('id', $configuredDefault)?->id ?? $bankAccounts->first()?->id;

        return response()->json([
            'source_workbook' => $references['source_workbook'] ?? 'BAHAN KEUANGAN NEW.xlsx',
            'bank_accounts' => $bankAccounts->map(fn (JaspestFinanceBankAccount $account): array => $this->bankAccountResource($account))->values(),
            'default_invoice_bank_account_id' => $defaultBankAccountId,
            'cash_bank_accounts' => $references['cash_bank_accounts'] ?? [],
            'payment_methods' => $references['payment_methods'] ?? [],
        ]);
    }

    /** Master data rekening menjadi sumber tunggal seluruh dropdown rekening keuangan. */
    public function bankAccounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);
        $query = JaspestFinanceBankAccount::query()->orderBy('sort_order')->orderBy('bank_name')->orderBy('id');
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('bank_name', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%")
                ->orWhere('account_holder', 'like', "%{$search}%")
                ->orWhere('account_label', 'like', "%{$search}%")
                ->orWhere('branch', 'like', "%{$search}%"));
        }
        if (($validated['status'] ?? 'all') !== 'all') {
            $query->where('is_active', ($validated['status'] ?? 'active') === 'active');
        }
        $accounts = $query->get();
        return response()->json([
            'data' => $accounts->map(fn (JaspestFinanceBankAccount $account): array => $this->bankAccountResource($account))->values(),
            'summary' => [
                'total' => JaspestFinanceBankAccount::query()->count(),
                'active' => JaspestFinanceBankAccount::query()->where('is_active', true)->count(),
                'inactive' => JaspestFinanceBankAccount::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function storeBankAccount(Request $request): JsonResponse
    {
        $data = $this->validatedBankAccount($request);
        $this->assertUniqueBankAccount($data);
        $account = JaspestFinanceBankAccount::query()->create($data);
        return response()->json($this->bankAccountResource($account), 201);
    }

    public function updateBankAccount(Request $request, int $bankAccount): JsonResponse
    {
        $account = JaspestFinanceBankAccount::query()->findOrFail($bankAccount);
        $data = $this->validatedBankAccount($request, $account);
        $this->assertUniqueBankAccount($data, $account);
        $account->update($data);
        return response()->json($this->bankAccountResource($account->fresh()));
    }

    /** Hapus rekening dari master sambil mempertahankan snapshot rekening pada dokumen historis. */
    public function deleteBankAccount(int $bankAccount): JsonResponse
    {
        DB::transaction(function () use ($bankAccount): void {
            $account = JaspestFinanceBankAccount::query()->lockForUpdate()->findOrFail($bankAccount);

            // Snapshot nama bank/nomor/pemilik tetap tersimpan pada invoice dan pembayaran.
            JaspestFinanceInvoice::query()->where('bank_account_id', $account->id)->update(['bank_account_id' => null]);
            JaspestFinancePayment::query()->where('received_bank_account_id', $account->id)->update(['received_bank_account_id' => null]);
            $account->delete();
        }, 3);

        return response()->json(['message' => 'Rekening berhasil dihapus. Snapshot transaksi historis tetap dipertahankan.']);
    }

    private function validatedBankAccount(Request $request, ?JaspestFinanceBankAccount $account = null): array
    {
        foreach (['bank_name', 'account_number', 'account_holder', 'account_label', 'branch'] as $field) {
            if (is_string($request->input($field))) $request->merge([$field => trim($request->input($field))]);
        }
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'min:2', 'max:80'],
            'account_number' => ['required', 'string', 'min:3', 'max:100'],
            'account_holder' => ['required', 'string', 'min:2', 'max:200'],
            'account_label' => ['nullable', 'string', 'max:200'],
            'branch' => ['nullable', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $data['account_label'] = $data['account_label'] ?: null;
        $data['branch'] = $data['branch'] ?: null;
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($account?->is_active ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? ($account?->sort_order ?? 0));
        return $data;
    }

    private function assertUniqueBankAccount(array $data, ?JaspestFinanceBankAccount $account = null): void
    {
        $duplicate = JaspestFinanceBankAccount::query()
            ->whereRaw('LOWER(bank_name) = ?', [mb_strtolower($data['bank_name'])])
            ->where('account_number', $data['account_number'])
            ->when($account, fn ($query) => $query->where('id', '<>', $account->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'account_number' => 'Nomor rekening tersebut sudah terdaftar untuk bank yang sama di Master Data.',
            ]);
        }
    }

    private function bankAccountResource(JaspestFinanceBankAccount $account): array
    {
        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'account_holder' => $account->account_holder,
            'account_label' => $account->account_label,
            'branch' => $account->branch,
            'is_active' => (bool) $account->is_active,
            'sort_order' => (int) $account->sort_order,
        ];
    }

    public function summary(): JsonResponse
    {
        $invoiceTotal = (float) JaspestFinanceInvoice::query()->sum('total_amount');
        $paymentTotal = (float) JaspestFinancePayment::query()->sum('amount');
        $overdue = JaspestFinanceInvoice::query()
            ->whereDate('due_date', '<', today())
            ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)')
            ->count();

        return response()->json([
            'total_invoiced' => round($invoiceTotal, 2),
            'collected' => round($paymentTotal, 2),
            'outstanding' => round(max(0, $invoiceTotal - $paymentTotal), 2),
            'overdue_count' => $overdue,
            'invoice_count' => JaspestFinanceInvoice::query()->count(),
            'unbilled_order_count' => JaspestEnquiry::query()->where('status', 'converted')
                ->whereNotNull('order_price')->where('order_price', '>', 0)
                ->whereNotIn('id', JaspestFinanceInvoice::query()->select('enquiry_id'))->count(),
        ]);
    }

    public function orders(): JsonResponse
    {
        $orders = JaspestEnquiry::query()
            ->where('status', 'converted')
            ->whereNotNull('order_price')->where('order_price', '>', 0)
            ->whereNotIn('id', JaspestFinanceInvoice::query()->select('enquiry_id'))
            ->with('clientLink.client')
            ->latest('converted_at')
            ->limit(500)
            ->get();

        $contracts = JaspestContract::query()
            ->whereIn('enquiry_id', $orders->pluck('id'))
            ->get()->keyBy('enquiry_id');

        return response()->json(['data' => $orders->map(function (JaspestEnquiry $order) use ($contracts): array {
            $client = $order->clientLink?->client;
            $contract = $contracts->get($order->id);
            $period = $this->defaultWorkPeriod($order, $contract);
            $jobType = $order->serviceTitleLabel();
            if ($contract && is_array($contract->services) && $contract->services !== []) {
                $labels = array_values(array_filter(array_map(
                    static fn ($item): string => is_array($item) ? trim((string) ($item['title'] ?? $item['slug'] ?? '')) : trim((string) $item),
                    $contract->services
                )));
                if ($labels !== []) {
                    $jobType = implode(' + ', $labels);
                }
            }

            return [
                'id' => $order->reference,
                'customer_name' => $order->company_name ?: $order->full_name,
                'billing_name' => $order->company_name ?: $order->full_name,
                'billing_address' => $client?->address ?: $order->address,
                'client_code' => $client?->client_code,
                'job_type' => $jobType,
                'location_name' => '',
                'job_address' => $order->address,
                'work_period' => $period,
                'work_amount' => (float) $order->order_price,
                'converted_at' => $order->converted_at?->copy()->timezone('Asia/Jakarta')->toDateString(),
                'is_contract' => (bool) $contract,
            ];
        })->values()]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $query = JaspestFinanceInvoice::query()->with(['enquiry:id,reference', 'payments']);
        $status = (string) $request->query('status', 'all');
        if ($status === 'paid') {
            $query->whereRaw('total_amount <= (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        } elseif ($status === 'unpaid') {
            $query->whereRaw('COALESCE((SELECT SUM(amount) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id), 0) = 0');
        } elseif ($status === 'partial') {
            $query->whereRaw('COALESCE((SELECT SUM(amount) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id), 0) > 0')
                ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        } elseif ($status === 'overdue') {
            $query->whereDate('due_date', '<', today())
                ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        }

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $query->where(fn ($builder) => $builder
                ->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('billing_name', 'like', "%{$term}%")
                ->orWhereHas('enquiry', fn ($order) => $order->where('reference', 'like', "%{$term}%")));
        }

        $page = $query->latest('invoice_date')->latest('id')->paginate(100);
        return response()->json([
            'data' => collect($page->items())->map(fn (JaspestFinanceInvoice $invoice): array => $this->invoiceResource($invoice))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function createInvoice(Request $request, JaspestEnquiry $enquiry): JsonResponse
    {
        $references = config('jaspest_finance', []);

        $validated = $request->validate([
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'billing_name' => ['required', 'string', 'max:200'],
            'billing_address' => ['required', 'string', 'max:3000'],
            'job_type' => ['required', 'string', 'max:250'],
            'location_name' => ['required', 'string', 'max:200'],
            'job_address' => ['required', 'string', 'max:3000'],
            'work_period' => ['required', 'string', 'max:180'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('jaspest_finance_bank_accounts', 'id')->where('is_active', true)],
        ], [
            'location_name.required' => 'Nama lokasi/gedung harus diisi sesuai lokasi pekerjaan.',
            'work_period.required' => 'Periode pekerjaan harus diisi sebelum invoice dibuat.',
            'bank_account_id.exists' => 'Rekening tujuan tidak aktif atau tidak ditemukan pada Master Data Rekening.',
        ]);

        $locationName = trim((string) $validated['location_name']);
        $jobAddress = trim((string) $validated['job_address']);

        $requestedBankAccountId = isset($validated['bank_account_id']) && $validated['bank_account_id'] !== ''
            ? (int) $validated['bank_account_id']
            : (int) ($references['default_invoice_bank_account_id'] ?? 0);
        $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->find($requestedBankAccountId);
        if (! $bankAccountModel && empty($validated['bank_account_id'])) {
            $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        }
        $bankAccount = $bankAccountModel?->toArray();

        if (! $locationName || ! $jobAddress) {
            throw ValidationException::withMessages([
                'job_address' => 'Nama lokasi dan alamat pekerjaan wajib lengkap sebelum invoice diterbitkan.',
            ]);
        }
        if (! $bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Pilih rekening aktif dari Master Data Rekening sebelum menerbitkan invoice.',
            ]);
        }

        $invoice = DB::transaction(function () use ($enquiry, $validated, $locationName, $jobAddress, $bankAccount): JaspestFinanceInvoice {
            $order = JaspestEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
            if ($order->status !== 'converted' || (float) $order->order_price <= 0) {
                throw ValidationException::withMessages(['order' => 'Invoice hanya dapat dibuat dari Deal Order yang memiliki nilai order valid.']);
            }
            if (JaspestFinanceInvoice::query()->where('enquiry_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Order ini sudah memiliki invoice.']);
            }

            $workAmount = round((float) $order->order_price, 2);
            $ppnRate = round((float) ($validated['ppn_rate'] ?? 0), 2);
            $ppnAmount = round($workAmount * $ppnRate / 100, 2);
            $totalAmount = round($workAmount + $ppnAmount, 2);

            $invoice = JaspestFinanceInvoice::query()->create([
                'invoice_number' => null,
                'enquiry_id' => $order->id,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'billing_name' => trim($validated['billing_name']),
                'billing_address' => trim($validated['billing_address']),
                'job_type' => trim($validated['job_type']),
                'location_name' => $locationName,
                'job_address' => $jobAddress,
                'bank_account_id' => (int) $bankAccount['id'],
                'bank_name' => trim((string) $bankAccount['bank_name']),
                'bank_account_number' => trim((string) $bankAccount['account_number']),
                'bank_account_holder' => trim((string) $bankAccount['account_holder']),
                'work_period' => trim($validated['work_period']),
                'work_amount' => $workAmount,
                'ppn_rate' => $ppnRate,
                'ppn_amount' => $ppnAmount,
                'total_amount' => $totalAmount,
                'amount_words' => $this->spellOutRupiah($totalAmount),
                'created_by' => auth()->id(),
            ]);

            $clientCode = $order->clientLink?->client?->client_code;
            if (! $clientCode) {
                $order->load('clientLink.client');
                $clientCode = $order->clientLink?->client?->client_code;
            }
            $clientCode = $clientCode ?: 'ORD'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
            $date = Carbon::parse($validated['invoice_date']);
            $romanMonths = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
            $number = str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT).'/INV/'.$clientCode.'/JAS/'.$romanMonths[(int) $date->format('n')].'/'.$date->format('y');
            $invoice->forceFill(['invoice_number' => $number])->save();

            return $invoice;
        }, 3);

        return response()->json($this->invoiceResource($invoice->load(['enquiry', 'payments'])), 201);
    }

    public function recordPayment(Request $request, JaspestFinanceInvoice $invoice): JsonResponse
    {
        $references = config('jaspest_finance', []);
        $cashBankAccounts = $references['cash_bank_accounts'] ?? [];
        $cashBankCodes = array_values(array_filter(array_column($cashBankAccounts, 'account_code')));

        $validated = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'method' => ['required', 'in:transfer,cash,bg,cheque_giro,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('jaspest_finance_bank_accounts', 'id')->where('is_active', true)],
            'receiving_account_code' => ['nullable', 'string', Rule::in($cashBankCodes)],
        ]);

        $method = (string) $validated['method'];
        if (in_array($method, ['transfer', 'bg'], true) && empty($validated['bank_account_id'])) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Pilih rekening bank penerima untuk pembayaran Transfer atau BG.',
            ]);
        }
        if ($method === 'cash' && ! in_array($validated['receiving_account_code'] ?? null, ['111.01', '111.02'], true)) {
            throw ValidationException::withMessages([
                'receiving_account_code' => 'Untuk pembayaran tunai, pilih KAS BESAR (111.01) atau KAS KECIL (111.02).',
            ]);
        }

        $bankAccountModel = ! empty($validated['bank_account_id'])
            ? JaspestFinanceBankAccount::query()->where('is_active', true)->find((int) $validated['bank_account_id'])
            : null;
        $bankAccount = $bankAccountModel?->toArray();
        $receivingChartAccount = collect($cashBankAccounts)->firstWhere('account_code', $validated['receiving_account_code'] ?? null);

        $payment = DB::transaction(function () use ($invoice, $validated, $bankAccount, $receivingChartAccount): JaspestFinancePayment {
            $locked = JaspestFinanceInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $paid = (float) $locked->payments()->sum('amount');
            $outstanding = round((float) $locked->total_amount - $paid, 2);
            $amount = round((float) $validated['amount'], 2);
            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount' => 'Nominal pembayaran melebihi sisa piutang invoice.']);
            }

            return JaspestFinancePayment::query()->create([
                'invoice_id' => $locked->id,
                'payment_date' => $validated['payment_date'],
                'amount' => $amount,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'receiving_account_code' => $receivingChartAccount['account_code'] ?? null,
                'receiving_account_name' => $receivingChartAccount['account_name'] ?? null,
                'received_bank_account_id' => $bankAccount['id'] ?? null,
                'received_bank_name' => $bankAccount['bank_name'] ?? null,
                'received_bank_account_number' => $bankAccount['account_number'] ?? null,
                'received_bank_account_holder' => $bankAccount['account_holder'] ?? null,
                'received_by' => auth()->id(),
            ]);
        }, 3);

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'payment' => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'amount' => (float) $payment->amount,
            ],
        ], 201);
    }

    /** Kas & bank: seluruh pembayaran masuk, dikelompokkan per akun kas/rekening penerima. */
    public function cash(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'account' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = JaspestFinancePayment::query()->with('invoice:id,invoice_number,billing_name')
            ->orderByDesc('payment_date')->orderByDesc('id');
        if (! empty($validated['from'])) {
            $query->whereDate('payment_date', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('payment_date', '<=', $validated['to']);
        }
        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '') {
            $query->where(fn ($builder) => $builder
                ->where('reference', 'like', "%{$term}%")
                ->orWhereHas('invoice', fn ($invoice) => $invoice
                    ->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('billing_name', 'like', "%{$term}%")));
        }

        $payments = $query->get()->map(function (JaspestFinancePayment $payment): array {
            [$key, $label] = $this->cashAccountOf($payment);
            return [
                'id' => $payment->id,
                'date' => $payment->payment_date?->format('Y-m-d'),
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'method_label' => $this->paymentMethodLabel((string) $payment->method),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'account_key' => $key,
                'account_label' => $label,
                'invoice_number' => $payment->invoice?->invoice_number,
                'customer' => $payment->invoice?->billing_name,
            ];
        });

        $accountFilter = (string) ($validated['account'] ?? 'all');
        $accounts = $payments->groupBy('account_key')->map(fn ($rows, $key): array => [
            'key' => $key,
            'label' => $rows->first()['account_label'],
            'total' => round((float) $rows->sum('amount'), 2),
            'count' => $rows->count(),
        ])->sortByDesc('total')->values();

        $rows = $accountFilter === 'all' ? $payments : $payments->where('account_key', $accountFilter)->values();
        $monthStart = today()->startOfMonth()->format('Y-m-d');

        return response()->json([
            'summary' => [
                'total_in' => round((float) $payments->sum('amount'), 2),
                'month_in' => round((float) $payments->where('date', '>=', $monthStart)->sum('amount'), 2),
                'cash_in' => round((float) $payments->where('method', 'cash')->sum('amount'), 2),
                'bank_in' => round((float) $payments->where('method', '!=', 'cash')->sum('amount'), 2),
                'count' => $payments->count(),
            ],
            'accounts' => $accounts,
            'data' => $rows->take(300)->values(),
            'truncated' => $rows->count() > 300,
        ]);
    }

    /** Piutang: invoice yang belum lunas, umur piutang (aging), dan ringkasan per pelanggan. */
    public function receivables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bucket' => ['nullable', 'in:all,current,d1_30,d31_60,d61_90,d90_plus'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = JaspestFinanceInvoice::query()->with(['enquiry:id,reference', 'payments'])
            ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)')
            ->orderBy('due_date')->orderBy('id');
        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '') {
            $query->where(fn ($builder) => $builder
                ->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('billing_name', 'like', "%{$term}%")
                ->orWhereHas('enquiry', fn ($order) => $order->where('reference', 'like', "%{$term}%")));
        }

        $today = today();
        $items = $query->get()->map(function (JaspestFinanceInvoice $invoice) use ($today): array {
            $resource = $this->invoiceResource($invoice);
            $late = $invoice->due_date && $invoice->due_date->lt($today) ? (int) $invoice->due_date->diffInDays($today) : 0;
            $resource['days_overdue'] = $late;
            $resource['bucket'] = match (true) {
                $late === 0 => 'current',
                $late <= 30 => 'd1_30',
                $late <= 60 => 'd31_60',
                $late <= 90 => 'd61_90',
                default => 'd90_plus',
            };
            unset($resource['payments']);
            return $resource;
        });

        $buckets = [];
        foreach (['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'] as $bucket) {
            $group = $items->where('bucket', $bucket);
            $buckets[$bucket] = ['total' => round((float) $group->sum('outstanding_amount'), 2), 'count' => $group->count()];
        }

        $customers = $items->groupBy('billing_name')->map(fn ($rows, $name): array => [
            'name' => $name,
            'invoice_count' => $rows->count(),
            'outstanding' => round((float) $rows->sum('outstanding_amount'), 2),
            'max_days_overdue' => (int) $rows->max('days_overdue'),
        ])->sortByDesc('outstanding')->values();

        $bucketFilter = (string) ($validated['bucket'] ?? 'all');
        $rows = $bucketFilter === 'all' ? $items : $items->where('bucket', $bucketFilter)->values();

        return response()->json([
            'summary' => [
                'outstanding' => round((float) $items->sum('outstanding_amount'), 2),
                'invoice_count' => $items->count(),
                'overdue_total' => round((float) $items->where('days_overdue', '>', 0)->sum('outstanding_amount'), 2),
                'overdue_count' => $items->where('days_overdue', '>', 0)->count(),
                'customer_count' => $customers->count(),
            ],
            'buckets' => $buckets,
            'customers' => $customers->take(10)->values(),
            'data' => $rows->values(),
        ]);
    }

    /** @return array{0:string,1:string} kunci & label akun kas/rekening penerima sebuah pembayaran. */
    private function cashAccountOf(JaspestFinancePayment $payment): array
    {
        if ($payment->receiving_account_code) {
            return ['cash:'.$payment->receiving_account_code, trim($payment->receiving_account_code.' · '.$payment->receiving_account_name)];
        }
        if ($payment->received_bank_account_id || $payment->received_bank_name) {
            $label = trim(($payment->received_bank_name ?? 'Bank').' '.($payment->received_bank_account_number ?? '')
                .($payment->received_bank_account_holder ? ' · '.$payment->received_bank_account_holder : ''));
            return ['bank:'.($payment->received_bank_account_id ?? $payment->received_bank_name), $label];
        }
        return ['other', 'Akun belum dicatat'];
    }

    /** Export matching invoices and their payment movements into a formatted three-sheet workbook. */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'partial', 'overdue'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $status = (string) ($validated['status'] ?? 'all');
        $query = JaspestFinanceInvoice::query()->with(['enquiry:id,reference', 'payments'])->latest('invoice_date')->latest('id');
        if ($status === 'paid') {
            $query->whereRaw('total_amount <= (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        } elseif ($status === 'unpaid') {
            $query->whereRaw('COALESCE((SELECT SUM(amount) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id), 0) = 0');
        } elseif ($status === 'partial') {
            $query->whereRaw('COALESCE((SELECT SUM(amount) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id), 0) > 0')
                ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        } elseif ($status === 'overdue') {
            $query->whereDate('due_date', '<', today())
                ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)');
        }
        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '') {
            $query->where(fn ($builder) => $builder
                ->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('billing_name', 'like', "%{$term}%")
                ->orWhere('location_name', 'like', "%{$term}%")
                ->orWhereHas('enquiry', fn ($order) => $order->where('reference', 'like', "%{$term}%")));
        }

        $invoices = $query->get();
        $invoiceRows = [];
        $paymentRows = [];
        $grandTotal = 0.0;
        $grandPaid = 0.0;
        $statusCounts = ['Lunas' => 0, 'Dibayar Sebagian' => 0, 'Belum Dibayar' => 0, 'Jatuh Tempo' => 0];
        foreach ($invoices as $invoice) {
            $paid = (float) $invoice->payments->sum('amount');
            $total = (float) $invoice->total_amount;
            $outstanding = max(0, round($total - $paid, 2));
            $invoiceStatus = $paid >= $total ? 'Lunas' : ($paid > 0 ? 'Dibayar Sebagian' : 'Belum Dibayar');
            if ($outstanding > 0 && $invoice->due_date?->lt(today())) $invoiceStatus = 'Jatuh Tempo';
            $statusCounts[$invoiceStatus] = ($statusCounts[$invoiceStatus] ?? 0) + 1;
            $grandTotal += $total;
            $grandPaid += $paid;
            $invoiceRows[] = [
                $invoice->invoice_number, $invoice->enquiry?->reference, $invoice->invoice_date?->format('d/m/Y'),
                $invoice->due_date?->format('d/m/Y'), $invoice->billing_name, $invoice->billing_address,
                $invoice->job_type, $invoice->location_name, $invoice->job_address,
                $invoice->work_period, $invoice->bank_name, $invoice->bank_account_number, $invoice->bank_account_holder,
                (float) $invoice->work_amount, (float) $invoice->ppn_rate, (float) $invoice->ppn_amount,
                $total, $paid, $outstanding, $invoiceStatus, $invoice->amount_words,
            ];
            foreach ($invoice->payments as $payment) {
                $paymentRows[] = [
                    $invoice->invoice_number, $invoice->enquiry?->reference, $invoice->billing_name,
                    $payment->payment_date?->format('d/m/Y'), (float) $payment->amount,
                    $this->paymentMethodLabel((string) $payment->method), $payment->reference,
                    $payment->receiving_account_code, $payment->receiving_account_name,
                    $payment->received_bank_name, $payment->received_bank_account_number,
                    $payment->received_bank_account_holder, $payment->notes,
                ];
            }
        }

        $statusLabel = [
            'all' => 'Semua Status', 'paid' => 'Lunas', 'unpaid' => 'Belum Dibayar',
            'partial' => 'Dibayar Sebagian', 'overdue' => 'Jatuh Tempo',
        ][$status] ?? 'Semua Status';
        $filterText = 'Status: '.$statusLabel.' | Pencarian: '.($term !== '' ? $term : 'Semua');
        $exportedAt = now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H:i');
        $summaryText = 'Ekspor '.$exportedAt.' WIB | Total invoice: '.count($invoiceRows)
            .' | Nilai invoice: Rp'.number_format($grandTotal, 2, ',', '.')
            .' | Terbayar: Rp'.number_format($grandPaid, 2, ',', '.')
            .' | Piutang: Rp'.number_format(max(0, $grandTotal - $grandPaid), 2, ',', '.');

        $workbook = JaspestFinanceXlsx::build([
            [
                'name' => 'Ringkasan',
                'headers' => ['Indikator Keuangan', 'Nilai', 'Keterangan'],
                'rows' => [
                    ['Jumlah invoice', count($invoiceRows), 'Jumlah invoice sesuai filter ekspor'],
                    ['Nilai invoice (Rp)', $grandTotal, 'Total nilai invoice yang diterbitkan'],
                    ['Pembayaran diterima (Rp)', $grandPaid, 'Akumulasi pembayaran yang tercatat'],
                    ['Piutang berjalan (Rp)', max(0, $grandTotal - $grandPaid), 'Nilai invoice yang belum tertagih lunas'],
                    ['Invoice lunas', $statusCounts['Lunas'], 'Status lunas'],
                    ['Invoice dibayar sebagian', $statusCounts['Dibayar Sebagian'], 'Masih memiliki sisa piutang'],
                    ['Invoice belum dibayar', $statusCounts['Belum Dibayar'], 'Belum ada pembayaran tercatat'],
                    ['Invoice jatuh tempo', $statusCounts['Jatuh Tempo'], 'Belum lunas dan melewati tanggal jatuh tempo'],
                    ['Transaksi pembayaran', count($paymentRows), 'Jumlah baris riwayat pembayaran'],
                ],
                'numeric_columns' => [1],
                'widths' => [34, 24, 60],
            ],
            [
                'name' => 'Daftar Invoice',
                'headers' => [
                    'Nomor Invoice', 'Referensi Order', 'Tanggal Invoice', 'Jatuh Tempo', 'Nama Penagihan',
                    'Alamat Penagihan', 'Jenis Pekerjaan', 'Lokasi', 'Alamat Lokasi', 'Periode Pekerjaan',
                    'Bank Tujuan', 'Nomor Rekening', 'Atas Nama', 'Nilai Pekerjaan (Rp)', 'PPN (%)', 'Nilai PPN (Rp)',
                    'Total Invoice (Rp)', 'Terbayar (Rp)', 'Sisa Piutang (Rp)', 'Status', 'Terbilang',
                ],
                'rows' => $invoiceRows,
                'numeric_columns' => [13, 14, 15, 16, 17, 18],
                'widths' => [25, 20, 15, 15, 28, 42, 28, 25, 42, 22, 16, 24, 28, 20, 12, 18, 20, 20, 20, 20, 48],
            ],
            [
                'name' => 'Riwayat Pembayaran',
                'headers' => [
                    'Nomor Invoice', 'Referensi Order', 'Nama Pelanggan', 'Tanggal Pembayaran', 'Nominal (Rp)',
                    'Metode', 'Nomor Referensi', 'Kode Akun', 'Nama Akun', 'Bank Penerima',
                    'Nomor Rekening Penerima', 'Atas Nama', 'Catatan',
                ],
                'rows' => $paymentRows,
                'numeric_columns' => [4],
                'widths' => [25, 20, 28, 18, 20, 20, 24, 16, 36, 18, 26, 28, 44],
            ],
        ], 'JASPEST | LAPORAN KEUANGAN & AKUNTANSI', $summaryText, $filterText);

        $filename = 'JASPEST-Laporan-Keuangan-'.now('Asia/Jakarta')->format('Y-m-d').'.xlsx';
        return response()->streamDownload(
            static function () use ($workbook): void { echo $workbook; },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'transfer' => 'TRANSFER', 'cash' => 'TUNAI', 'bg' => 'BG (BILYET GIRO)',
            'cheque_giro' => 'CHEQUE / BILYET GIRO', 'other' => 'LAINNYA', default => strtoupper($method),
        };
    }

    public function printInvoice(JaspestFinanceInvoice $invoice): View
    {
        $invoice->load(['enquiry', 'payments']);
        $paidAmount = (float) $invoice->payments->sum('amount');

        return view('jaspest.admin.finance.invoice-print', [
            'invoice' => $invoice,
            'paidAmount' => $paidAmount,
            'remainingAmount' => max(0, (float) $invoice->total_amount - $paidAmount),
        ]);
    }

    private function invoiceResource(JaspestFinanceInvoice $invoice): array
    {
        $paid = (float) ($invoice->payments?->sum('amount') ?? 0);
        $total = (float) $invoice->total_amount;
        $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'order_reference' => $invoice->enquiry?->reference,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'billing_name' => $invoice->billing_name,
            'job_type' => $invoice->job_type,
            'location_name' => $invoice->location_name,
            'bank_account_id' => $invoice->bank_account_id,
            'bank_name' => $invoice->bank_name,
            'bank_account_number' => $invoice->bank_account_number,
            'bank_account_holder' => $invoice->bank_account_holder,
            'work_amount' => (float) $invoice->work_amount,
            'ppn_rate' => (float) $invoice->ppn_rate,
            'ppn_amount' => (float) $invoice->ppn_amount,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'outstanding_amount' => max(0, round($total - $paid, 2)),
            'status' => $status,
            'is_overdue' => $status !== 'paid' && $invoice->due_date?->lt(today()),
            'payments' => ($invoice->payments ?? collect())->map(fn (JaspestFinancePayment $payment): array => [
                'id' => $payment->id,
                'date' => $payment->payment_date?->format('Y-m-d'),
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'receiving_account_code' => $payment->receiving_account_code,
                'receiving_account_name' => $payment->receiving_account_name,
                'received_bank_name' => $payment->received_bank_name,
                'received_bank_account_number' => $payment->received_bank_account_number,
                'received_bank_account_holder' => $payment->received_bank_account_holder,
            ])->values(),
        ];
    }

    private function defaultWorkPeriod(JaspestEnquiry $order, ?JaspestContract $contract): string
    {
        if ($contract) {
            $start = Carbon::parse($contract->start_date)->locale('id')->translatedFormat('F Y');
            $end = Carbon::parse($contract->end_date)->locale('id')->translatedFormat('F Y');
            return $start === $end ? $start : $start.' - '.$end;
        }
        $dates = collect($order->serviceSchedules())->pluck('scheduled_date')->filter()
            ->map(fn ($date) => Carbon::parse($date))->sortBy(fn (Carbon $date) => $date->timestamp)->values();
        if ($dates->isEmpty()) {
            return $order->converted_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('F Y') ?? now('Asia/Jakarta')->locale('id')->translatedFormat('F Y');
        }
        $first = $dates->first()->locale('id')->translatedFormat('F Y');
        $last = $dates->last()->locale('id')->translatedFormat('F Y');
        return $first === $last ? $first : $first.' - '.$last;
    }

    private function spellOutRupiah(float $amount): string
    {
        $whole = (int) floor($amount + 0.000001);
        $text = trim($this->spellNumber($whole));
        $decimal = (int) round(($amount - $whole) * 100);
        if ($decimal > 0) {
            $text .= ' rupiah '.trim($this->spellNumber($decimal)).' sen';
        } else {
            $text .= ' rupiah';
        }
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private function spellNumber(int $number): string
    {
        $names = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($number < 12) return $names[$number];
        if ($number < 20) return $this->spellNumber($number - 10).' belas';
        if ($number < 100) return $this->spellNumber(intdiv($number, 10)).' puluh '.($number % 10 ? $this->spellNumber($number % 10) : '');
        if ($number < 200) return 'seratus '.($number - 100 ? $this->spellNumber($number - 100) : '');
        if ($number < 1000) return $this->spellNumber(intdiv($number, 100)).' ratus '.($number % 100 ? $this->spellNumber($number % 100) : '');
        if ($number < 2000) return 'seribu '.($number - 1000 ? $this->spellNumber($number - 1000) : '');
        if ($number < 1000000) return $this->spellNumber(intdiv($number, 1000)).' ribu '.($number % 1000 ? $this->spellNumber($number % 1000) : '');
        if ($number < 1000000000) return $this->spellNumber(intdiv($number, 1000000)).' juta '.($number % 1000000 ? $this->spellNumber($number % 1000000) : '');
        if ($number < 1000000000000) return $this->spellNumber(intdiv($number, 1000000000)).' miliar '.($number % 1000000000 ? $this->spellNumber($number % 1000000000) : '');
        return $this->spellNumber(intdiv($number, 1000000000000)).' triliun '.($number % 1000000000000 ? $this->spellNumber($number % 1000000000000) : '');
    }
}

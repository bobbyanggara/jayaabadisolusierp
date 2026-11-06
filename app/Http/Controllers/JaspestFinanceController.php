<?php

namespace App\Http\Controllers;

use App\Models\JaspestContract;
use App\Models\JaspestContractVisit;
use App\Models\JaspestEnquiry;
use App\Models\JaspestFinanceBankAccount;
use App\Models\JaspestFinanceExpense;
use App\Models\JaspestFinanceInvoice;
use App\Models\JaspestFinancePayment;
use App\Services\JaspestContractService;
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
            JaspestFinanceExpense::query()->where('bank_account_id', $account->id)->update(['bank_account_id' => null]);
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
                ->whereNotIn('id', JaspestFinanceInvoice::query()->select('enquiry_id'))
                ->whereNotIn('id', JaspestContract::query()->whereNotNull('enquiry_id')->select('enquiry_id'))->count(),
        ]);
    }

    public function orders(): JsonResponse
    {
        $orders = JaspestEnquiry::query()
            ->where('status', 'converted')
            ->whereNotNull('order_price')->where('order_price', '>', 0)
            ->whereNotIn('id', JaspestFinanceInvoice::query()->select('enquiry_id'))
            // Order kontrak berkala ditagih per kunjungan (tab "Kunjungan Siap Ditagihkan"), bukan sekali penuh.
            ->whereNotIn('id', JaspestContract::query()->whereNotNull('enquiry_id')->select('enquiry_id'))
            ->with('clientLink.client')
            ->latest('converted_at')->latest('id')
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
        $query = JaspestFinanceInvoice::query()->with(['enquiry:id,reference', 'payments', 'contract:id,contract_number'])->withCount('visits');
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
            'job_type' => ['required', 'string', 'max:250'],
            'job_address' => ['required', 'string', 'max:3000'],
            'work_period' => ['nullable', 'string', 'max:180'],
            'work_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('jaspest_finance_bank_accounts', 'id')->where('is_active', true)],
        ], [
            'bank_account_id.exists' => 'Rekening tujuan tidak aktif atau tidak ditemukan pada Master Data Rekening.',
        ]);

        $jobAddress = trim((string) $validated['job_address']);

        $requestedBankAccountId = isset($validated['bank_account_id']) && $validated['bank_account_id'] !== ''
            ? (int) $validated['bank_account_id']
            : (int) ($references['default_invoice_bank_account_id'] ?? 0);
        $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->find($requestedBankAccountId);
        if (! $bankAccountModel && empty($validated['bank_account_id'])) {
            $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        }
        $bankAccount = $bankAccountModel?->toArray();

        if (! $jobAddress) {
            throw ValidationException::withMessages([
                'job_address' => 'Alamat pekerjaan wajib diisi sebelum invoice diterbitkan.',
            ]);
        }
        if (! $bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Pilih rekening aktif dari Master Data Rekening sebelum menerbitkan invoice.',
            ]);
        }

        $invoice = DB::transaction(function () use ($enquiry, $validated, $jobAddress, $bankAccount): JaspestFinanceInvoice {
            $order = JaspestEnquiry::query()->lockForUpdate()->findOrFail($enquiry->id);
            if ($order->status !== 'converted' || (float) $order->order_price <= 0) {
                throw ValidationException::withMessages(['order' => 'Invoice hanya dapat dibuat dari Deal Order yang memiliki nilai order valid.']);
            }
            if (JaspestFinanceInvoice::query()->where('enquiry_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Order ini sudah memiliki invoice.']);
            }
            if (JaspestContract::query()->where('enquiry_id', $order->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Order ini adalah kontrak berkala. Tagih per kunjungan dari tab "Kunjungan Siap Ditagihkan".']);
            }

            // Nilai pekerjaan boleh diisi manual (mis. pembayaran sebagian); bila kosong memakai nilai Deal Order.
            $workAmount = round((float) (($validated['work_amount'] ?? null) ?: $order->order_price), 2);
            $ppnRate = round((float) ($validated['ppn_rate'] ?? 0), 2);
            $ppnAmount = round($workAmount * $ppnRate / 100, 2);
            $totalAmount = round($workAmount + $ppnAmount, 2);

            $invoice = JaspestFinanceInvoice::query()->create([
                'invoice_number' => null,
                'enquiry_id' => $order->id,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'billing_name' => trim($validated['billing_name']),
                'billing_address' => '', // alamat penerima sudah dihapus dari form
                'job_type' => trim($validated['job_type']),
                'location_name' => '', // kolom lokasi/gedung sudah dihapus dari form
                'job_address' => $jobAddress,
                'bank_account_id' => (int) $bankAccount['id'],
                'bank_name' => trim((string) $bankAccount['bank_name']),
                'bank_account_number' => trim((string) $bankAccount['account_number']),
                'bank_account_holder' => trim((string) $bankAccount['account_holder']),
                'work_period' => trim((string) ($validated['work_period'] ?? '')) ?: $this->defaultWorkPeriod($enquiry, null),
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

    /**
     * Semua Deal Order untuk dipilih pada form "Buat Invoice" (manual maupun otomatis).
     * Order kontrak dilengkapi daftar kunjungan bernomor urut (kunjungan ke-1, ke-2, ...) beserta status tagihannya.
     */
    public function dealOrders(): JsonResponse
    {
        $orders = JaspestEnquiry::query()
            ->where('status', 'converted')
            ->with('clientLink.client')
            ->latest('converted_at')->latest('id')
            ->get();

        $contracts = JaspestContract::query()->whereIn('enquiry_id', $orders->pluck('id'))->get()->keyBy('enquiry_id');
        $visits = JaspestContractVisit::query()
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->with('invoice:id,invoice_number')
            ->orderBy('is_extra')->orderBy('period_index')->orderBy('slot_no')->orderBy('id')
            ->get()->groupBy('contract_id');
        $plainInvoices = JaspestFinanceInvoice::query()
            ->whereNull('contract_id')->whereIn('enquiry_id', $orders->pluck('id'))
            ->pluck('invoice_number', 'enquiry_id');

        return response()->json(['data' => $orders->map(function (JaspestEnquiry $order) use ($contracts, $visits, $plainInvoices): array {
            $client = $order->clientLink?->client;
            $contract = $contracts->get($order->id);
            $name = $order->company_name ?: $order->full_name;
            $row = [
                'id' => $order->reference,
                'customer_name' => $name,
                'billing_name' => $name,
                'billing_address' => $client?->address ?: $order->address,
                'client_code' => $client?->client_code,
                'job_type' => $contract ? $this->contractJobType($contract, $order) : $order->serviceTitleLabel(),
                'job_address' => $order->address,
                'work_period' => $this->defaultWorkPeriod($order, $contract),
                'order_price' => (float) $order->order_price,
                'converted_at' => $order->converted_at?->copy()->timezone('Asia/Jakarta')->toDateString(),
                'is_contract' => (bool) $contract,
                'contract_id' => $contract?->id,
                'contract_number' => $contract?->contract_number,
                'period_unit' => $contract ? (JaspestContractService::PERIODS[$contract->period_type]['unit'] ?? 'Periode') : null,
                'unit_price' => (float) $order->order_price,
                'visits' => [],
                'can_bill' => false,
                'blocked_reason' => null,
                'auto' => null,
            ];

            $legacyNumber = $plainInvoices->get($order->id);
            if (! $contract) {
                if ($legacyNumber) {
                    $row['blocked_reason'] = 'Sudah ditagih pada invoice '.$legacyNumber;
                } elseif ((float) $order->order_price <= 0) {
                    $row['blocked_reason'] = 'Nilai order belum diisi';
                } else {
                    $row['can_bill'] = true;
                    $row['auto'] = ['visit_ids' => [], 'sequences' => [], 'amount' => (float) $order->order_price];
                }

                return $row;
            }

            $list = $visits->get($contract->id, collect());
            $unit = $contract->unitPrice();
            $row['unit_price'] = $unit;
            $row['visits'] = $list->values()->map(fn (JaspestContractVisit $visit, int $index): array => [
                'id' => $visit->id,
                'sequence' => $index + 1,
                'period_index' => (int) $visit->period_index,
                'period_label' => $this->visitPeriodLabel($visit, $contract->period_type),
                'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
                'status' => $visit->status,
                'is_extra' => (bool) $visit->is_extra,
                'billed' => $visit->invoice_id !== null,
                'invoice_number' => $visit->invoice?->invoice_number,
                'billable' => $this->isBillableVisit($visit),
            ])->all();

            if ($legacyNumber) {
                $row['blocked_reason'] = 'Sudah ditagih penuh pada invoice '.$legacyNumber;

                return $row;
            }
            $plan = $this->autoVisitPlan($list);
            if ($plan->isEmpty()) {
                $row['blocked_reason'] = 'Semua kunjungan sudah ditagih';

                return $row;
            }
            $sequenceById = $list->values()->mapWithKeys(fn (JaspestContractVisit $visit, int $index): array => [$visit->id => $index + 1]);
            $row['can_bill'] = true;
            $row['auto'] = [
                'visit_ids' => $plan->pluck('id')->all(),
                'sequences' => $plan->map(fn (JaspestContractVisit $visit): int => $sequenceById[$visit->id])->all(),
                'amount' => round($unit * $plan->count(), 2),
            ];

            return $row;
        })->values()]);
    }

    /**
     * Invoice otomatis: semua data diisi dari Deal Order dan langsung diterbitkan.
     * Order kontrak: semua kunjungan yang sudah selesai dan belum ditagih; bila belum ada, kunjungan berikutnya
     * yang belum ditagih. Order sekali: nilai order penuh.
     */
    public function createAutoInvoice(Request $request, JaspestEnquiry $enquiry): JsonResponse
    {
        if ($enquiry->status !== 'converted') {
            throw ValidationException::withMessages(['order' => 'Invoice hanya dapat dibuat dari Deal Order.']);
        }
        $enquiry->load('clientLink.client');
        $client = $enquiry->clientLink?->client;
        $name = (string) ($enquiry->company_name ?: $enquiry->full_name);
        $today = now('Asia/Jakarta');
        $contract = JaspestContract::query()->where('enquiry_id', $enquiry->id)->first();

        $payload = [
            'invoice_date' => $today->format('Y-m-d'),
            'due_date' => $today->copy()->addDays(30)->format('Y-m-d'),
            'billing_name' => $name,
            'billing_address' => (string) ($client?->address ?: $enquiry->address),
            'job_type' => $contract ? $this->contractJobType($contract, $enquiry) : $enquiry->serviceTitleLabel(),
            'job_address' => (string) $enquiry->address,
            'ppn_rate' => 0,
        ];

        if (! $contract) {
            $payload['work_period'] = $this->defaultWorkPeriod($enquiry, null);

            return $this->createInvoice(Request::create('/', 'POST', $payload), $enquiry);
        }

        if (JaspestFinanceInvoice::query()->where('enquiry_id', $enquiry->id)->whereNull('contract_id')->exists()) {
            throw ValidationException::withMessages(['contract' => 'Order kontrak ini sudah ditagih penuh lewat invoice lama.']);
        }
        $visits = JaspestContractVisit::query()->where('contract_id', $contract->id)
            ->orderBy('is_extra')->orderBy('period_index')->orderBy('slot_no')->orderBy('id')->get();
        $plan = $this->autoVisitPlan($visits);
        if ($plan->isEmpty()) {
            throw ValidationException::withMessages(['visits' => 'Tidak ada kunjungan yang tersisa untuk ditagih pada kontrak ini.']);
        }
        $unit = $contract->unitPrice();
        $payload['visits'] = $plan->map(fn (JaspestContractVisit $visit): array => ['id' => $visit->id, 'amount' => $unit > 0 ? $unit : null])->all();
        $payload['work_period'] = $this->visitsWorkPeriod($plan);

        return $this->createContractInvoice(Request::create('/', 'POST', $payload), $contract);
    }

    private function isBillableVisit(JaspestContractVisit $visit): bool
    {
        return $visit->invoice_id === null && in_array($visit->status, ['unscheduled', 'scheduled', 'done'], true);
    }

    /** Kunjungan yang ditagih otomatis: semua yang sudah selesai; bila belum ada, satu kunjungan berikutnya. */
    private function autoVisitPlan(\Illuminate\Support\Collection $visits): \Illuminate\Support\Collection
    {
        $open = $visits->filter(fn (JaspestContractVisit $visit): bool => $this->isBillableVisit($visit))->values();
        $done = $open->where('status', 'done')->values();

        return $done->isNotEmpty() ? $done : $open->take(1)->values();
    }

    /** Keterangan periode sebuah kunjungan, mis. "November 2026" (bulanan) atau "05 Nov 2026 - 11 Nov 2026". */
    private function visitPeriodLabel(JaspestContractVisit $visit, ?string $periodType): string
    {
        $start = $visit->period_start?->copy()->locale('id');
        $end = $visit->period_end?->copy()->locale('id');
        if (! $start) {
            return '';
        }
        if ($periodType === 'monthly' || ! $end) {
            return $start->translatedFormat('F Y');
        }

        return $start->translatedFormat('d M Y').' - '.$end->translatedFormat('d M Y');
    }

    /**
     * Kontrak yang punya kunjungan selesai tetapi belum ditagih. Dasar layar "Tagih per Kunjungan":
     * nilai tiap kunjungan = harga per kunjungan kontrak (bisa diubah admin saat membuat invoice).
     */
    public function billableContracts(): JsonResponse
    {
        // Kontrak muncul sejak dibuat: kunjungan yang belum dijadwalkan, terjadwal, maupun selesai dapat ditagih.
        // Kunjungan yang dilewati/dibatalkan tidak ikut.
        $billableStatuses = ['unscheduled', 'scheduled', 'done'];
        $contracts = JaspestContract::query()
            ->whereHas('visits', fn ($q) => $q->whereIn('status', $billableStatuses)->whereNull('invoice_id'))
            ->with('enquiry.clientLink.client')
            ->orderByDesc('id')
            ->get();

        $visits = JaspestContractVisit::query()
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->whereIn('status', $billableStatuses)->whereNull('invoice_id')
            ->orderBy('is_extra')->orderBy('period_index')->orderBy('slot_no')
            ->get()->groupBy('contract_id');

        // Order kontrak yang sudah ditagih penuh lewat invoice lama (tanpa contract_id) tidak boleh ditagih lagi per kunjungan.
        $legacyBilled = JaspestFinanceInvoice::query()
            ->whereIn('enquiry_id', $contracts->pluck('enquiry_id')->filter()->all())
            ->whereNull('contract_id')
            ->pluck('enquiry_id')->flip();

        return response()->json(['data' => $contracts->map(function (JaspestContract $contract) use ($visits, $legacyBilled): array {
            $order = $contract->enquiry;
            $client = $order?->clientLink?->client;
            $unit = $contract->unitPrice();
            $list = $visits->get($contract->id, collect());

            return [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'order_reference' => $order?->reference,
                'status' => $contract->status,
                'client_code' => $client?->client_code,
                'billing_name' => $order ? ($order->company_name ?: $order->full_name) : '',
                'billing_address' => $client?->address ?: ($order?->address ?? ''),
                'job_type' => $this->contractJobType($contract, $order),
                'job_address' => $order?->address ?? '',
                'unit_price' => $unit,
                'period_unit' => JaspestContractService::PERIODS[$contract->period_type]['unit'] ?? 'Periode',
                'legacy_full_invoice' => $contract->enquiry_id !== null && $legacyBilled->has($contract->enquiry_id),
                'unbilled_visit_count' => $list->count(),
                'done_visit_count' => $list->where('status', 'done')->count(),
                'visits' => $list->map(fn (JaspestContractVisit $visit): array => [
                    'id' => $visit->id,
                    'period_index' => (int) $visit->period_index,
                    'period_label' => $this->visitPeriodLabel($visit, $contract->period_type),
                    'slot_no' => (int) $visit->slot_no,
                    'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
                    'status' => $visit->status,
                    'is_extra' => (bool) $visit->is_extra,
                    'suggested_amount' => $unit,
                ])->values(),
                'suggested_work_period' => $this->visitsWorkPeriod($list),
            ];
        })->values()]);
    }

    /** Buat satu invoice dari kunjungan kontrak yang dipilih (bisa satu bulan, atau beberapa bulan sekaligus). */
    public function createContractInvoice(Request $request, JaspestContract $contract): JsonResponse
    {
        $references = config('jaspest_finance', []);

        $validated = $request->validate([
            'visits' => ['required', 'array', 'min:1', 'max:200'],
            'visits.*.id' => ['required', 'integer', 'distinct'],
            'visits.*.amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:invoice_date'],
            'billing_name' => ['required', 'string', 'max:200'],
            'job_type' => ['required', 'string', 'max:250'],
            'job_address' => ['required', 'string', 'max:3000'],
            'work_period' => ['nullable', 'string', 'max:180'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('jaspest_finance_bank_accounts', 'id')->where('is_active', true)],
        ], [
            'visits.required' => 'Pilih minimal satu kunjungan yang akan ditagih.',
            'visits.min' => 'Pilih minimal satu kunjungan yang akan ditagih.',
            'bank_account_id.exists' => 'Rekening tujuan tidak aktif atau tidak ditemukan pada Master Data Rekening.',
        ]);

        $requestedBankAccountId = isset($validated['bank_account_id']) && $validated['bank_account_id'] !== ''
            ? (int) $validated['bank_account_id']
            : (int) ($references['default_invoice_bank_account_id'] ?? 0);
        $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->find($requestedBankAccountId);
        if (! $bankAccountModel && empty($validated['bank_account_id'])) {
            $bankAccountModel = JaspestFinanceBankAccount::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        }
        $bankAccount = $bankAccountModel?->toArray();
        if (! $bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Pilih rekening aktif dari Master Data Rekening sebelum menerbitkan invoice.',
            ]);
        }

        $requested = collect($validated['visits'])->mapWithKeys(static fn (array $row): array => [
            (int) $row['id'] => isset($row['amount']) && $row['amount'] !== '' ? round((float) $row['amount'], 2) : null,
        ]);

        $invoice = DB::transaction(function () use ($contract, $validated, $bankAccount, $requested): JaspestFinanceInvoice {
            $locked = JaspestContract::query()->lockForUpdate()->findOrFail($contract->id);
            $order = $locked->enquiry_id ? JaspestEnquiry::query()->lockForUpdate()->find($locked->enquiry_id) : null;
            if (! $order) {
                throw ValidationException::withMessages(['contract' => 'Kontrak ini tidak terhubung ke Deal Order, sehingga tidak dapat ditagih.']);
            }
            if (JaspestFinanceInvoice::query()->where('enquiry_id', $order->id)->whereNull('contract_id')->exists()) {
                throw ValidationException::withMessages(['contract' => 'Order kontrak ini sudah ditagih penuh lewat invoice lama. Tagih per kunjungan dinonaktifkan agar tidak terjadi tagihan ganda.']);
            }

            $visits = JaspestContractVisit::query()
                ->where('contract_id', $locked->id)->whereIn('id', $requested->keys()->all())
                ->lockForUpdate()->get()->keyBy('id');
            if ($visits->count() !== $requested->count()) {
                throw ValidationException::withMessages(['visits' => 'Ada kunjungan yang tidak termasuk dalam kontrak ini.']);
            }
            foreach ($visits as $visit) {
                if ($visit->invoice_id !== null) {
                    throw ValidationException::withMessages(['visits' => 'Ada kunjungan yang sudah masuk ke invoice lain. Muat ulang daftar kunjungan.']);
                }
                if (! in_array($visit->status, ['unscheduled', 'scheduled', 'done'], true)) {
                    throw ValidationException::withMessages(['visits' => 'Kunjungan yang dilewati atau dibatalkan tidak dapat ditagih.']);
                }
            }

            $unit = $locked->unitPrice();
            $amounts = $requested->map(static fn (?float $override): float => $override ?? $unit);
            if ($amounts->contains(static fn (float $amount): bool => $amount <= 0)) {
                throw ValidationException::withMessages(['visits' => 'Nilai kunjungan belum ada. Isi nominal tagihan kunjungan secara manual.']);
            }

            $workAmount = round((float) $amounts->sum(), 2);
            $ppnRate = round((float) ($validated['ppn_rate'] ?? 0), 2);
            $ppnAmount = round($workAmount * $ppnRate / 100, 2);
            $totalAmount = round($workAmount + $ppnAmount, 2);
            $workPeriod = trim((string) ($validated['work_period'] ?? '')) ?: $this->visitsWorkPeriod($visits->values());

            $invoice = JaspestFinanceInvoice::query()->create([
                'invoice_number' => null,
                'enquiry_id' => $order->id,
                'contract_id' => $locked->id,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'billing_name' => trim($validated['billing_name']),
                'billing_address' => '', // alamat penerima sudah dihapus dari form
                'job_type' => trim($validated['job_type']),
                'location_name' => '', // kolom lokasi/gedung sudah dihapus dari form
                'job_address' => trim($validated['job_address']),
                'bank_account_id' => (int) $bankAccount['id'],
                'bank_name' => trim((string) $bankAccount['bank_name']),
                'bank_account_number' => trim((string) $bankAccount['account_number']),
                'bank_account_holder' => trim((string) $bankAccount['account_holder']),
                'work_period' => $workPeriod,
                'work_amount' => $workAmount,
                'ppn_rate' => $ppnRate,
                'ppn_amount' => $ppnAmount,
                'total_amount' => $totalAmount,
                'amount_words' => $this->spellOutRupiah($totalAmount),
                'created_by' => auth()->id(),
            ]);

            foreach ($visits as $visit) {
                $visit->forceFill(['invoice_id' => $invoice->id, 'billed_amount' => $amounts[$visit->id]])->save();
            }

            $order->load('clientLink.client');
            $clientCode = $order->clientLink?->client?->client_code ?: 'ORD'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
            $date = Carbon::parse($validated['invoice_date']);
            $romanMonths = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
            $invoice->forceFill([
                'invoice_number' => str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT).'/INV/'.$clientCode.'/JAS/'.$romanMonths[(int) $date->format('n')].'/'.$date->format('y'),
            ])->save();

            return $invoice;
        }, 3);

        return response()->json($this->invoiceResource($invoice->load(['enquiry', 'payments'])), 201);
    }

    private function contractJobType(JaspestContract $contract, ?JaspestEnquiry $order): string
    {
        $jobType = $order ? $order->serviceTitleLabel() : '';
        if (is_array($contract->services) && $contract->services !== []) {
            $labels = array_values(array_filter(array_map(
                static fn ($item): string => is_array($item) ? trim((string) ($item['title'] ?? $item['slug'] ?? '')) : trim((string) $item),
                $contract->services
            )));
            if ($labels !== []) {
                $jobType = implode(' + ', $labels);
            }
        }

        return $jobType;
    }

    /** Periode pekerjaan dari tanggal kunjungan, mis. "November 2026" atau "November 2026 - Desember 2026". */
    private function visitsWorkPeriod(\Illuminate\Support\Collection $visits): string
    {
        $dates = $visits->map(fn (JaspestContractVisit $visit) => $visit->scheduled_date ?? $visit->period_start)
            ->filter()->sortBy(fn ($date) => $date->timestamp)->values();
        if ($dates->isEmpty()) {
            return now('Asia/Jakarta')->locale('id')->translatedFormat('F Y');
        }
        $first = $dates->first()->copy()->locale('id')->translatedFormat('F Y');
        $last = $dates->last()->copy()->locale('id')->translatedFormat('F Y');

        return $first === $last ? $first : $first.' - '.$last;
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

        $payments = $this->cashPaymentQuery($validated, 'invoice:id,invoice_number,billing_name')->get()->map(function (JaspestFinancePayment $payment): array {
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

    /** Label kategori umur piutang (aging); dipakai tampilan, filter, dan ekspor Excel. */
    private const RECEIVABLE_BUCKETS = [
        'current' => 'Belum Jatuh Tempo',
        'd1_30' => '1–30 Hari',
        'd31_60' => '31–60 Hari',
        'd61_90' => '61–90 Hari',
        'd90_plus' => '> 90 Hari',
    ];

    /**
     * Invoice yang belum lunas beserta umur piutangnya. Dipakai daftar Piutang Usaha dan ekspor Excel
     * supaya keduanya selalu menampilkan angka yang sama. Kunci `payments` dipertahankan untuk ekspor.
     *
     * @param  array{q?:?string}  $validated
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function receivableItems(array $validated)
    {
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

        return $query->get()->map(function (JaspestFinanceInvoice $invoice) use ($today): array {
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

            return $resource;
        });
    }

    /** Piutang: invoice yang belum lunas, umur piutang (aging), dan ringkasan per pelanggan. */
    public function receivables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bucket' => ['nullable', 'in:all,current,d1_30,d31_60,d61_90,d90_plus'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $items = $this->receivableItems($validated)->map(function (array $resource): array {
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

    /**
     * Ekspor Excel Piutang Usaha: ringkasan, aging, aging per pelanggan, dan daftar invoice lengkap.
     * Angkanya berasal dari receivableItems() yang sama dengan tampilan layar, lengkap dengan filter umur & pencarian.
     */
    public function exportReceivables(Request $request)
    {
        $validated = $request->validate([
            'bucket' => ['nullable', 'in:all,current,d1_30,d31_60,d61_90,d90_plus'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $today = today();
        $all = $this->receivableItems($validated);
        $bucketFilter = (string) ($validated['bucket'] ?? 'all');
        $rows = ($bucketFilter === 'all' ? $all : $all->where('bucket', $bucketFilter))->values();

        $sumOf = static fn ($group, string $key): float => round((float) $group->sum($key), 2);
        $share = static fn (float $value, float $total): float => $total > 0 ? round($value / $total, 6) : 0.0;
        $rupiah = static fn (float $value): string => 'Rp'.number_format($value, 0, ',', '.');

        // Angka utama (mengikuti filter yang dipilih).
        $count = $rows->count();
        $grandOutstanding = $sumOf($rows, 'outstanding_amount');
        $grandInvoiced = $sumOf($rows, 'total_amount');
        $grandPaid = $sumOf($rows, 'paid_amount');
        $overdueRows = $rows->where('days_overdue', '>', 0);
        $overdueTotal = $sumOf($overdueRows, 'outstanding_amount');
        $currentTotal = round($grandOutstanding - $overdueTotal, 2);
        $over90 = $sumOf($rows->where('bucket', 'd90_plus'), 'outstanding_amount');
        $weightedLate = $overdueTotal > 0
            ? (int) round($overdueRows->sum(fn (array $row): float => $row['days_overdue'] * $row['outstanding_amount']) / $overdueTotal)
            : 0;
        $nextDue = $rows->where('days_overdue', 0)->pluck('due_date')->filter()->sort()->values()->first();
        $customerCount = $rows->pluck('billing_name')->unique()->count();

        // Teks filter untuk judul lembar kerja.
        $bucketText = match (true) {
            $bucketFilter === 'all' => 'Semua umur piutang',
            $bucketFilter === 'current' => 'Belum jatuh tempo',
            default => 'Terlambat '.(self::RECEIVABLE_BUCKETS[$bucketFilter] ?? $bucketFilter),
        };
        $term = trim((string) ($validated['q'] ?? ''));
        $searchText = $term !== '' ? $term : 'Semua';
        $filterText = 'Posisi piutang: '.$today->format('d/m/Y').' | Umur piutang: '.$bucketText.' | Pencarian: '.$searchText;
        $exportedAt = now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H:i');
        $subtitle = 'Dicetak '.$exportedAt.' WIB | Total piutang: '.$rupiah($grandOutstanding).' | Invoice belum lunas: '.$count;

        // Ringkasan.
        $largest = $count > 0 ? (float) $rows->max('outstanding_amount') : 0.0;
        $summaryRows = [
            ['Posisi piutang per tanggal', ['date', $today->format('Y-m-d')], 'Umur piutang dihitung dari tanggal jatuh tempo sampai tanggal ini'],
            ['Filter umur piutang', ['text', $bucketText], 'Kategori umur yang dipilih saat ekspor'],
            ['Pencarian', ['text', $searchText], 'Nomor invoice, pelanggan, atau referensi order'],
            ['Total piutang usaha (Rp)', ['money', $grandOutstanding], 'Sisa tagihan yang belum dilunasi'],
            ['Total tagihan invoice (Rp)', ['money', $grandInvoiced], 'Nilai penuh invoice yang belum lunas, termasuk PPN'],
            ['Sudah dibayar (Rp)', ['money', $grandPaid], 'Pembayaran sebagian yang sudah diterima'],
            ['Persentase terbayar', ['pct', $share($grandPaid, $grandInvoiced)], 'Sudah dibayar dibagi total tagihan'],
            ['Belum jatuh tempo (Rp)', ['money', $currentTotal], 'Piutang yang masih dalam masa pembayaran'],
            ['Sudah jatuh tempo (Rp)', ['money', $overdueTotal], 'Piutang yang melewati tanggal jatuh tempo'],
            ['Porsi jatuh tempo', ['pct', $share($overdueTotal, $grandOutstanding)], 'Piutang jatuh tempo dibagi total piutang'],
            ['Piutang lewat 90 hari (Rp)', ['money', $over90], 'Piutang berisiko tinggi, perlu penagihan prioritas'],
            ['Jumlah invoice belum lunas', ['int', $count], 'Invoice dengan sisa tagihan'],
            ['Jumlah invoice jatuh tempo', ['int', $overdueRows->count()], 'Invoice yang sudah melewati jatuh tempo'],
            ['Jumlah pelanggan', ['int', $customerCount], 'Pelanggan yang masih memiliki piutang'],
            ['Rata-rata sisa per invoice (Rp)', ['money', $count > 0 ? round($grandOutstanding / $count, 2) : 0.0], 'Total piutang dibagi jumlah invoice'],
            ['Sisa piutang terbesar (Rp)', ['money', $largest], 'Sisa tagihan tertinggi pada satu invoice'],
            ['Keterlambatan terlama (hari)', ['int', (int) $rows->max('days_overdue')], 'Invoice yang paling lama melewati jatuh tempo'],
            ['Rata-rata keterlambatan (hari)', ['int', $weightedLate], 'Dibobot nominal, hanya invoice yang sudah jatuh tempo'],
            ['Jatuh tempo terdekat', ['date', $nextDue], 'Tanggal jatuh tempo berikutnya yang belum lewat'],
        ];

        // Aging piutang: semua kategori umur (tanpa filter umur) agar komposisinya tetap terlihat.
        $allTotal = $sumOf($all, 'outstanding_amount');
        $agingRows = [];
        $number = 0;
        foreach (self::RECEIVABLE_BUCKETS as $key => $label) {
            $group = $all->where('bucket', $key);
            $total = $sumOf($group, 'outstanding_amount');
            $agingRows[] = [++$number, $label, $group->count(), $group->pluck('billing_name')->unique()->count(), $total, $share($total, $allTotal)];
        }

        // Aging per pelanggan.
        $customers = $rows->groupBy('billing_name')->map(function ($group, $name) use ($sumOf): array {
            $entry = [
                'name' => (string) $name,
                'count' => $group->count(),
                'total' => $sumOf($group, 'outstanding_amount'),
                'max_late' => (int) $group->max('days_overdue'),
            ];
            foreach (array_keys(self::RECEIVABLE_BUCKETS) as $key) {
                $entry[$key] = $sumOf($group->where('bucket', $key), 'outstanding_amount');
            }

            return $entry;
        })->sortByDesc('total')->values();
        $customerRows = [];
        foreach ($customers as $index => $customer) {
            $customerRows[] = [
                $index + 1, $customer['name'], $customer['count'],
                $customer['current'], $customer['d1_30'], $customer['d31_60'], $customer['d61_90'], $customer['d90_plus'],
                $customer['total'], $share($customer['total'], $grandOutstanding), $customer['max_late'],
            ];
        }

        // Daftar piutang per invoice (urut dari yang paling lama jatuh tempo).
        $detailRows = [];
        foreach ($rows as $index => $row) {
            $payments = collect($row['payments'] ?? []);
            $lastPaid = $payments->pluck('date')->filter()->sort()->last();
            $late = (int) $row['days_overdue'];
            $status = $late > 0 ? 'Jatuh Tempo' : ((float) $row['paid_amount'] > 0 ? 'Dibayar Sebagian' : 'Belum Dibayar');
            $bank = trim(implode(' ', array_filter([(string) $row['bank_name'], (string) $row['bank_account_number']]))
                .($row['bank_account_holder'] ? ' · '.$row['bank_account_holder'] : ''));

            $detailRows[] = [
                $index + 1, $row['invoice_number'], $row['order_reference'], $row['contract_number'],
                $row['billing_name'], $row['job_type'], $row['location_name'],
                $row['invoice_date'], $row['due_date'], $late, self::RECEIVABLE_BUCKETS[$row['bucket']] ?? '', $status,
                $row['work_amount'], ((float) $row['ppn_rate']) / 100, $row['ppn_amount'],
                $row['total_amount'], $row['paid_amount'], $row['outstanding_amount'],
                $lastPaid, $payments->count(), $bank,
            ];
        }

        $workbook = JaspestFinanceXlsx::build([
            [
                'name' => 'Ringkasan', 'heading' => 'Ringkasan',
                'headers' => ['Indikator', 'Nilai', 'Keterangan'],
                'rows' => $summaryRows,
                'types' => ['text', 'money', 'text'],
                'widths' => [40, 26, 66],
                'show_count' => false,
                'tab' => 'FF102548',
            ],
            [
                'name' => 'Aging Piutang', 'heading' => 'Aging Piutang (semua kategori umur)',
                'headers' => ['No', 'Kategori Umur Piutang', 'Jumlah Invoice', 'Jumlah Pelanggan', 'Sisa Piutang (Rp)', 'Porsi (%)'],
                'rows' => $agingRows,
                'types' => ['center', 'text', 'int', 'int', 'money', 'pct'],
                'widths' => [7, 34, 18, 18, 26, 14],
                'totals' => [2, 4],
                'show_count' => false,
            ],
            [
                'name' => 'Per Pelanggan', 'heading' => 'Aging Piutang per Pelanggan',
                'headers' => [
                    'No', 'Pelanggan', 'Jumlah Invoice', 'Belum Jatuh Tempo (Rp)', '1–30 Hari (Rp)', '31–60 Hari (Rp)',
                    '61–90 Hari (Rp)', '> 90 Hari (Rp)', 'Total Piutang (Rp)', 'Porsi (%)', 'Terlambat Maks. (Hari)',
                ],
                'rows' => $customerRows,
                'types' => ['center', 'text', 'int', 'money', 'money', 'money', 'money', 'money', 'money', 'pct', 'int'],
                'widths' => [6, 38, 16, 24, 20, 20, 20, 20, 24, 12, 18],
                'totals' => [2, 3, 4, 5, 6, 7, 8],
            ],
            [
                'name' => 'Daftar Piutang', 'heading' => 'Daftar Piutang Usaha',
                'headers' => [
                    'No', 'Nomor Invoice', 'Referensi Order', 'Nomor Kontrak', 'Pelanggan', 'Jenis Pekerjaan', 'Lokasi',
                    'Tanggal Invoice', 'Jatuh Tempo', 'Terlambat (Hari)', 'Kategori Umur', 'Status',
                    'Nilai Pekerjaan (Rp)', 'PPN (%)', 'PPN (Rp)', 'Total Tagihan (Rp)', 'Terbayar (Rp)', 'Sisa Piutang (Rp)',
                    'Pembayaran Terakhir', 'Jumlah Pembayaran', 'Rekening Tagihan',
                ],
                'rows' => $detailRows,
                'types' => [
                    'center', 'text', 'text', 'text', 'text', 'text', 'text',
                    'date', 'date', 'int', 'center', 'center',
                    'money', 'pct', 'money', 'money', 'money', 'money',
                    'date', 'int', 'text',
                ],
                'widths' => [6, 24, 20, 22, 32, 34, 28, 15, 15, 14, 20, 18, 22, 10, 20, 22, 20, 22, 17, 14, 40],
                'totals' => [12, 14, 15, 16, 17],
            ],
        ], 'JASPEST | LAPORAN PIUTANG USAHA', $subtitle, $filterText);

        $filename = 'JASPEST-Laporan-Piutang-Usaha-'.now('Asia/Jakarta')->format('Y-m-d').'.xlsx';

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

    /** Query pembayaran masuk dengan filter tanggal dan pencarian; dipakai tampilan Kas & Bank dan ekspornya. */
    private function cashPaymentQuery(array $validated, array|string $with)
    {
        $query = JaspestFinancePayment::query()->with($with)
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

        return $query;
    }

    /** Ekspor Excel Kas & Bank: ringkasan, saldo per akun, per metode, rekap bulanan, dan riwayat kas masuk lengkap. */
    public function exportCash(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'account' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $payments = $this->cashPaymentQuery($validated, ['invoice.enquiry:id,reference', 'invoice.payments'])->get();
        $today = today();
        $all = $payments->map(function (JaspestFinancePayment $payment) use ($today): array {
            [$key, $label] = $this->cashAccountOf($payment);
            $invoice = $payment->invoice;
            $invoiceTotal = (float) ($invoice?->total_amount ?? 0);
            $invoicePaid = (float) ($invoice?->payments->sum('amount') ?? 0);
            $outstanding = max(0, round($invoiceTotal - $invoicePaid, 2));
            $invoiceStatus = $invoicePaid >= $invoiceTotal ? 'Lunas' : ($invoicePaid > 0 ? 'Dibayar Sebagian' : 'Belum Dibayar');
            if ($outstanding > 0 && $invoice?->due_date?->lt($today)) {
                $invoiceStatus = 'Jatuh Tempo';
            }

            return [
                'id' => (int) $payment->id,
                'invoice_id' => (int) ($payment->invoice_id ?? 0),
                'date' => $payment->payment_date?->format('Y-m-d'),
                'amount' => (float) $payment->amount,
                'method' => (string) $payment->method,
                'method_label' => $this->paymentMethodLabel((string) $payment->method),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'account_key' => $key,
                'account_label' => $label,
                'bank_name' => $payment->received_bank_name,
                'bank_number' => $payment->received_bank_account_number,
                'bank_holder' => $payment->received_bank_account_holder,
                'invoice_number' => $invoice?->invoice_number,
                'order_reference' => $invoice?->enquiry?->reference,
                'customer' => $invoice?->billing_name,
                'invoice_date' => $invoice?->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice?->due_date?->format('Y-m-d'),
                'invoice_total' => $invoiceTotal,
                'invoice_paid' => $invoicePaid,
                'invoice_outstanding' => $outstanding,
                'invoice_status' => $invoiceStatus,
            ];
        });

        $accountFilter = (string) ($validated['account'] ?? 'all');
        $grandAll = (float) $all->sum('amount');
        $accounts = $all->groupBy('account_key')->map(fn ($rows): array => [
            'label' => $rows->first()['account_label'],
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('amount'), 2),
        ])->sortByDesc('total')->values();
        $rows = $accountFilter === 'all' ? $all : $all->where('account_key', $accountFilter)->values();
        $rows = $rows->sortBy([['date', 'asc'], ['id', 'asc']])->values();

        $grand = round((float) $rows->sum('amount'), 2);
        $count = $rows->count();
        $cashIn = round((float) $rows->where('method', 'cash')->sum('amount'), 2);
        $bankIn = round((float) $rows->where('method', '!=', 'cash')->sum('amount'), 2);
        $monthStart = $today->copy()->startOfMonth()->format('Y-m-d');
        $monthIn = round((float) $rows->where('date', '>=', $monthStart)->sum('amount'), 2);
        $share = static fn (float $value, float $total): float => $total > 0 ? round($value / $total, 6) : 0.0;
        $rupiah = static fn (float $value): string => 'Rp'.number_format($value, 0, ',', '.');

        // Teks filter untuk judul lembar kerja.
        $from = ! empty($validated['from']) ? Carbon::createFromFormat('Y-m-d', $validated['from'])->format('d/m/Y') : null;
        $to = ! empty($validated['to']) ? Carbon::createFromFormat('Y-m-d', $validated['to'])->format('d/m/Y') : null;
        $periodText = match (true) {
            $from && $to => $from.' s.d. '.$to,
            (bool) $from => 'Sejak '.$from,
            (bool) $to => 'Sampai '.$to,
            default => 'Semua tanggal',
        };
        $accountText = 'Semua akun';
        if ($accountFilter !== 'all') {
            $match = $all->firstWhere('account_key', $accountFilter);
            $accountText = $match['account_label'] ?? $accountFilter;
        }
        $term = trim((string) ($validated['q'] ?? ''));
        $searchText = $term !== '' ? $term : 'Semua';
        $filterText = 'Periode: '.$periodText.' | Akun: '.$accountText.' | Pencarian: '.$searchText;
        $exportedAt = now('Asia/Jakarta')->locale('id')->translatedFormat('d F Y H:i');
        $subtitle = 'Dicetak '.$exportedAt.' WIB | Total kas masuk: '.$rupiah($grand).' | Transaksi: '.$count;

        // Ringkasan.
        $largest = $count > 0 ? (float) $rows->max('amount') : 0.0;
        $dates = $rows->pluck('date')->filter()->sort()->values();
        $summaryRows = [
            ['Periode laporan', ['text', $periodText], 'Rentang tanggal pembayaran yang diekspor'],
            ['Akun kas / rekening', ['text', $accountText], 'Filter akun penerima yang dipilih'],
            ['Total kas masuk (Rp)', ['money', $grand], 'Seluruh pembayaran invoice sesuai filter'],
            ['Kas masuk bulan berjalan (Rp)', ['money', $monthIn], 'Pembayaran diterima sejak '.$today->copy()->startOfMonth()->format('d/m/Y')],
            ['Penerimaan tunai (Rp)', ['money', $cashIn], 'Masuk ke Kas Besar / Kas Kecil'],
            ['Penerimaan transfer / BG / lainnya (Rp)', ['money', $bankIn], 'Masuk ke rekening bank'],
            ['Jumlah transaksi', ['int', $count], 'Jumlah baris pembayaran'],
            ['Jumlah invoice dibayar', ['int', $rows->pluck('invoice_id')->unique()->count()], 'Invoice yang menerima pembayaran pada periode ini'],
            ['Jumlah akun penerima', ['int', $rows->pluck('account_key')->unique()->count()], 'Akun kas / rekening yang menerima dana'],
            ['Rata-rata per transaksi (Rp)', ['money', $count > 0 ? round($grand / $count, 2) : 0.0], 'Total kas masuk dibagi jumlah transaksi'],
            ['Transaksi terbesar (Rp)', ['money', $largest], 'Nominal pembayaran tunggal tertinggi'],
            ['Pembayaran pertama', ['date', $dates->first()], 'Tanggal pembayaran paling awal'],
            ['Pembayaran terakhir', ['date', $dates->last()], 'Tanggal pembayaran paling akhir'],
        ];

        // Saldo masuk per akun (semua akun pada periode, tanpa filter akun).
        $accountRows = [];
        foreach ($accounts as $index => $account) {
            $accountRows[] = [$index + 1, $account['label'], $account['count'], $account['total'], $share($account['total'], $grandAll)];
        }

        // Rekap per metode pembayaran.
        $methodRows = [];
        foreach ($rows->groupBy('method_label')->map(fn ($group): array => [
            'count' => $group->count(), 'total' => round((float) $group->sum('amount'), 2),
        ])->sortByDesc('total') as $label => $group) {
            $methodRows[] = [$label, $group['count'], $group['total'], $share($group['total'], $grand)];
        }

        // Rekap bulanan (kronologis).
        $monthlyRows = [];
        $byMonth = $rows->groupBy(fn (array $entry): string => $entry['date'] ? substr($entry['date'], 0, 7) : '')->sortKeys();
        foreach ($byMonth as $ym => $group) {
            $label = $ym !== ''
                ? Carbon::createFromFormat('!Y-m', $ym)->locale('id')->translatedFormat('F Y')
                : 'Tanpa tanggal';
            $monthCash = round((float) $group->where('method', 'cash')->sum('amount'), 2);
            $monthBank = round((float) $group->where('method', '!=', 'cash')->sum('amount'), 2);
            $monthlyRows[] = [$label, $group->count(), $monthCash, $monthBank, round($monthCash + $monthBank, 2)];
        }

        // Riwayat kas masuk lengkap.
        $historyRows = [];
        foreach ($rows as $index => $entry) {
            $historyRows[] = [
                $index + 1, $entry['date'], $entry['invoice_number'], $entry['order_reference'], $entry['customer'],
                $entry['invoice_date'], $entry['due_date'], $entry['method_label'], $entry['reference'],
                $entry['account_label'], $entry['bank_name'], $entry['bank_number'], $entry['bank_holder'], $entry['notes'],
                $entry['amount'], $entry['invoice_total'], $entry['invoice_paid'], $entry['invoice_outstanding'], $entry['invoice_status'],
            ];
        }

        $workbook = JaspestFinanceXlsx::build([
            [
                'name' => 'Ringkasan', 'heading' => 'Ringkasan',
                'headers' => ['Indikator', 'Nilai', 'Keterangan'],
                'rows' => $summaryRows,
                'types' => ['text', 'money', 'text'],
                'widths' => [40, 26, 62],
                'show_count' => false,
                'tab' => 'FF102548',
            ],
            [
                'name' => 'Saldo per Akun', 'heading' => 'Saldo Masuk per Akun Kas / Rekening',
                'headers' => ['No', 'Akun Kas / Rekening', 'Jumlah Transaksi', 'Total Masuk (Rp)', 'Porsi (%)'],
                'rows' => $accountRows,
                'types' => ['center', 'text', 'int', 'money', 'pct'],
                'widths' => [7, 54, 18, 24, 14],
                'totals' => [2, 3],
            ],
            [
                'name' => 'Per Metode', 'heading' => 'Kas Masuk per Metode Pembayaran',
                'headers' => ['Metode Pembayaran', 'Jumlah Transaksi', 'Total Masuk (Rp)', 'Porsi (%)'],
                'rows' => $methodRows,
                'types' => ['text', 'int', 'money', 'pct'],
                'widths' => [36, 18, 24, 14],
                'totals' => [1, 2],
            ],
            [
                'name' => 'Rekap Bulanan', 'heading' => 'Rekap Kas Masuk Bulanan',
                'headers' => ['Bulan', 'Jumlah Transaksi', 'Tunai (Rp)', 'Transfer / BG / Lainnya (Rp)', 'Total Masuk (Rp)'],
                'rows' => $monthlyRows,
                'types' => ['text', 'int', 'money', 'money', 'money'],
                'widths' => [26, 18, 24, 28, 24],
                'totals' => [1, 2, 3, 4],
            ],
            [
                'name' => 'Riwayat Kas Masuk', 'heading' => 'Riwayat Kas Masuk',
                'headers' => [
                    'No', 'Tanggal Bayar', 'Nomor Invoice', 'Referensi Order', 'Pelanggan', 'Tanggal Invoice', 'Jatuh Tempo',
                    'Metode', 'Nomor Referensi', 'Akun Kas / Rekening', 'Bank Penerima', 'Nomor Rekening', 'Atas Nama', 'Catatan',
                    'Jumlah Diterima (Rp)', 'Total Invoice (Rp)', 'Total Terbayar (Rp)', 'Sisa Piutang (Rp)', 'Status Invoice',
                ],
                'rows' => $historyRows,
                'types' => [
                    'center', 'date', 'text', 'text', 'text', 'date', 'date',
                    'center', 'text', 'text', 'text', 'text', 'text', 'text',
                    'money', 'money', 'money', 'money', 'center',
                ],
                'widths' => [6, 15, 24, 20, 30, 15, 15, 18, 22, 38, 18, 22, 28, 38, 22, 22, 22, 22, 22],
                'totals' => [14],
            ],
        ], 'JASPEST | LAPORAN KAS & BANK', $subtitle, $filterText);

        $filename = 'JASPEST-Laporan-Kas-Bank-'.now('Asia/Jakarta')->format('Y-m-d').'.xlsx';

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
        $invoice->load(['enquiry', 'payments', 'contract', 'visits']);
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
            'contract_number' => $invoice->contract?->contract_number,
            'visit_count' => $invoice->contract_id ? (int) ($invoice->visits_count ?? $invoice->visits()->count()) : 0,
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

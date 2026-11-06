<?php

namespace App\Http\Controllers;

use App\Models\JaspestFinanceBankAccount;
use App\Models\JaspestFinanceExpense;
use App\Models\JaspestFinanceInvoice;
use App\Models\JaspestFinancePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kas & Bank: ringkasan omzet (Year-on-Year), omzet vs pengeluaran bulanan, dan pencatatan pengeluaran.
 *
 * Definisi omzet di modul ini: seluruh pembayaran invoice yang sudah diterima (basis kas),
 * sama dengan data "Riwayat Kas Masuk" pada halaman Kas & Bank.
 */
class JaspestFinanceCashflowController extends Controller
{
    private const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const CASH_ACCOUNTS = ['111.01' => 'KAS BESAR', '111.02' => 'KAS KECIL'];

    /** Omzet & pengeluaran per bulan untuk satu tahun, dibandingkan dengan tahun sebelumnya. */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'bank' => ['nullable', 'regex:/^(all|cash|\d+)$/'],
        ]);

        $today = Carbon::today('Asia/Jakarta');
        $year = (int) ($validated['year'] ?? $today->year);
        $prevYear = $year - 1;
        $bank = (string) ($validated['bank'] ?? 'all');

        $omzet = [$year => array_fill(1, 12, 0.0), $prevYear => array_fill(1, 12, 0.0)];
        $expense = [$year => array_fill(1, 12, 0.0), $prevYear => array_fill(1, 12, 0.0)];

        $payments = $this->paymentScope($bank)
            ->whereDate('payment_date', '>=', "{$prevYear}-01-01")
            ->whereDate('payment_date', '<=', "{$year}-12-31")
            ->get(['payment_date', 'amount']);
        foreach ($payments as $payment) {
            $date = $payment->payment_date;
            if ($date && isset($omzet[$date->year])) {
                $omzet[$date->year][(int) $date->month] += (float) $payment->amount;
            }
        }

        $expenses = $this->expenseScope($bank)
            ->whereDate('expense_date', '>=', "{$prevYear}-01-01")
            ->whereDate('expense_date', '<=', "{$year}-12-31")
            ->get(['expense_date', 'amount']);
        foreach ($expenses as $row) {
            $date = $row->expense_date;
            if ($date && isset($expense[$date->year])) {
                $expense[$date->year][(int) $date->month] += (float) $row->amount;
            }
        }

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month' => $m,
                'label' => self::MONTHS_SHORT[$m - 1],
                'omzet' => round($omzet[$year][$m], 2),
                'omzet_prev' => round($omzet[$prevYear][$m], 2),
                'expense' => round($expense[$year][$m], 2),
                'expense_prev' => round($expense[$prevYear][$m], 2),
                'net' => round($omzet[$year][$m] - $expense[$year][$m], 2),
            ];
        }

        // Pertumbuhan YoY dibandingkan pada periode yang sebanding:
        // tahun berjalan = Januari s.d. bulan ini; tahun lampau = 12 bulan penuh.
        $comparableMonths = $year === $today->year ? $today->month : ($year < $today->year ? 12 : 0);
        $comparableCurrent = array_sum(array_slice($omzet[$year], 0, $comparableMonths));
        $comparablePrev = array_sum(array_slice($omzet[$prevYear], 0, $comparableMonths));

        // Angka "bulan ini" selalu bulan kalender berjalan, terlepas dari tahun yang dipilih.
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonthNoOverflow();
        $monthOmzet = $this->sumBetween($this->paymentScope($bank), 'payment_date', $monthStart, $today->copy()->endOfMonth());
        $monthExpense = $this->sumBetween($this->expenseScope($bank), 'expense_date', $monthStart, $today->copy()->endOfMonth());
        $prevMonthOmzet = $this->sumBetween($this->paymentScope($bank), 'payment_date', $prevMonthStart, $prevMonthStart->copy()->endOfMonth());
        $prevMonthExpense = $this->sumBetween($this->expenseScope($bank), 'expense_date', $prevMonthStart, $prevMonthStart->copy()->endOfMonth());

        $omzetYear = array_sum($omzet[$year]);
        $expenseYear = array_sum($expense[$year]);

        $yearStart = "{$year}-01-01";
        $yearEnd = "{$year}-12-31";

        // Tagihan & piutang memakai semua invoice, tidak dipengaruhi filter rekening.
        $billed = JaspestFinanceInvoice::query()->whereDate('invoice_date', '>=', $yearStart)->whereDate('invoice_date', '<=', $yearEnd);
        $paidSql = '(SELECT COALESCE(SUM(amount), 0) FROM jaspest_finance_payments WHERE jaspest_finance_payments.invoice_id = jaspest_finance_invoices.id)';
        $open = JaspestFinanceInvoice::query()
            ->selectRaw("jaspest_finance_invoices.id, jaspest_finance_invoices.due_date, total_amount - {$paidSql} AS outstanding")
            ->whereRaw("total_amount > {$paidSql}")
            ->get();
        $todayKey = $today->format('Y-m-d');
        $overdue = $open->filter(fn ($invoice): bool => $invoice->due_date && $invoice->due_date->format('Y-m-d') < $todayKey);

        // Uang masuk, keluar, dan selisih per akun kas / rekening pada tahun terpilih (semua akun).
        $accounts = [];
        foreach (JaspestFinancePayment::query()->whereDate('payment_date', '>=', $yearStart)->whereDate('payment_date', '<=', $yearEnd)->get() as $payment) {
            [$key, $label] = $this->paymentAccount($payment);
            $accounts[$key] ??= ['key' => $key, 'label' => $label, 'income' => 0.0, 'expense' => 0.0];
            $accounts[$key]['income'] += (float) $payment->amount;
        }
        foreach (JaspestFinanceExpense::query()->whereDate('expense_date', '>=', $yearStart)->whereDate('expense_date', '<=', $yearEnd)->get() as $row) {
            [$key, $label] = $this->expenseAccount($row);
            $accounts[$key] ??= ['key' => $key, 'label' => $label, 'income' => 0.0, 'expense' => 0.0];
            $accounts[$key]['expense'] += (float) $row->amount;
        }
        $accounts = collect($accounts)->map(fn (array $a): array => [
            'key' => $a['key'],
            'label' => $a['label'],
            'income' => round($a['income'], 2),
            'expense' => round($a['expense'], 2),
            'net' => round($a['income'] - $a['expense'], 2),
        ])->sortByDesc('income')->values();

        return response()->json([
            'year' => $year,
            'previous_year' => $prevYear,
            'bank' => $bank,
            'years' => $this->availableYears($year, $today),
            'monthly' => $monthly,
            'summary' => [
                'omzet_year' => round($omzetYear, 2),
                'omzet_prev_year' => round(array_sum($omzet[$prevYear]), 2),
                'expense_year' => round($expenseYear, 2),
                'net_year' => round($omzetYear - $expenseYear, 2),
                'comparable_months' => $comparableMonths,
                'comparable_label' => $this->comparableLabel($comparableMonths),
                'yoy_percent' => $this->percentChange($comparableCurrent, $comparablePrev),
            ],
            'receivable' => [
                'billed_year' => round((float) (clone $billed)->sum('total_amount'), 2),
                'billed_count_year' => (clone $billed)->count(),
                'outstanding' => round((float) $open->sum('outstanding'), 2),
                'outstanding_count' => $open->count(),
                'overdue' => round((float) $overdue->sum('outstanding'), 2),
                'overdue_count' => $overdue->count(),
            ],
            'accounts' => $accounts,
            'month' => [
                'label' => self::MONTHS_LONG[$today->month - 1].' '.$today->year,
                'prev_label' => self::MONTHS_LONG[$prevMonthStart->month - 1],
                'omzet' => round($monthOmzet, 2),
                'expense' => round($monthExpense, 2),
                'net' => round($monthOmzet - $monthExpense, 2),
                'omzet_change_percent' => $this->percentChange($monthOmzet, $prevMonthOmzet),
                'expense_change_percent' => $this->percentChange($monthExpense, $prevMonthExpense),
            ],
        ]);
    }

    /** Daftar pengeluaran, mengikuti filter rekening/tahun yang sama dengan ringkasan omzet. */
    public function expenses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bank' => ['nullable', 'regex:/^(all|cash|\d+)$/'],
            'category' => ['nullable', Rule::in(array_keys(JaspestFinanceExpense::CATEGORIES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = $this->expenseScope((string) ($validated['bank'] ?? 'all'));
        if (! empty($validated['year'])) {
            $query->whereYear('expense_date', (int) $validated['year']);
        }
        if (! empty($validated['month'])) {
            $query->whereMonth('expense_date', (int) $validated['month']);
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        $term = trim((string) ($validated['q'] ?? ''));
        if ($term !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('description', 'like', "%{$term}%")
                ->orWhere('payee', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%"));
        }

        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('amount');
        $byCategory = (clone $query)->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category')->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'label' => JaspestFinanceExpense::CATEGORIES[$row->category] ?? ucfirst((string) $row->category),
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->cnt,
            ])->sortByDesc('total')->values();

        $rows = (clone $query)->orderByDesc('expense_date')->orderByDesc('id')->limit(300)->get();

        return response()->json([
            'summary' => ['total' => round($total, 2), 'count' => $count],
            'categories' => collect(JaspestFinanceExpense::CATEGORIES)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
            'by_category' => $byCategory,
            'data' => $rows->map(fn (JaspestFinanceExpense $expense): array => $this->expenseResource($expense))->values(),
            'truncated' => $count > $rows->count(),
        ]);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $data = $this->validatedExpense($request);
        $data['created_by'] = auth()->id();
        $expense = JaspestFinanceExpense::query()->create($data);

        return response()->json([
            'message' => 'Pengeluaran berhasil dicatat.',
            'expense' => $this->expenseResource($expense),
        ], 201);
    }

    public function updateExpense(Request $request, int $expense): JsonResponse
    {
        $model = JaspestFinanceExpense::query()->findOrFail($expense);
        $model->update($this->validatedExpense($request, $model));

        return response()->json([
            'message' => 'Pengeluaran berhasil diperbarui.',
            'expense' => $this->expenseResource($model->fresh()),
        ]);
    }

    public function deleteExpense(int $expense): JsonResponse
    {
        JaspestFinanceExpense::query()->findOrFail($expense)->delete();

        return response()->json(['message' => 'Pengeluaran berhasil dihapus.']);
    }

    private function validatedExpense(Request $request, ?JaspestFinanceExpense $expense = null): array
    {
        foreach (['description', 'payee', 'reference', 'notes'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => trim($request->input($field))]);
            }
        }

        $data = $request->validate([
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', Rule::in(array_keys(JaspestFinanceExpense::CATEGORIES))],
            'description' => ['required', 'string', 'min:3', 'max:250'],
            'payee' => ['nullable', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'method' => ['required', Rule::in(['transfer', 'cash'])],
            'bank_account_id' => ['nullable', 'integer'],
            'cash_account_code' => ['nullable', Rule::in(array_keys(self::CASH_ACCOUNTS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = [
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'description' => $data['description'],
            'payee' => ($data['payee'] ?? '') !== '' ? $data['payee'] : null,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'reference' => $request->has('reference')
                ? (($data['reference'] ?? '') !== '' ? $data['reference'] : null)
                : $expense?->reference,
            'notes' => ($data['notes'] ?? '') !== '' ? $data['notes'] : null,
            'bank_account_id' => null,
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_account_holder' => null,
            'cash_account_code' => null,
            'cash_account_name' => null,
        ];

        if ($data['method'] === 'transfer') {
            if (empty($data['bank_account_id'])) {
                throw ValidationException::withMessages(['bank_account_id' => 'Pilih rekening bank sumber dana untuk pengeluaran via transfer.']);
            }
            $keepId = (int) ($expense?->bank_account_id ?? 0);
            // Rekening nonaktif hanya boleh dipertahankan bila memang sudah dipakai pengeluaran ini.
            $bank = JaspestFinanceBankAccount::query()
                ->where(fn (Builder $builder) => $builder->where('is_active', true)->orWhere('id', $keepId))
                ->find((int) $data['bank_account_id']);
            if (! $bank) {
                throw ValidationException::withMessages(['bank_account_id' => 'Rekening tidak ditemukan atau sudah nonaktif di Master Data.']);
            }
            $result['bank_account_id'] = $bank->id;
            $result['bank_name'] = $bank->bank_name;
            $result['bank_account_number'] = $bank->account_number;
            $result['bank_account_holder'] = $bank->account_holder;
        } else {
            $code = (string) ($data['cash_account_code'] ?? '');
            if (! isset(self::CASH_ACCOUNTS[$code])) {
                throw ValidationException::withMessages(['cash_account_code' => 'Pilih KAS BESAR (111.01) atau KAS KECIL (111.02) sebagai sumber dana tunai.']);
            }
            $configured = collect(config('jaspest_finance.cash_bank_accounts', []))->firstWhere('account_code', $code);
            $result['cash_account_code'] = $code;
            $result['cash_account_name'] = $configured['account_name'] ?? self::CASH_ACCOUNTS[$code];
        }

        return $result;
    }

    private function expenseResource(JaspestFinanceExpense $expense): array
    {
        if ($expense->cash_account_code) {
            $account = trim($expense->cash_account_code.' · '.$expense->cash_account_name);
        } elseif ($expense->bank_name) {
            $account = trim($expense->bank_name.' '.($expense->bank_account_number ?? '')
                .($expense->bank_account_holder ? ' · '.$expense->bank_account_holder : ''));
        } else {
            $account = 'Sumber dana belum dicatat';
        }

        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date?->format('Y-m-d'),
            'category' => $expense->category,
            'category_label' => $expense->category_label,
            'description' => $expense->description,
            'payee' => $expense->payee,
            'amount' => (float) $expense->amount,
            'method' => $expense->method,
            'method_label' => $expense->method === 'cash' ? 'Tunai' : 'Transfer',
            'reference' => $expense->reference,
            'notes' => $expense->notes,
            'bank_account_id' => $expense->bank_account_id,
            'cash_account_code' => $expense->cash_account_code,
            'account_label' => $account,
        ];
    }

    /** @return array{0: string, 1: string} kunci dan label akun penerima; kunci sama dengan halaman Pemasukan. */
    private function paymentAccount(JaspestFinancePayment $payment): array
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

    /** @return array{0: string, 1: string} kunci dan label sumber dana pengeluaran, selaras dengan paymentAccount(). */
    private function expenseAccount(JaspestFinanceExpense $expense): array
    {
        if ($expense->cash_account_code) {
            return ['cash:'.$expense->cash_account_code, trim($expense->cash_account_code.' · '.$expense->cash_account_name)];
        }
        if ($expense->bank_account_id || $expense->bank_name) {
            $label = trim(($expense->bank_name ?? 'Bank').' '.($expense->bank_account_number ?? '')
                .($expense->bank_account_holder ? ' · '.$expense->bank_account_holder : ''));

            return ['bank:'.($expense->bank_account_id ?? $expense->bank_name), $label];
        }

        return ['other', 'Akun belum dicatat'];
    }

    /** Pembayaran masuk yang dibatasi ke satu rekening Master Data, kas tunai, atau semuanya. */
    private function paymentScope(string $bank): Builder
    {
        $query = JaspestFinancePayment::query();
        if ($bank === 'cash') {
            $query->where(fn (Builder $builder) => $builder->whereNotNull('receiving_account_code')->orWhere('method', 'cash'));
        } elseif ($bank !== 'all') {
            $query->where('received_bank_account_id', (int) $bank);
        }

        return $query;
    }

    private function expenseScope(string $bank): Builder
    {
        $query = JaspestFinanceExpense::query();
        if ($bank === 'cash') {
            $query->where('method', 'cash');
        } elseif ($bank !== 'all') {
            $query->where('bank_account_id', (int) $bank);
        }

        return $query;
    }

    private function sumBetween(Builder $query, string $column, Carbon $from, Carbon $to): float
    {
        return (float) $query
            ->whereDate($column, '>=', $from->format('Y-m-d'))
            ->whereDate($column, '<=', $to->format('Y-m-d'))
            ->sum('amount');
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function comparableLabel(int $months): string
    {
        if ($months <= 0) {
            return 'Belum ada periode pembanding';
        }
        if ($months >= 12) {
            return 'Januari–Desember';
        }

        return $months === 1 ? 'Januari' : 'Januari–'.self::MONTHS_LONG[$months - 1];
    }

    /** @return list<int> tahun dengan data (menurun), minimal tahun ini dan tahun lalu. */
    private function availableYears(int $selected, Carbon $today): array
    {
        $first = collect([
            JaspestFinancePayment::query()->min('payment_date'),
            JaspestFinanceExpense::query()->min('expense_date'),
        ])->filter()->map(fn ($date): int => (int) substr((string) $date, 0, 4))->min();

        $from = min((int) ($first ?: $today->year), $today->year - 1, $selected);
        $to = max($today->year, $selected);

        return range($to, max($from, $to - 15));
    }
}

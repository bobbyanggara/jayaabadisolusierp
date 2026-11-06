<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestFinanceExpense extends Model
{
    /** Kategori pengeluaran (kunci => label). Kunci disimpan di database. */
    public const CATEGORIES = [
        'gaji' => 'Gaji & Honor',
        'bahan' => 'Bahan & Obat Pest Control',
        'transport' => 'Transport & BBM',
        'peralatan' => 'Peralatan & Perawatan',
        'operasional' => 'Operasional Kantor',
        'pemasaran' => 'Pemasaran & Iklan',
        'pajak' => 'Pajak & Perizinan',
        'lainnya' => 'Lain-lain',
    ];

    protected $table = 'jaspest_finance_expenses';

    protected $fillable = [
        'expense_date', 'category', 'description', 'payee', 'amount', 'method', 'reference', 'notes',
        'bank_account_id', 'bank_name', 'bank_account_number', 'bank_account_holder',
        'cash_account_code', 'cash_account_name', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(JaspestFinanceBankAccount::class, 'bank_account_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}

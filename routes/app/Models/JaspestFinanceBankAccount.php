<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestFinanceBankAccount extends Model
{
    protected $table = 'jaspest_finance_bank_accounts';

    protected $fillable = [
        'bank_name', 'account_number', 'account_holder', 'account_label', 'branch', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(JaspestFinanceInvoice::class, 'bank_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(JaspestFinancePayment::class, 'received_bank_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(JaspestFinanceExpense::class, 'bank_account_id');
    }
}

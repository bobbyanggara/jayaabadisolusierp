<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestFinancePayment extends Model
{
    protected $table = 'jaspest_finance_payments';

    protected $fillable = [
        'invoice_id', 'payment_date', 'amount', 'method', 'reference', 'notes', 'received_by',
        'receiving_account_code', 'receiving_account_name', 'received_bank_account_id',
        'received_bank_name', 'received_bank_account_number', 'received_bank_account_holder',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(JaspestFinanceInvoice::class, 'invoice_id');
    }
}

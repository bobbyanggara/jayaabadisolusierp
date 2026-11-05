<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestFinanceInvoice extends Model
{
    protected $table = 'jaspest_finance_invoices';

    protected $fillable = [
        'invoice_number', 'enquiry_id', 'invoice_date', 'due_date', 'billing_name', 'billing_address',
        'job_type', 'location_name', 'job_address', 'bank_account_id', 'bank_name',
        'bank_account_number', 'bank_account_holder', 'work_period', 'work_amount', 'ppn_rate',
        'ppn_amount', 'total_amount', 'amount_words', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'work_amount' => 'decimal:2',
            'ppn_rate' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(JaspestFinancePayment::class, 'invoice_id')->orderBy('payment_date')->orderBy('id');
    }
}

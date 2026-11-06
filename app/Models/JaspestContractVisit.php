<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestContractVisit extends Model
{
    protected $table = 'jaspest_contract_visits';

    protected $fillable = [
        'contract_id', 'period_index', 'slot_no', 'period_start', 'period_end', 'scheduled_date',
        'technician_id', 'technician_name', 'status', 'invoice_id', 'billed_amount', 'is_extra', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'scheduled_date' => 'date:Y-m-d',
            'billed_amount' => 'decimal:2',
            'is_extra' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(JaspestContract::class, 'contract_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(JaspestFinanceInvoice::class, 'invoice_id');
    }

    public function isBilled(): bool
    {
        return $this->invoice_id !== null;
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(JaspestTechnician::class, 'technician_id');
    }
}

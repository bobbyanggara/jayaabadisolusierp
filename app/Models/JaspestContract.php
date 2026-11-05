<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestContract extends Model
{
    protected $table = 'jaspest_contracts';

    protected $fillable = [
        'contract_number', 'enquiry_id', 'client_id', 'service', 'services', 'period_type', 'visits_per_period',
        'duration_periods', 'start_date', 'end_date', 'price_basis', 'price_amount', 'total_price', 'area',
        'default_technician_id', 'default_technician_name', 'status', 'notes', 'cancelled_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'price_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'area' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(JaspestContractVisit::class, 'contract_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(JaspestClient::class, 'client_id');
    }

    public function defaultTechnician(): BelongsTo
    {
        return $this->belongsTo(JaspestTechnician::class, 'default_technician_id');
    }
}

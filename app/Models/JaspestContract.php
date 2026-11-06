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

    public function invoices(): HasMany
    {
        return $this->hasMany(JaspestFinanceInvoice::class, 'contract_id');
    }

    /** Jumlah kunjungan terencana pada kontrak (tidak termasuk kunjungan tambahan). */
    public function plannedVisitCount(): int
    {
        return (int) $this->visits_per_period * (int) $this->duration_periods;
    }

    /**
     * Nilai tagihan untuk satu kunjungan: harga per kunjungan bila kontrak berdasar per_visit,
     * selain itu total kontrak dibagi jumlah kunjungan terencana.
     */
    public function unitPrice(): float
    {
        if ($this->price_basis === 'per_visit') {
            return round((float) $this->price_amount, 2);
        }
        $count = $this->plannedVisitCount();

        return $count > 0 ? round((float) $this->total_price / $count, 2) : 0.0;
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

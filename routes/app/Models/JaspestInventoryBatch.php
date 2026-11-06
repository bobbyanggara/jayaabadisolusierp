<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestInventoryBatch extends Model
{
    protected $table = 'jaspest_inventory_batches';

    protected $fillable = [
        'item_id', 'batch_number', 'expires_at', 'quantity_received', 'quantity_remaining',
        'unit_cost', 'supplier_name', 'invoice_number', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date:Y-m-d',
            'received_at' => 'date:Y-m-d',
            'quantity_received' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(JaspestInventoryItem::class, 'item_id');
    }

    public function technicianStocks(): HasMany
    {
        return $this->hasMany(JaspestInventoryTechnicianBatch::class, 'batch_id');
    }
}

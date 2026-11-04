<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestInventoryMovement extends Model
{
    protected $table = 'jaspest_inventory_movements';

    protected $fillable = [
        'movement_code', 'item_id', 'movement_type', 'quantity', 'unit_cost',
        'warehouse_before', 'warehouse_after', 'enquiry_id', 'supplier_name',
        'invoice_number', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'warehouse_before' => 'decimal:3',
            'warehouse_after' => 'decimal:3',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(JaspestInventoryItem::class, 'item_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }
}

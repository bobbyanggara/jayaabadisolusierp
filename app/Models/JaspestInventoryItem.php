<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestInventoryItem extends Model
{
    protected $table = 'jaspest_inventory_items';

    protected $fillable = [
        'sku', 'name', 'category', 'item_type', 'brand', 'specification', 'unit',
        'minimum_stock', 'stock_on_hand', 'track_expiry', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:3',
            'stock_on_hand' => 'decimal:3',
            'is_active' => 'boolean',
            'track_expiry' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(JaspestInventoryBatch::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(JaspestInventoryMovement::class, 'item_id');
    }

    public function technicianStocks(): HasMany
    {
        return $this->hasMany(JaspestInventoryTechnicianStock::class, 'item_id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(JaspestInventoryMaintenance::class, 'item_id');
    }
}

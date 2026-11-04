<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestInventoryItem extends Model
{
    protected $table = 'jaspest_inventory_items';

    protected $fillable = [
        'name', 'category', 'brand', 'specification', 'unit',
        'stock_on_hand', 'damaged_quantity', 'condition_status',
    ];

    protected function casts(): array
    {
        return [
            'stock_on_hand' => 'decimal:3',
            'damaged_quantity' => 'decimal:3',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(JaspestInventoryMovement::class, 'item_id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(JaspestInventoryMaintenance::class, 'item_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestInventoryTechnicianStock extends Model
{
    protected $table = 'jaspest_inventory_technician_stocks';

    protected $fillable = ['item_id', 'technician_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(JaspestInventoryItem::class, 'item_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(JaspestTechnician::class, 'technician_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestInventoryMaintenance extends Model
{
    protected $table = 'jaspest_inventory_maintenance';

    protected $fillable = [
        'item_id', 'technician_id', 'maintenance_type', 'scheduled_date', 'completed_at',
        'status', 'cost', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
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

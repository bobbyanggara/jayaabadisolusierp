<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JaspestEnquiry extends Model
{
    protected $table = 'jaspest_enquiries';

    protected $fillable = [
        'reference', 'full_name', 'whatsapp', 'address', 'estimated_area', 'need',
        'property_type', 'company_name', 'description', 'consent', 'status',
        'wa_opened_at', 'contacted_at', 'followed_up_at', 'converted_at',
        'order_price', 'order_area', 'scheduled_date', 'assigned_technician_id', 'assigned_technician_name',
    ];

    protected function casts(): array
    {
        return [
            'estimated_area' => 'decimal:2',
            'consent' => 'boolean',
            'wa_opened_at' => 'datetime',
            'contacted_at' => 'datetime',
            'followed_up_at' => 'datetime',
            'converted_at' => 'datetime',
            'order_price' => 'decimal:2',
            'order_area' => 'decimal:2',
            'scheduled_date' => 'date:Y-m-d',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function photos(): HasMany
    {
        return $this->hasMany(JaspestEnquiryPhoto::class, 'enquiry_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(JaspestEnquiryEvent::class, 'enquiry_id')->latest();
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(JaspestTechnician::class, 'assigned_technician_id');
    }

    public function clientLink(): HasOne
    {
        return $this->hasOne(JaspestClientEnquiryLink::class, 'enquiry_id');
    }

    public function whatsappNotification(): HasOne
    {
        return $this->hasOne(JaspestWhatsappNotification::class, 'enquiry_id')
            ->where('notification_type', 'deal_order_confirmation');
    }

}

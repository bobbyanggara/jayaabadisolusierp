<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JaspestEnquiry extends Model
{
    protected $table = 'jaspest_enquiries';

    protected $fillable = [
        'reference', 'full_name', 'whatsapp', 'address', 'estimated_area', 'need',
        'property_type', 'company_name', 'description', 'consent', 'status',
        'wa_opened_at', 'contacted_at', 'followed_up_at', 'converted_at',
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
}

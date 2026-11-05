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
        'order_price', 'order_area', 'scheduled_date', 'service_schedules', 'assigned_technician_id', 'assigned_technician_name',
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
            'service_schedules' => 'array',
        ];
    }

    /**
     * Daftar layanan order beserta tanggal pelaksanaannya.
     * Order lama (satu layanan) otomatis dibaca dari kolom need + scheduled_date.
     *
     * @return array<int, array{slug: string, title: string, scheduled_date: ?string}>
     */
    public function serviceSchedules(): array
    {
        $rows = is_array($this->service_schedules) ? array_values($this->service_schedules) : [];
        if ($rows === []) {
            $rows = [[
                'slug' => (string) $this->need,
                'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            ]];
        }

        $slugs = array_values(array_unique(array_map(fn ($row) => (string) ($row['slug'] ?? ''), $rows)));
        $titles = JaspestContentItem::query()
            ->where('type', 'services')
            ->whereIn('slug', $slugs)
            ->pluck('title', 'slug');

        return array_map(function ($row) use ($titles): array {
            $slug = (string) ($row['slug'] ?? '');

            return [
                'slug' => $slug,
                'title' => (string) ($titles[$slug] ?? \Illuminate\Support\Str::of($slug)->replace(['-', '_'], ' ')->title()->toString()),
                'scheduled_date' => $row['scheduled_date'] ?? null,
            ];
        }, $rows);
    }

    public function serviceTitleLabel(): string
    {
        return implode(' + ', array_column($this->serviceSchedules(), 'title'));
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

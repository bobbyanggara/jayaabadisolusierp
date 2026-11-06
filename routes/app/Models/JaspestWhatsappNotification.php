<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestWhatsappNotification extends Model
{
    protected $table = 'jaspest_whatsapp_notifications';

    protected $fillable = [
        'enquiry_id',
        'notification_type',
        'recipient',
        'message',
        'status',
        'provider_message_id',
        'provider_request_id',
        'provider_status',
        'provider_state',
        'provider_state_id',
        'attempts',
        'provider_response',
        'last_error',
        'accepted_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'attempts' => 'integer',
            'accepted_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }
}

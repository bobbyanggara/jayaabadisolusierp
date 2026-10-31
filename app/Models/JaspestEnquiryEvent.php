<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestEnquiryEvent extends Model
{
    protected $table = 'jaspest_enquiry_events';

    protected $fillable = [
        'enquiry_id', 'user_id', 'event', 'from_status', 'to_status', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }
}

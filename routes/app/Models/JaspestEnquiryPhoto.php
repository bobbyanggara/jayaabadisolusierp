<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestEnquiryPhoto extends Model
{
    protected $table = 'jaspest_enquiry_photos';

    protected $fillable = ['enquiry_id', 'path', 'original_name', 'mime_type', 'size_bytes'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }
}

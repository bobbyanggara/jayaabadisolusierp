<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestClientEnquiryLink extends Model
{
    protected $table = 'jaspest_client_enquiry_links';

    protected $fillable = ['client_id', 'enquiry_id', 'linked_by'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(JaspestClient::class, 'client_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(JaspestEnquiry::class, 'enquiry_id');
    }
}

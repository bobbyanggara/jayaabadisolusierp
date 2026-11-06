<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class JaspestClient extends Model
{
    protected $table = 'jaspest_clients';

    protected $fillable = [
        'client_code',
        'full_name',
        'company_name',
        'whatsapp',
        'whatsapp_normalized',
        'email',
        'address',
        'status',
        'notes',
    ];

    public function enquiryLinks(): HasMany
    {
        return $this->hasMany(JaspestClientEnquiryLink::class, 'client_id');
    }

    public function enquiries(): HasManyThrough
    {
        return $this->hasManyThrough(
            JaspestEnquiry::class,
            JaspestClientEnquiryLink::class,
            'client_id',
            'id',
            'id',
            'enquiry_id'
        );
    }
}

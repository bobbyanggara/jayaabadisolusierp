<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JaspestTechnician extends Model
{
    protected $table = 'jaspest_technicians';

    protected $fillable = [
        'full_name', 'email', 'address', 'technician_code', 'specialization',
        'service_area', 'certification', 'operational_status', 'notes',
    ];
}

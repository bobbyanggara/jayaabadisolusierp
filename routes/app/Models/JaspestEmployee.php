<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JaspestEmployee extends Model
{
    protected $table = 'jaspest_employees';

    protected $fillable = [
        'user_id', 'employee_code', 'full_name', 'whatsapp', 'email', 'department', 'role', 'position',
        'employment_type', 'join_date', 'employment_status', 'address', 'notes',
    ];

    protected function casts(): array
    {
        return ['join_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

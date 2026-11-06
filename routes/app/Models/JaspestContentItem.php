<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JaspestContentItem extends Model
{
    protected $table = 'jaspest_content_items';

    protected $fillable = [
        'type', 'title', 'slug', 'category', 'description', 'body', 'image_path',
        'image_alt', 'status', 'sort_order', 'data',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'sort_order' => 'integer'];
    }
}

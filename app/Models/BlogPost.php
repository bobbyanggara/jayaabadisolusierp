<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body', 'cover_path', 'cover_alt',
        'author_name', 'meta_title', 'meta_description', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function paragraphs(): array
    {
        return preg_split('/\R\s*\R/u', trim($this->body), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(count(preg_split('/\s+/u', trim($this->body))) / 200));
    }

    public function coverUrl(): string
    {
        return asset($this->cover_path ?: 'jaspest/assets/hero-pest-control.webp');
    }

    public function schema(): array
    {
        return [
            '@type' => 'BlogPosting',
            'headline' => $this->title,
            'description' => $this->meta_description ?: $this->excerpt,
            'image' => [$this->coverUrl()],
            'datePublished' => $this->published_at->toIso8601String(),
            'dateModified' => $this->updated_at->toIso8601String(),
            'author' => ['@type' => 'Organization', 'name' => $this->author_name, 'url' => url('/')],
            'publisher' => ['@type' => 'Organization', 'name' => 'JASPEST — CV Jaya Abadi Solusi', 'url' => url('/')],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('jaspest.blog.show', $this->slug)],
            'inLanguage' => 'id-ID',
            'articleSection' => $this->category,
        ];
    }
}

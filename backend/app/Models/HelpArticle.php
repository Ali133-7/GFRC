<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'page_key', 'category', 'title_ar', 'title_en',
        'content_ar', 'content_en', 'media', 'links', 'examples',
        'sort_order', 'is_active', 'is_system',
    ];

    protected $casts = [
        'media' => 'array',
        'links' => 'array',
        'examples' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getTitle(): string
    {
        return $this->title_ar ?? $this->title_en ?? '';
    }

    public function getContent(): string
    {
        return $this->content_ar ?? $this->content_en ?? '';
    }
}

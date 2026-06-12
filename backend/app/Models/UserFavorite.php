<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'favorite_type',
        'favorite_id',
        'favorite_name_ar',
        'favorite_name_en',
        'category',
        'sort_order',
        'metadata',
        'quick_access_config',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quick_access_config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('favorite_type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->favorite_name_ar : ($this->favorite_name_en ?? $this->favorite_name_ar);
    }
}

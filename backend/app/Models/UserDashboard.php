<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboard extends Model
{
    protected $fillable = [
        'user_id',
        'dashboard_id',
        'custom_name',
        'is_favorite',
        'sort_order',
        'is_pinned',
        'layout_overrides',
        'widget_positions',
        'widget_sizes',
        'is_visible',
        'is_hidden_by_user',
        'inherits_from_role',
        'inherits_from_department',
        'allow_inheritance_updates',
    ];

    protected $casts = [
        'layout_overrides' => 'array',
        'widget_positions' => 'array',
        'widget_sizes' => 'array',
        'is_favorite' => 'boolean',
        'is_pinned' => 'boolean',
        'is_visible' => 'boolean',
        'is_hidden_by_user' => 'boolean',
        'inherits_from_role' => 'boolean',
        'inherits_from_department' => 'boolean',
        'allow_inheritance_updates' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}

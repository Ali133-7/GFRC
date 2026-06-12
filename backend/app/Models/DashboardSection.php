<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardSection extends Model
{
    protected $fillable = [
        'dashboard_id',
        'name_ar',
        'name_en',
        'description',
        'sort_order',
        'layout_type',
        'layout_config',
        'background_color',
        'border_color',
        'padding',
        'is_collapsible',
        'is_collapsed',
        'is_visible',
        'display_conditions',
        'permissions',
        'created_by',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'display_conditions' => 'array',
        'permissions' => 'array',
        'is_collapsible' => 'boolean',
        'is_collapsed' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class, 'section_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : ($this->name_en ?? $this->name_ar);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardTemplate extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'description',
        'category',
        'role_type',
        'layout_config',
        'default_widgets',
        'is_active',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'default_widgets' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function dashboards(): HasMany
    {
        return $this->hasMany(Dashboard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByRoleType($query, string $roleType)
    {
        return $query->where('role_type', $roleType);
    }
}

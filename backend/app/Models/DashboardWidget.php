<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    protected $fillable = [
        'section_id',
        'name_ar',
        'name_en',
        'widget_type',
        'data_source',
        'sort_order',
        'grid_x',
        'grid_y',
        'grid_width',
        'grid_height',
        'data_config',
        'display_config',
        'filter_config',
        'filter_by_user',
        'filter_by_department',
        'filter_by_role',
        'filter_by_branch',
        'custom_filters',
        'refresh_interval',
        'is_real_time',
        'is_visible',
        'is_editable',
        'is_removable',
        'visibility_rules',
        'required_permissions',
        'allowed_roles',
        'allowed_departments',
        'template_widget_id',
        'is_inherited',
        'is_customized',
        'created_by',
    ];

    protected $casts = [
        'data_config' => 'array',
        'display_config' => 'array',
        'filter_config' => 'array',
        'custom_filters' => 'array',
        'visibility_rules' => 'array',
        'required_permissions' => 'array',
        'allowed_roles' => 'array',
        'allowed_departments' => 'array',
        'filter_by_user' => 'boolean',
        'filter_by_department' => 'boolean',
        'filter_by_role' => 'boolean',
        'filter_by_branch' => 'boolean',
        'is_real_time' => 'boolean',
        'is_visible' => 'boolean',
        'is_editable' => 'boolean',
        'is_removable' => 'boolean',
        'is_inherited' => 'boolean',
        'is_customized' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(DashboardSection::class);
    }

    public function templateWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'template_widget_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('widget_type', $type);
    }

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : ($this->name_en ?? $this->name_ar);
    }

    public function isVisibleTo(User $user): bool
    {
        if (!$this->is_visible) {
            return false;
        }

        // Check permissions
        if ($this->required_permissions) {
            foreach ($this->required_permissions as $permission) {
                if (!$user->can($permission)) {
                    return false;
                }
            }
        }

        // Check allowed roles
        if ($this->allowed_roles && !in_array($user->role_id, $this->allowed_roles)) {
            return false;
        }

        // Check allowed departments
        if ($this->allowed_departments && !in_array($user->department_id, $this->allowed_departments)) {
            return false;
        }

        return true;
    }
}

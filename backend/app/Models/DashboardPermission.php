<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardPermission extends Model
{
    protected $fillable = [
        'dashboard_id',
        'permission_type',
        'permission_target_id',
        'can_view',
        'can_edit',
        'can_customize',
        'can_share',
        'can_delete',
        'widget_permissions',
        'available_from',
        'available_to',
        'available_days',
        'conditions',
        'granted_by',
        'expires_at',
    ];

    protected $casts = [
        'widget_permissions' => 'array',
        'available_days' => 'array',
        'conditions' => 'array',
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
        'can_customize' => 'boolean',
        'can_share' => 'boolean',
        'can_delete' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeForDashboard($query, int $dashboardId)
    {
        return $query->where('dashboard_id', $dashboardId);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where(function ($q2) use ($user) {
                $q2->where('permission_type', 'user')
                   ->where('permission_target_id', $user->id);
            })
            ->orWhere(function ($q2) use ($user) {
                $q2->where('permission_type', 'role')
                   ->where('permission_target_id', $user->role_id);
            })
            ->orWhere(function ($q2) use ($user) {
                $q2->where('permission_type', 'department')
                   ->where('permission_target_id', $user->department_id);
            });
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}

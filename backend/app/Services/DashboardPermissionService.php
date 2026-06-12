<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\DashboardPermission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Permission Service
 * 
 * Handles visibility and access control for dashboards and widgets.
 */
class DashboardPermissionService
{
    /**
     * Check if user can view dashboard
     */
    public function canView(User $user, Dashboard $dashboard): bool
    {
        // System scope dashboards are visible to everyone
        if ($dashboard->scope === 'system') {
            return true;
        }

        // Check ownership
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        // Check scope-based access
        if ($dashboard->scope === 'organization' && $dashboard->organization_id === $user->organization_id) {
            return true;
        }

        if ($dashboard->scope === 'department' && $dashboard->department_id === $user->department_id) {
            return true;
        }

        if ($dashboard->scope === 'role' && $dashboard->role_id === $user->role_id) {
            return true;
        }

        // Check visibility scope
        if (!$this->checkVisibilityScope($user, $dashboard)) {
            return false;
        }

        // Check explicit permissions
        return $this->hasExplicitPermission($user, $dashboard, 'can_view');
    }

    /**
     * Check if user can edit dashboard
     */
    public function canEdit(User $user, Dashboard $dashboard): bool
    {
        // Owner can always edit
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        // Check visibility scope first
        if (!$this->checkVisibilityScope($user, $dashboard)) {
            return false;
        }

        // Check explicit edit permission
        return $this->hasExplicitPermission($user, $dashboard, 'can_edit');
    }

    /**
     * Check if user can customize dashboard
     */
    public function canCustomize(User $user, Dashboard $dashboard): bool
    {
        // Owner can always customize
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        // Check visibility scope
        if (!$this->checkVisibilityScope($user, $dashboard)) {
            return false;
        }

        // Check explicit customize permission
        return $this->hasExplicitPermission($user, $dashboard, 'can_customize');
    }

    /**
     * Check if user can delete dashboard
     */
    public function canDelete(User $user, Dashboard $dashboard): bool
    {
        // Only owner or system admin can delete
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        // Check explicit delete permission
        return $this->hasExplicitPermission($user, $dashboard, 'can_delete');
    }

    /**
     * Check visibility scope
     */
    protected function checkVisibilityScope(User $user, Dashboard $dashboard): bool
    {
        return match ($dashboard->visibility) {
            'public' => true,
            'system' => true,
            'organization' => $dashboard->organization_id === $user->organization_id,
            'department' => $dashboard->department_id === $user->department_id,
            'role' => $dashboard->role_id === $user->role_id,
            'private' => $dashboard->user_id === $user->id,
            default => false,
        };
    }

    /**
     * Check explicit permission
     */
    protected function hasExplicitPermission(User $user, Dashboard $dashboard, string $permissionType): bool
    {
        $permission = DashboardPermission::forDashboard($dashboard->id)
            ->where(function ($query) use ($user) {
                // Check user-specific permission
                $query->where(function ($q) use ($user) {
                    $q->where('permission_type', 'user')
                      ->where('permission_target_id', $user->id);
                })
                // Check role-based permission
                ->orWhere(function ($q) use ($user) {
                    $q->where('permission_type', 'role')
                      ->where('permission_target_id', $user->role_id);
                })
                // Check department-based permission
                ->orWhere(function ($q) use ($user) {
                    $q->where('permission_type', 'department')
                      ->where('permission_target_id', $user->department_id);
                });
            })
            ->where($permissionType, true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $permission !== null;
    }

    /**
     * Grant permission to user/role/department
     */
    public function grantPermission(
        Dashboard $dashboard,
        string $targetType,
        string $targetId,
        array $permissions = ['can_view' => true],
        ?User $grantedBy = null,
        ?\DateTime $expiresAt = null
    ): DashboardPermission {
        $permission = new DashboardPermission([
            'dashboard_id' => $dashboard->id,
            'permission_type' => $targetType,
            'permission_target_id' => $targetId,
            'granted_by' => $grantedBy?->id,
            'expires_at' => $expiresAt,
        ]);

        foreach ($permissions as $key => $value) {
            if (in_array($key, ['can_view', 'can_edit', 'can_customize', 'can_share', 'can_delete'])) {
                $permission->$key = $value;
            }
        }

        $permission->save();

        Log::info('[DashboardPermissionService] Permission granted', [
            'dashboard_id' => $dashboard->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'permissions' => $permissions,
        ]);

        return $permission;
    }

    /**
     * Revoke permission
     */
    public function revokePermission(Dashboard $dashboard, string $targetType, string $targetId): bool
    {
        $deleted = DashboardPermission::where('dashboard_id', $dashboard->id)
            ->where('permission_type', $targetType)
            ->where('permission_target_id', $targetId)
            ->delete();

        Log::info('[DashboardPermissionService] Permission revoked', [
            'dashboard_id' => $dashboard->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);

        return $deleted > 0;
    }

    /**
     * Get all permissions for dashboard
     */
    public function getDashboardPermissions(Dashboard $dashboard): array
    {
        $permissions = DashboardPermission::forDashboard($dashboard->id)->get();

        return $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'type' => $permission->permission_type,
                'target_id' => $permission->permission_target_id,
                'can_view' => $permission->can_view,
                'can_edit' => $permission->can_edit,
                'can_customize' => $permission->can_customize,
                'can_share' => $permission->can_share,
                'can_delete' => $permission->can_delete,
                'expires_at' => $permission->expires_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Check widget visibility
     */
    public function canViewWidget(User $user, \App\Models\DashboardWidget $widget): bool
    {
        if (!$widget->is_visible) {
            return false;
        }

        // Check required permissions
        if ($widget->required_permissions) {
            foreach ($widget->required_permissions as $permission) {
                if (!$user->can($permission)) {
                    return false;
                }
            }
        }

        // Check allowed roles
        if ($widget->allowed_roles && !in_array($user->role_id, $widget->allowed_roles)) {
            return false;
        }

        // Check allowed departments
        if ($widget->allowed_departments && !in_array($user->department_id, $widget->allowed_departments)) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\UserDashboardPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Service with Inheritance Resolution Engine
 * 
 * Resolves dashboards using the hierarchy:
 * User Dashboard → Role Dashboard → Department Dashboard → System Dashboard
 */
class DashboardService
{
    /**
     * Get the effective dashboard for a user using inheritance hierarchy
     */
    public function getEffectiveDashboard(User $user, ?int $dashboardId = null): ?Dashboard
    {
        Log::info('[DashboardService] Getting effective dashboard', [
            'user_id' => $user->id,
            'requested_dashboard_id' => $dashboardId,
        ]);

        // 1. If specific dashboard requested and user has access, return it
        if ($dashboardId) {
            $dashboard = Dashboard::find($dashboardId);
            if ($dashboard && $this->canUserAccess($user, $dashboard)) {
                Log::info('[DashboardService] Using requested dashboard', ['dashboard_id' => $dashboardId]);
                return $dashboard;
            }
        }

        // 2. Check user-specific dashboards (HIGHEST PRIORITY - overrides everything)
        $userDashboard = Dashboard::query()
            ->where('scope', 'user')
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($userDashboard) {
            Log::info('[DashboardService] Using user-specific dashboard', [
                'dashboard_id' => $userDashboard->id,
            ]);
            return $userDashboard;
        }

        // 3. Check user's default dashboard preference
        $preference = UserDashboardPreference::where('user_id', $user->id)->first();
        if ($preference && $preference->default_dashboard_id) {
            $dashboard = Dashboard::find($preference->default_dashboard_id);
            if ($dashboard && $this->canUserAccess($user, $dashboard)) {
                Log::info('[DashboardService] Using user preference dashboard', [
                    'dashboard_id' => $dashboard->id,
                ]);
                return $dashboard;
            }
        }

        // 4. Check role-based dashboards (priority 2)
        $roleDashboard = Dashboard::query()
            ->where('scope', 'role')
            ->where('role_id', $user->role_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($roleDashboard) {
            Log::info('[DashboardService] Using role-based dashboard', [
                'dashboard_id' => $roleDashboard->id,
            ]);
            return $roleDashboard;
        }

        // 5. Check department-based dashboards (priority 3)
        $deptDashboard = Dashboard::query()
            ->where('scope', 'department')
            ->where('department_id', $user->department_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($deptDashboard) {
            Log::info('[DashboardService] Using department dashboard', [
                'dashboard_id' => $deptDashboard->id,
            ]);
            return $deptDashboard;
        }

        // 6. Check organization dashboard (priority 4)
        $orgDashboard = Dashboard::query()
            ->where('scope', 'organization')
            ->where('organization_id', $user->organization_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($orgDashboard) {
            Log::info('[DashboardService] Using organization dashboard', [
                'dashboard_id' => $orgDashboard->id,
            ]);
            return $orgDashboard;
        }

        // 7. Fall back to system dashboard (priority 5)
        $systemDashboard = Dashboard::query()
            ->where('scope', 'system')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($systemDashboard) {
            Log::info('[DashboardService] Using system dashboard', [
                'dashboard_id' => $systemDashboard->id,
            ]);
            return $systemDashboard;
        }

        Log::warning('[DashboardService] No dashboard found for user', ['user_id' => $user->id]);
        return null;
    }

    /**
     * Get all available dashboards for a user
     */
    public function getAvailableDashboards(User $user): array
    {
        $dashboards = Dashboard::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                // System dashboards
                $query->where('scope', 'system')
                      // Organization dashboards
                      ->orWhere(function ($q) use ($user) {
                          $q->where('scope', 'organization')
                            ->where('organization_id', $user->organization_id);
                      })
                      // Department dashboards
                      ->orWhere(function ($q) use ($user) {
                          $q->where('scope', 'department')
                            ->where('department_id', $user->department_id);
                      })
                      // Role dashboards
                      ->orWhere(function ($q) use ($user) {
                          $q->where('scope', 'role')
                            ->where('role_id', $user->role_id);
                      })
                      // User dashboards
                      ->orWhere(function ($q) use ($user) {
                          $q->where('scope', 'user')
                            ->where('user_id', $user->id);
                      });
            })
            ->orderBy('scope')
            ->orderBy('name_ar')
            ->get();

        return $dashboards->map(function ($dashboard) use ($user) {
            return [
                'id' => $dashboard->id,
                'name' => $dashboard->getDisplayNameAttribute(),
                'scope' => $dashboard->scope,
                'is_default' => $dashboard->is_default,
                'is_user_default' => $this->isUserDefault($user, $dashboard),
                'can_edit' => $dashboard->isEditableBy($user),
            ];
        })->toArray();
    }

    /**
     * Check if user can access a dashboard
     */
    public function canUserAccess(User $user, Dashboard $dashboard): bool
    {
        // Check visibility
        if ($dashboard->visibility === 'public') {
            return true;
        }

        // Check ownership
        if ($dashboard->user_id === $user->id) {
            return true;
        }

        // Check scope-based access
        if ($dashboard->scope === 'system') {
            return true;
        }

        if ($dashboard->scope === 'organization' && $dashboard->organization_id === $user->organization_id) {
            return true;
        }

        if ($dashboard->scope === 'department' && $dashboard->department_id === $user->department_id) {
            return true;
        }

        if ($dashboard->scope === 'role' && $dashboard->role_id === $user->role_id) {
            return true;
        }

        // Check explicit permissions
        $permission = $dashboard->permissions()
            ->where(function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('permission_type', 'user')
                      ->where('permission_target_id', $user->id);
                })
                ->orWhere(function ($q) use ($user) {
                    $q->where('permission_type', 'role')
                      ->where('permission_target_id', $user->role_id);
                })
                ->orWhere(function ($q) use ($user) {
                    $q->where('permission_type', 'department')
                      ->where('permission_target_id', $user->department_id);
                });
            })
            ->where('can_view', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $permission !== null;
    }

    /**
     * Check if dashboard is user's default
     */
    public function isUserDefault(User $user, Dashboard $dashboard): bool
    {
        $preference = UserDashboardPreference::where('user_id', $user->id)->first();
        return $preference && $preference->default_dashboard_id === $dashboard->id;
    }

    /**
     * Set user's default dashboard
     */
    public function setUserDefaultDashboard(User $user, int $dashboardId): bool
    {
        $dashboard = Dashboard::find($dashboardId);
        if (!$dashboard || !$this->canUserAccess($user, $dashboard)) {
            return false;
        }

        $preference = UserDashboardPreference::where('user_id', $user->id)->first();
        if (!$preference) {
            $preference = new UserDashboardPreference(['user_id' => $user->id]);
        }

        $preference->default_dashboard_id = $dashboardId;
        $preference->save();

        Log::info('[DashboardService] Set user default dashboard', [
            'user_id' => $user->id,
            'dashboard_id' => $dashboardId,
        ]);

        return true;
    }

    /**
     * Get dashboard with widgets and sections
     */
    public function getDashboardWithContent(Dashboard $dashboard, User $user): array
    {
        $dashboard->load(['sections.widgets']);

        $sections = $dashboard->sections->map(function ($section) use ($user) {
            return [
                'id' => $section->id,
                'name_ar' => $section->name_ar,
                'name_en' => $section->name_en,
                'name' => $section->getDisplayNameAttribute(),
                'layout_type' => $section->layout_type,
                'layout_config' => $section->layout_config,
                'widgets' => $section->widgets->filter(function ($widget) use ($user) {
                    return $widget->isVisibleTo($user);
                })->map(function ($widget) use ($user) {
                    return $this->prepareWidgetData($widget, $user);
                })->values(),
            ];
        });

        return [
            'id' => $dashboard->id,
            'name_ar' => $dashboard->name_ar,
            'name_en' => $dashboard->name_en,
            'name' => $dashboard->getDisplayNameAttribute(),
            'description' => $dashboard->description,
            'scope' => $dashboard->scope,
            'visibility' => $dashboard->visibility,
            'is_default' => $dashboard->is_default,
            'is_active' => $dashboard->is_active,
            'status' => $dashboard->status,
            'version' => $dashboard->version,
            'layout_config' => $dashboard->layout_config,
            'theme_config' => $dashboard->theme_config,
            'sections' => $sections,
        ];
    }

    /**
     * Prepare widget data with dynamic filtering
     */
    protected function prepareWidgetData(DashboardWidget $widget, User $user): array
    {
        $dataConfig = $widget->data_config ?? [];

        // Apply dynamic filters
        if ($widget->filter_by_user) {
            $dataConfig['user_id'] = $user->id;
        }

        if ($widget->filter_by_department) {
            $dataConfig['department_id'] = $user->department_id;
        }

        if ($widget->filter_by_role) {
            $dataConfig['role_id'] = $user->role_id;
        }

        return [
            'id' => $widget->id,
            'name' => $widget->getDisplayNameAttribute(),
            'widget_type' => $widget->widget_type,
            'data_source' => $widget->data_source,
            'grid_x' => $widget->grid_x,
            'grid_y' => $widget->grid_y,
            'grid_width' => $widget->grid_width,
            'grid_height' => $widget->grid_height,
            'data_config' => $dataConfig,
            'display_config' => $widget->display_config,
            'refresh_interval' => $widget->refresh_interval,
            'is_real_time' => $widget->is_real_time,
        ];
    }
}

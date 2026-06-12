<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\UserDashboardPreference;
use App\Models\Receipt;
use App\Models\Register;
use App\Services\DashboardService;
use App\Services\DashboardPermissionService;
use App\Services\WidgetEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends ApiController
{
    protected DashboardService $dashboardService;
    protected DashboardPermissionService $permissionService;
    protected WidgetEngine $widgetEngine;
    
    public function __construct(
        DashboardService $dashboardService,
        DashboardPermissionService $permissionService,
        WidgetEngine $widgetEngine
    ) {
        $this->dashboardService = $dashboardService;
        $this->permissionService = $permissionService;
        $this->widgetEngine = $widgetEngine;
    }

    /**
     * Get user's effective dashboard (with inheritance)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $dashboardId = $request->query('dashboard_id');

            $dashboard = $this->dashboardService->getEffectiveDashboard($user, $dashboardId);

            if (!$dashboard) {
                // Create default dashboard for user if none exists
                $dashboard = $this->createDefaultDashboard($user);
            }

            if (!$this->permissionService->canView($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            $dashboardData = $this->dashboardService->getDashboardWithContent($dashboard, $user);

            // Set user context for widgets
            $this->widgetEngine->setUser($user);

            return $this->success([
                'dashboard' => $dashboardData,
                'is_default' => $this->dashboardService->isUserDefault($user, $dashboard),
                'can_edit' => $dashboard->isEditableBy($user),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create default dashboard for user
     */
    protected function createDefaultDashboard($user)
    {
        $dashboard = Dashboard::create([
            'name_ar' => 'داشبوري الشخصي',
            'name_en' => 'My Dashboard',
            'description' => 'الداشبورد الشخصي الافتراضي',
            'scope' => 'user',
            'visibility' => 'private',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'status' => 'published',
            'version' => 1,
            'layout_config' => [],
            'theme_config' => [],
        ]);

        // Create default section with fund statistics
        $section = DashboardSection::create([
            'dashboard_id' => $dashboard->id,
            'name_ar' => 'إحصائيات الصندوق',
            'layout_type' => 'grid',
            'sort_order' => 0,
            'is_visible' => true,
            'is_collapsible' => false,
            'padding' => 16,
            'created_by' => $user->id,
        ]);

        return $dashboard;
    }

    /**
     * Get all available dashboards for user
     */
    public function availableDashboards(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $dashboards = $this->dashboardService->getAvailableDashboards($user);

            return $this->success([
                'dashboards' => $dashboards,
                'default_dashboard_id' => UserDashboardPreference::where('user_id', $user->id)
                    ->value('default_dashboard_id'),
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] availableDashboards error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Set default dashboard
     */
    public function setDefault(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $dashboardId = $request->input('dashboard_id');

            if (!$dashboardId) {
                return $this->error('Dashboard ID required', 422);
            }

            if ($this->dashboardService->setUserDefaultDashboard($user, $dashboardId)) {
                return $this->success(['message' => 'Default dashboard set successfully']);
            }

            return $this->error('Failed to set default dashboard', 400);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] setDefault error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Get specific dashboard
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canView($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            $dashboardData = $this->dashboardService->getDashboardWithContent($dashboard, $user);

            return $this->success(['dashboard' => $dashboardData]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] show error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Create new dashboard
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $validated = $request->validate([
                'name_ar' => 'required|string|max:255',
                'name_en' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'scope' => 'required|in:user,department,role,organization,system',
                'visibility' => 'required|in:private,shared,department,role,organization,public',
                'layout_config' => 'nullable|array',
                'theme_config' => 'nullable|array',
                'is_default' => 'boolean',
                'assigned_to_user_id' => 'nullable|uuid|exists:users,id',
            ]);

            $dashboard = Dashboard::create([
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'] ?? null,
                'description' => $validated['description'] ?? null,
                'scope' => $validated['scope'],
                'visibility' => $validated['visibility'],
                'layout_config' => $validated['layout_config'] ?? [],
                'theme_config' => $validated['theme_config'] ?? [],
                'user_id' => $validated['scope'] === 'user' ? ($validated['assigned_to_user_id'] ?? $user->id) : null,
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => true,
                'status' => 'published',
                'version' => 1,
                'created_by' => $user->id,
            ]);

            if ($validated['is_default'] ?? false) {
                $targetUserId = $validated['assigned_to_user_id'] ?? $user->id;
                $targetUser = User::find($targetUserId);
                if ($targetUser) {
                    $this->dashboardService->setUserDefaultDashboard($targetUser, $dashboard->id);
                }
            }

            \Log::info('[DashboardController] Dashboard created', [
                'dashboard_id' => $dashboard->id,
                'user_id' => $user->id,
                'assigned_to' => $validated['assigned_to_user_id'] ?? null,
            ]);

            return $this->success([
                'dashboard' => $dashboard,
            ], 'Dashboard created successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update dashboard
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canEdit($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            $validated = $request->validate([
                'name_ar' => 'sometimes|string|max:255',
                'name_en' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'layout_config' => 'nullable|array',
                'theme_config' => 'nullable|array',
                'visibility' => 'sometimes|in:private,shared,department,role,organization,public',
                'is_default' => 'boolean',
                'assigned_to_user_id' => 'nullable|uuid|exists:users,id',
            ]);

            $dashboard->update($validated);

            if (isset($validated['is_default']) && $validated['is_default']) {
                $targetUserId = $validated['assigned_to_user_id'] ?? $user->id;
                $targetUser = User::find($targetUserId);
                if ($targetUser) {
                    $this->dashboardService->setUserDefaultDashboard($targetUser, $dashboard->id);
                }
            }

            return $this->success([
                'dashboard' => $dashboard->fresh(),
                'message' => 'Dashboard updated successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] update error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Clone dashboard
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $sourceDashboard = Dashboard::with(['sections.widgets'])->find($id);

            if (!$sourceDashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canView($user, $sourceDashboard)) {
                return $this->error('Access denied', 403);
            }

            $validated = $request->validate([
                'name_ar' => 'required|string|max:255',
                'name_en' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'scope' => 'sometimes|in:user,department,role,organization,system',
                'visibility' => 'sometimes|in:private,shared,department,role,organization,public',
            ]);

            // Clone dashboard
            $clonedDashboard = Dashboard::create([
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'] ?? $sourceDashboard->name_en,
                'description' => $validated['description'] ?? $sourceDashboard->description,
                'scope' => $validated['scope'] ?? $sourceDashboard->scope,
                'visibility' => $validated['visibility'] ?? $sourceDashboard->visibility,
                'user_id' => ($validated['scope'] ?? $sourceDashboard->scope) === 'user' ? $user->id : $sourceDashboard->user_id,
                'layout_config' => $sourceDashboard->layout_config,
                'theme_config' => $sourceDashboard->theme_config,
                'is_default' => false,
                'is_active' => true,
                'status' => 'draft',
                'version' => 1,
                'created_by' => $user->id,
            ]);

            // Clone sections and widgets
            foreach ($sourceDashboard->sections as $section) {
                $clonedSection = DashboardSection::create([
                    'dashboard_id' => $clonedDashboard->id,
                    'name_ar' => $section->name_ar,
                    'name_en' => $section->name_en,
                    'layout_type' => $section->layout_type,
                    'layout_config' => $section->layout_config,
                    'sort_order' => $section->sort_order,
                    'is_visible' => $section->is_visible,
                    'created_by' => $user->id,
                ]);

                foreach ($section->widgets as $widget) {
                    DashboardWidget::create([
                        'section_id' => $clonedSection->id,
                        'widget_type' => $widget->widget_type,
                        'name_ar' => $widget->name_ar,
                        'name_en' => $widget->name_en,
                        'data_source' => $widget->data_source,
                        'data_config' => $widget->data_config,
                        'display_config' => $widget->display_config,
                        'grid_x' => $widget->grid_x,
                        'grid_y' => $widget->grid_y,
                        'grid_width' => $widget->grid_width,
                        'grid_height' => $widget->grid_height,
                        'refresh_interval' => $widget->refresh_interval,
                        'is_real_time' => $widget->is_real_time,
                        'filter_by_user' => $widget->filter_by_user,
                        'filter_by_department' => $widget->filter_by_department,
                        'filter_by_role' => $widget->filter_by_role,
                        'sort_order' => $widget->sort_order,
                        'is_visible' => $widget->is_visible,
                        'created_by' => $user->id,
                    ]);
                }
            }

            \Log::info('[DashboardController] Dashboard cloned', [
                'source_id' => $id,
                'cloned_id' => $clonedDashboard->id,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'dashboard' => $clonedDashboard,
            ], 'Dashboard cloned successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] clone error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export dashboard as JSON
     */
    public function export(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $dashboard = Dashboard::with(['sections.widgets'])->find($id);

            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canView($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            $exportData = [
                'version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'dashboard' => [
                    'name_ar' => $dashboard->name_ar,
                    'name_en' => $dashboard->name_en,
                    'description' => $dashboard->description,
                    'scope' => $dashboard->scope,
                    'visibility' => $dashboard->visibility,
                    'layout_config' => $dashboard->layout_config,
                    'theme_config' => $dashboard->theme_config,
                    'sections' => $dashboard->sections->map(function ($section) {
                        return [
                            'name_ar' => $section->name_ar,
                            'name_en' => $section->name_en,
                            'layout_type' => $section->layout_type,
                            'layout_config' => $section->layout_config,
                            'sort_order' => $section->sort_order,
                            'is_visible' => $section->is_visible,
                            'widgets' => $section->widgets->map(function ($widget) {
                                return [
                                    'widget_type' => $widget->widget_type,
                                    'name_ar' => $widget->name_ar,
                                    'name_en' => $widget->name_en,
                                    'data_source' => $widget->data_source,
                                    'data_config' => $widget->data_config,
                                    'display_config' => $widget->display_config,
                                    'grid_x' => $widget->grid_x,
                                    'grid_y' => $widget->grid_y,
                                    'grid_width' => $widget->grid_width,
                                    'grid_height' => $widget->grid_height,
                                    'refresh_interval' => $widget->refresh_interval,
                                    'is_real_time' => $widget->is_real_time,
                                    'filter_by_user' => $widget->filter_by_user,
                                    'filter_by_department' => $widget->filter_by_department,
                                    'filter_by_role' => $widget->filter_by_role,
                                    'sort_order' => $widget->sort_order,
                                    'is_visible' => $widget->is_visible,
                                ];
                            })->toArray(),
                        ];
                    })->toArray(),
                ],
            ];

            return $this->success([
                'export' => $exportData,
            ], 'Dashboard exported successfully');
        } catch (\Exception $e) {
            \Log::error('[DashboardController] export error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import dashboard from JSON
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $validated = $request->validate([
                'export.version' => 'required|string',
                'export.dashboard.name_ar' => 'required|string|max:255',
                'export.dashboard.name_en' => 'nullable|string|max:255',
                'export.dashboard.description' => 'nullable|string',
                'export.dashboard.scope' => 'required|in:user,department,role,organization,system',
                'export.dashboard.visibility' => 'required|in:private,shared,department,role,organization,public',
                'export.dashboard.layout_config' => 'nullable|array',
                'export.dashboard.theme_config' => 'nullable|array',
                'export.dashboard.sections' => 'required|array',
                'export.dashboard.sections.*.name_ar' => 'required|string|max:255',
                'export.dashboard.sections.*.name_en' => 'nullable|string|max:255',
                'export.dashboard.sections.*.layout_type' => 'required|string',
                'export.dashboard.sections.*.layout_config' => 'nullable|array',
                'export.dashboard.sections.*.sort_order' => 'required|integer',
                'export.dashboard.sections.*.is_visible' => 'required|boolean',
                'export.dashboard.sections.*.widgets' => 'required|array',
            ]);

            $exportData = $validated['export'];
            $dashboardData = $exportData['dashboard'];

            // Create dashboard
            $dashboard = Dashboard::create([
                'name_ar' => $dashboardData['name_ar'],
                'name_en' => $dashboardData['name_en'] ?? null,
                'description' => $dashboardData['description'] ?? null,
                'scope' => $dashboardData['scope'],
                'visibility' => $dashboardData['visibility'],
                'user_id' => $dashboardData['scope'] === 'user' ? $user->id : null,
                'layout_config' => $dashboardData['layout_config'] ?? [],
                'theme_config' => $dashboardData['theme_config'] ?? [],
                'is_default' => false,
                'is_active' => true,
                'status' => 'draft',
                'version' => 1,
                'created_by' => $user->id,
            ]);

            // Create sections and widgets
            foreach ($dashboardData['sections'] as $sectionData) {
                $section = DashboardSection::create([
                    'dashboard_id' => $dashboard->id,
                    'name_ar' => $sectionData['name_ar'],
                    'name_en' => $sectionData['name_en'] ?? null,
                    'layout_type' => $sectionData['layout_type'],
                    'layout_config' => $sectionData['layout_config'] ?? [],
                    'sort_order' => $sectionData['sort_order'],
                    'is_visible' => $sectionData['is_visible'],
                    'created_by' => $user->id,
                ]);

                foreach ($sectionData['widgets'] as $widgetData) {
                    DashboardWidget::create([
                        'section_id' => $section->id,
                        'widget_type' => $widgetData['widget_type'],
                        'name_ar' => $widgetData['name_ar'],
                        'name_en' => $widgetData['name_en'] ?? null,
                        'data_source' => $widgetData['data_source'] ?? null,
                        'data_config' => $widgetData['data_config'] ?? [],
                        'display_config' => $widgetData['display_config'] ?? [],
                        'grid_x' => $widgetData['grid_x'],
                        'grid_y' => $widgetData['grid_y'],
                        'grid_width' => $widgetData['grid_width'],
                        'grid_height' => $widgetData['grid_height'],
                        'refresh_interval' => $widgetData['refresh_interval'] ?? 0,
                        'is_real_time' => $widgetData['is_real_time'] ?? false,
                        'filter_by_user' => $widgetData['filter_by_user'] ?? false,
                        'filter_by_department' => $widgetData['filter_by_department'] ?? false,
                        'filter_by_role' => $widgetData['filter_by_role'] ?? false,
                        'sort_order' => $widgetData['sort_order'],
                        'is_visible' => $widgetData['is_visible'],
                        'created_by' => $user->id,
                    ]);
                }
            }

            \Log::info('[DashboardController] Dashboard imported', [
                'dashboard_id' => $dashboard->id,
                'user_id' => $user->id,
            ]);

            return $this->success([
                'dashboard' => $dashboard,
            ], 'Dashboard imported successfully', [], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get dashboard version history
     */
    public function versions(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canView($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            // For now, return current version info
            // In a full implementation, this would query a versions table
            $versions = [
                [
                    'version' => $dashboard->version,
                    'status' => $dashboard->status,
                    'created_at' => $dashboard->updated_at,
                    'created_by' => $dashboard->updated_by ?? $dashboard->created_by,
                ],
            ];

            return $this->success([
                'versions' => $versions,
            ], 'Version history retrieved');
        } catch (\Exception $e) {
            \Log::error('[DashboardController] versions error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete dashboard
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $dashboard = Dashboard::find($id);

            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            if (!$this->permissionService->canDelete($user, $dashboard)) {
                return $this->error('Access denied', 403);
            }

            $dashboard->delete();

            return $this->success(['message' => 'Dashboard deleted successfully']);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] destroy error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Get fund statistics for dashboard widgets
     */
    public function fundStatistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $period = $request->input('period', 'today'); // today, week, month, year, all
            $registerId = $request->input('register_id'); // Optional: filter by specific register

            // Build query based on period
            $query = Receipt::where('status', 'issued')
                ->whereNull('deleted_at');

            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }

            // Filter by register if specified
            if ($registerId) {
                $query->where('register_id', $registerId);
            }

            // Get statistics
            $totalReceipts = $query->count();
            $totalAmount = $query->sum('total_amount');
            $pendingReceipts = Receipt::where('status', 'pending')
                ->whereNull('deleted_at')
                ->when($registerId, fn($q) => $q->where('register_id', $registerId))
                ->count();

            // Get receipts by register
            $receiptsByRegister = Receipt::select('register_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
                ->where('status', 'issued')
                ->whereNull('deleted_at')
                ->when($period === 'today', fn($q) => $q->whereDate('created_at', today()))
                ->when($period === 'week', fn($q) => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]))
                ->when($period === 'month', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year))
                ->when($period === 'year', fn($q) => $q->whereYear('created_at', now()->year))
                ->groupBy('register_id')
                ->with('register:id,name_ar,name_en')
                ->get();

            // Get recent receipts
            $recentReceipts = Receipt::with(['register:id,name_ar', 'createdBy:id,name'])
                ->whereNull('deleted_at')
                ->when($period === 'today', fn($q) => $q->whereDate('created_at', today()))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return $this->success([
                'statistics' => [
                    'total_receipts' => $totalReceipts,
                    'total_amount' => $totalAmount,
                    'pending_receipts' => $pendingReceipts,
                    'period' => $period,
                ],
                'by_register' => $receiptsByRegister,
                'recent_receipts' => $recentReceipts,
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] fundStatistics error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get widget data
     */
    public function widgetData(int $dashboardId, int $widgetId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $widget = DashboardWidget::find($widgetId);

            if (!$widget) {
                return $this->error('Widget not found', 404);
            }

            if (!$this->permissionService->canViewWidget($user, $widget)) {
                return $this->error('Access denied', 403);
            }

            $this->widgetEngine->setUser($user);
            $data = $this->widgetEngine->getWidgetData($widget);

            return $this->success($data);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] widgetData error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Get multiple widgets data (batch)
     */
    public function batchWidgetData(Request $request, int $dashboardId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $widgetIds = $request->input('widget_ids', []);

            if (empty($widgetIds)) {
                return $this->error('Widget IDs required', 422);
            }

            $widgets = DashboardWidget::whereIn('id', $widgetIds)
                ->whereHas('section', function ($query) use ($dashboardId) {
                    $query->where('dashboard_id', $dashboardId);
                })
                ->get();

            $this->widgetEngine->setUser($user);
            $results = $this->widgetEngine->getBatchWidgetsData($widgets->all());

            return $this->success(['widgets' => $results]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] batchWidgetData error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Get user dashboard preferences
     */
    public function preferences(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }
            
            $preferences = UserDashboardPreference::where('user_id', $user->id)->first();

            return $this->success([
                'preferences' => $preferences ?? [
                    'theme' => 'light',
                    'font_size' => 'medium',
                    'layout_density' => 'comfortable',
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] preferences error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Update user dashboard preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $validated = $request->validate([
                'theme' => 'sometimes|in:light,dark,auto',
                'color_palette' => 'nullable|string',
                'font_size' => 'sometimes|in:small,medium,large',
                'layout_density' => 'sometimes|in:compact,comfortable,spacious',
                'auto_refresh_widgets' => 'boolean',
                'default_refresh_interval' => 'integer|min:0|max:3600',
                'executive_mode' => 'boolean',
                'tv_mode' => 'boolean',
            ]);

            $preference = UserDashboardPreference::where('user_id', $user->id)->first();

            if (!$preference) {
                $preference = new UserDashboardPreference(['user_id' => $user->id]);
            }

            $preference->update($validated);

            return $this->success([
                'preferences' => $preference,
                'message' => 'Preferences updated successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] updatePreferences error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Admin: Get all dashboards with user assignments
     */
    public function adminList(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            // Check if user is admin
            if (!$user->can('manage-dashboards')) {
                return $this->error('Access denied', 403);
            }

            $dashboards = Dashboard::with(['user:id,name,name_ar', 'creator:id,name'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($dashboard) {
                    return [
                        'id' => $dashboard->id,
                        'name_ar' => $dashboard->name_ar,
                        'name_en' => $dashboard->name_en,
                        'scope' => $dashboard->scope,
                        'visibility' => $dashboard->visibility,
                        'assigned_to' => $dashboard->user ? [
                            'id' => $dashboard->user->id,
                            'name' => $dashboard->user->name,
                            'name_ar' => $dashboard->user->name_ar,
                        ] : null,
                        'created_by' => $dashboard->creator ? $dashboard->creator->name : null,
                        'is_active' => $dashboard->is_active,
                        'created_at' => $dashboard->created_at,
                    ];
                });

            return $this->success(['dashboards' => $dashboards]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] adminList error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }

    /**
     * Admin: Assign dashboard to user
     */
    public function assignToUser(Request $request, int $dashboardId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            if (!$user->can('manage-dashboards')) {
                return $this->error('Access denied', 403);
            }

            $validated = $request->validate([
                'user_id' => 'required|uuid|exists:users,id',
                'set_as_default' => 'boolean',
            ]);

            $dashboard = Dashboard::find($dashboardId);
            if (!$dashboard) {
                return $this->error('Dashboard not found', 404);
            }

            // Update dashboard assignment
            $dashboard->update([
                'scope' => 'user',
                'user_id' => $validated['user_id'],
                'visibility' => 'private',
            ]);

            // Set as default if requested
            if ($validated['set_as_default']) {
                $targetUser = User::find($validated['user_id']);
                if ($targetUser) {
                    $this->dashboardService->setUserDefaultDashboard($targetUser, $dashboard->id);
                }
            }

            return $this->success([
                'message' => 'Dashboard assigned successfully',
                'dashboard' => $dashboard->fresh(),
            ]);
        } catch (\Exception $e) {
            \Log::error('[DashboardController] assignToUser error', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('Server error', 500);
        }
    }
}

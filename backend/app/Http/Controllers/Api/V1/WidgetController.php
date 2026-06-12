<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Dashboard;
use App\Models\DashboardSection;
use App\Models\DashboardWidget;
use App\Services\DashboardPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WidgetController extends ApiController
{
    protected DashboardPermissionService $permissionService;

    public function __construct(DashboardPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Add widget to section
     */
    public function store(Request $request, int $dashboardId, int $sectionId): JsonResponse
    {
        $user = Auth::user();
        $dashboard = Dashboard::find($dashboardId);

        if (!$dashboard) {
            return $this->error('Dashboard not found', 404);
        }

        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $section = DashboardSection::find($sectionId);
        if (!$section || $section->dashboard_id !== $dashboardId) {
            return $this->error('Section not found', 404);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'widget_type' => 'required|string',
            'data_source' => 'nullable|string',
            'grid_width' => 'integer|min:1|max:12',
            'grid_height' => 'integer|min:1|max:20',
            'data_config' => 'nullable|array',
            'display_config' => 'nullable|array',
            'filter_config' => 'nullable|array',
            'filter_by_user' => 'boolean',
            'filter_by_department' => 'boolean',
            'filter_by_role' => 'boolean',
            'refresh_interval' => 'integer|min:0|max:3600',
            'is_real_time' => 'boolean',
        ]);

        $widget = DashboardWidget::create([
            ...$validated,
            'section_id' => $sectionId,
            'sort_order' => DashboardWidget::where('section_id', $sectionId)->max('sort_order') + 1,
            'grid_x' => $validated['grid_x'] ?? 0,
            'grid_y' => $validated['grid_y'] ?? 0,
            'grid_width' => $validated['grid_width'] ?? 6,
            'grid_height' => $validated['grid_height'] ?? 4,
            'is_visible' => true,
            'is_editable' => true,
            'is_removable' => true,
            'created_by' => $user->id,
        ]);

        Log::info('[WidgetController] Widget created', [
            'widget_id' => $widget->id,
            'dashboard_id' => $dashboardId,
            'user_id' => $user->id,
        ]);

        return $this->success([
            'widget' => $widget,
        ], 'Widget added successfully', [], 201);
    }

    /**
     * Update widget
     */
    public function update(Request $request, int $widgetId): JsonResponse
    {
        $user = Auth::user();
        $widget = DashboardWidget::find($widgetId);

        if (!$widget) {
            return $this->error('Widget not found', 404);
        }

        $dashboard = $widget->section->dashboard;
        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'grid_x' => 'integer',
            'grid_y' => 'integer',
            'grid_width' => 'integer|min:1|max:12',
            'grid_height' => 'integer|min:1|max:20',
            'sort_order' => 'integer',
            'data_config' => 'nullable|array',
            'display_config' => 'nullable|array',
            'filter_config' => 'nullable|array',
            'refresh_interval' => 'integer|min:0|max:3600',
            'is_visible' => 'boolean',
        ]);

        $widget->update($validated);

        return $this->success([
            'widget' => $widget->fresh(),
        ], 'Widget updated successfully');
    }

    /**
     * Update widget positions (for drag-and-drop)
     */
    public function updatePositions(Request $request, int $dashboardId): JsonResponse
    {
        $user = Auth::user();
        $dashboard = Dashboard::find($dashboardId);

        if (!$dashboard) {
            return $this->error('Dashboard not found', 404);
        }

        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|integer|exists:dashboard_widgets,id',
            'widgets.*.grid_x' => 'required|integer',
            'widgets.*.grid_y' => 'required|integer',
            'widgets.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['widgets'] as $widgetData) {
            DashboardWidget::where('id', $widgetData['id'])->update([
                'grid_x' => $widgetData['grid_x'],
                'grid_y' => $widgetData['grid_y'],
                'sort_order' => $widgetData['sort_order'],
            ]);
        }

        return $this->success([], 'Widget positions updated successfully');
    }

    /**
     * Remove widget
     */
    public function destroy(int $widgetId): JsonResponse
    {
        $user = Auth::user();
        $widget = DashboardWidget::find($widgetId);

        if (!$widget) {
            return $this->error('Widget not found', 404);
        }

        $dashboard = $widget->section->dashboard;
        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        if (!$widget->is_removable) {
            return $this->error('This widget cannot be removed', 400);
        }

        $widget->delete();

        return $this->success([], 'Widget removed successfully');
    }

    /**
     * Add section to dashboard
     */
    public function addSection(Request $request, int $dashboardId): JsonResponse
    {
        $user = Auth::user();
        $dashboard = Dashboard::find($dashboardId);

        if (!$dashboard) {
            return $this->error('Dashboard not found', 404);
        }

        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'layout_type' => 'sometimes|string',
            'layout_config' => 'nullable|array',
            'background_color' => 'nullable|string',
            'is_collapsible' => 'boolean',
        ]);

        $section = DashboardSection::create([
            ...$validated,
            'dashboard_id' => $dashboardId,
            'sort_order' => DashboardSection::where('dashboard_id', $dashboardId)->max('sort_order') + 1,
            'is_visible' => true,
            'created_by' => $user->id,
        ]);

        return $this->success([
            'section' => $section,
        ], 'Section added successfully', [], 201);
    }

    /**
     * Update section
     */
    public function updateSection(Request $request, int $sectionId): JsonResponse
    {
        $user = Auth::user();
        $section = DashboardSection::find($sectionId);

        if (!$section) {
            return $this->error('Section not found', 404);
        }

        $dashboard = Dashboard::find($section->dashboard_id);
        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $validated = $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'layout_type' => 'sometimes|string',
            'layout_config' => 'nullable|array',
            'sort_order' => 'integer',
            'is_collapsible' => 'boolean',
            'is_visible' => 'boolean',
        ]);

        $section->update($validated);

        return $this->success([
            'section' => $section->fresh(),
        ], 'Section updated successfully');
    }

    /**
     * Remove section
     */
    public function removeSection(int $sectionId): JsonResponse
    {
        $user = Auth::user();
        $section = DashboardSection::find($sectionId);

        if (!$section) {
            return $this->error('Section not found', 404);
        }

        $dashboard = Dashboard::find($section->dashboard_id);
        if (!$this->permissionService->canEdit($user, $dashboard)) {
            return $this->error('Access denied', 403);
        }

        $section->delete();

        return $this->success([], 'Section removed successfully');
    }
}

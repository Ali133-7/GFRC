<?php

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Widget Engine for Dynamic Data Filtering
 * 
 * Fetches and filters widget data based on:
 * - Current User
 * - Current Department
 * - Current Role
 * - Current Branch
 * - Custom Filters
 */
class WidgetEngine
{
    protected User $user;

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get widget data with dynamic filtering
     */
    public function getWidgetData(DashboardWidget $widget): array
    {
        Log::info('[WidgetEngine] Fetching widget data', [
            'widget_id' => $widget->id,
            'widget_type' => $widget->widget_type,
            'data_source' => $widget->data_source,
        ]);

        // Apply context filters
        $filters = $this->buildContextFilters($widget);

        try {
            return match ($widget->data_source) {
                'receipts' => $this->getReceiptsData($widget, $filters),
                'receipts_total' => $this->getReceiptsTotal($widget, $filters),
                'workflows' => $this->getWorkflowsData($widget, $filters),
                'workflow_tasks' => $this->getWorkflowTasks($widget, $filters),
                'registers' => $this->getRegistersData($widget, $filters),
                'users' => $this->getUsersData($widget, $filters),
                'departments' => $this->getDepartmentsData($widget, $filters),
                'audit_logs' => $this->getAuditLogsData($widget, $filters),
                'custom_query' => $this->getCustomQueryData($widget, $filters),
                'api' => $this->getApiData($widget, $filters),
                default => $this->getStaticData($widget),
            };
        } catch (\Exception $e) {
            Log::error('[WidgetEngine] Error fetching data', [
                'widget_id' => $widget->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Build context filters based on widget configuration
     */
    protected function buildContextFilters(DashboardWidget $widget): array
    {
        $filters = $widget->custom_filters ?? [];

        // Apply automatic context filters
        if ($widget->filter_by_user) {
            $filters['user_id'] = $this->user->id;
        }

        if ($widget->filter_by_department) {
            $filters['department_id'] = $this->user->department_id;
        }

        if ($widget->filter_by_role) {
            $filters['role_id'] = $this->user->role_id;
        }

        if ($widget->filter_by_branch) {
            $filters['branch_id'] = $this->user->branch_id ?? null;
        }

        return $filters;
    }

    /**
     * Get receipts data
     */
    protected function getReceiptsData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('receipts')
            ->select('receipts.*', 'registers.name_ar as register_name', 'users.name as user_name')
            ->leftJoin('registers', 'receipts.register_id', '=', 'registers.id')
            ->leftJoin('users', 'receipts.created_by', '=', 'users.id')
            ->whereNull('receipts.deleted_at');

        // Apply filters
        $this->applyFilters($query, $filters);

        $limit = $widget->display_config['limit'] ?? 10;
        $receipts = $query->orderBy('receipts.created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'data' => $receipts,
            'count' => $receipts->count(),
        ];
    }

    /**
     * Get receipts total (KPI)
     */
    protected function getReceiptsTotal(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('receipts')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')
            ->whereNull('deleted_at');

        // Apply filters
        $this->applyFilters($query, $filters);

        // Date range filter
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Today by default
        if (!isset($filters['date_from']) && !isset($filters['date_to'])) {
            $query->whereDate('created_at', today());
        }

        $result = $query->first();

        return [
            'success' => true,
            'data' => [
                'count' => (int) ($result->count ?? 0),
                'total' => (float) ($result->total ?? 0),
            ],
        ];
    }

    /**
     * Get workflows data
     */
    protected function getWorkflowsData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('workflow_executions')
            ->select('workflow_executions.*', 'workflows.name_ar as workflow_name')
            ->leftJoin('workflows', 'workflow_executions.workflow_id', '=', 'workflows.id')
            ->whereNull('workflow_executions.deleted_at');

        $this->applyFilters($query, $filters);

        $limit = $widget->display_config['limit'] ?? 10;
        $workflows = $query->orderBy('workflow_executions.created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'data' => $workflows,
            'count' => $workflows->count(),
        ];
    }

    /**
     * Get workflow tasks assigned to user
     */
    protected function getWorkflowTasks(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('workflow_tasks')
            ->select('workflow_tasks.*', 'workflows.name_ar as workflow_name')
            ->leftJoin('workflow_executions', 'workflow_tasks.execution_id', '=', 'workflow_executions.id')
            ->leftJoin('workflows', 'workflow_executions.workflow_id', '=', 'workflows.id')
            ->where('workflow_tasks.assigned_to', $this->user->id)
            ->where('workflow_tasks.status', 'pending');

        $this->applyFilters($query, $filters);

        $tasks = $query->orderBy('workflow_tasks.created_at', 'desc')
            ->limit($widget->display_config['limit'] ?? 10)
            ->get();

        return [
            'success' => true,
            'data' => $tasks,
            'count' => $tasks->count(),
            'pending_count' => $tasks->count(),
        ];
    }

    /**
     * Get registers data
     */
    protected function getRegistersData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('registers')
            ->select('registers.*', DB::raw('COUNT(records.id) as records_count'))
            ->leftJoin('records', 'registers.id', '=', 'records.register_id')
            ->where('registers.is_active', true)
            ->groupBy('registers.id');

        $this->applyFilters($query, $filters);

        $registers = $query->orderBy('registers.name_ar')->get();

        return [
            'success' => true,
            'data' => $registers,
            'count' => $registers->count(),
        ];
    }

    /**
     * Get users data
     */
    protected function getUsersData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('users')
            ->select('users.*', 'departments.name_ar as department_name', 'roles.name as role_name')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true);

        $this->applyFilters($query, $filters);

        $users = $query->orderBy('users.name')->get();

        return [
            'success' => true,
            'data' => $users,
            'count' => $users->count(),
        ];
    }

    /**
     * Get departments data
     */
    protected function getDepartmentsData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('departments')
            ->select('departments.*', DB::raw('COUNT(users.id) as users_count'))
            ->leftJoin('users', 'departments.id', '=', 'users.department_id')
            ->where('departments.is_active', true)
            ->groupBy('departments.id');

        $this->applyFilters($query, $filters);

        $departments = $query->orderBy('departments.name_ar')->get();

        return [
            'success' => true,
            'data' => $departments,
            'count' => $departments->count(),
        ];
    }

    /**
     * Get audit logs data
     */
    protected function getAuditLogsData(DashboardWidget $widget, array $filters): array
    {
        $query = DB::table('audit_logs')
            ->select('audit_logs.*', 'users.name as user_name')
            ->leftJoin('users', 'audit_logs.causer_id', '=', 'users.id')
            ->where('audit_logs.causer_id', $this->user->id);

        $this->applyFilters($query, $filters);

        $limit = $widget->display_config['limit'] ?? 20;
        $logs = $query->orderBy('audit_logs.created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'data' => $logs,
            'count' => $logs->count(),
        ];
    }

    /**
     * Get custom query data
     */
    protected function getCustomQueryData(DashboardWidget $widget, array $filters): array
    {
        $queryConfig = $widget->data_config['query'] ?? null;

        if (!$queryConfig) {
            return ['success' => false, 'error' => 'No query configured', 'data' => []];
        }

        try {
            $results = DB::select($queryConfig, $filters);
            return ['success' => true, 'data' => $results, 'count' => count($results)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Get API data
     */
    protected function getApiData(DashboardWidget $widget, array $filters): array
    {
        $apiConfig = $widget->data_config['api'] ?? null;

        if (!$apiConfig) {
            return ['success' => false, 'error' => 'No API configured', 'data' => []];
        }

        $url = $apiConfig['url'];
        $method = $apiConfig['method'] ?? 'GET';
        $headers = $apiConfig['headers'] ?? [];

        try {
            $response = Http::withHeaders($headers)
                ->send($method, $url, [
                    'query' => $filters,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json(), 'count' => count($response->json())];
            }

            return ['success' => false, 'error' => 'API request failed', 'data' => []];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Get static data (for static widgets)
     */
    protected function getStaticData(DashboardWidget $widget): array
    {
        return [
            'success' => true,
            'data' => $widget->data_config['static_data'] ?? [],
        ];
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * Get multiple widgets data in batch
     */
    public function getBatchWidgetsData(array $widgets): array
    {
        $results = [];

        foreach ($widgets as $widget) {
            $results[$widget->id] = $this->getWidgetData($widget);
        }

        return $results;
    }
}

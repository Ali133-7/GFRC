<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportField;
use App\Models\ReportFilter;
use App\Models\ReportAggregation;
use App\Models\ReportGrouping;
use App\Models\ReportChart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class ReportEngine
{
    protected const MAX_ROWS = 10000;
    protected const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Execute a report and return results
     */
    public function executeReport(string $reportId, array $filters = [], array $options = []): array
    {
        $startTime = microtime(true);
        
        // Load report definition
        $report = Report::with([
            'fields' => fn($q) => $q->where('is_visible', true)->orderBy('sort_order'),
            'filters',
            'aggregations' => fn($q) => $q->orderBy('sort_order'),
            'groupings' => fn($q) => $q->orderBy('sort_order'),
        ])->findOrFail($reportId);
        
        // Check permissions
        $this->checkPermission($report, 'execute');
        
        // Build cache key
        $cacheKey = $this->buildCacheKey($reportId, $filters);
        
        // Try cache first
        if ($options['use_cache'] ?? true) {
            if ($cached = Cache::get($cacheKey)) {
                return array_merge($cached, ['from_cache' => true]);
            }
        }
        
        // Build query
        $query = $this->buildQuery($report, $filters);
        
        // Apply pagination
        $perPage = min($options['per_page'] ?? 50, 100);
        $page = $options['page'] ?? 1;
        
        // Execute query
        try {
            $total = $query->count();
            $results = $query->limit(self::MAX_ROWS)
                            ->offset(($page - 1) * $perPage)
                            ->get();
            
            // Calculate aggregations
            $aggregations = $this->calculateAggregations($report, $filters);
            
            // Format results
            $formattedResults = $this->formatResults($report, $results);
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Log execution
            $this->logExecution($report, $filters, $results->count(), $executionTime, $cacheKey);
            
            $responseData = [
                'report' => [
                    'id' => $report->id,
                    'name' => $report->name,
                    'name_ar' => $report->name_ar,
                    'code' => $report->code,
                ],
                'data' => $formattedResults,
                'aggregations' => $aggregations,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil(min($total, self::MAX_ROWS) / $perPage),
                ],
                'meta' => [
                    'execution_time_ms' => round($executionTime, 2),
                    'rows_returned' => $results->count(),
                    'from_cache' => false,
                ],
            ];
            
            // Cache the result
            if ($options['use_cache'] ?? true) {
                Cache::put($cacheKey, $responseData, self::CACHE_TTL);
            }
            
            return $responseData;
            
        } catch (QueryException $e) {
            Log::error('Report execution failed', [
                'report_id' => $reportId,
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Build database query from report definition
     */
    protected function buildQuery(Report $report, array $filters): \Illuminate\Database\Query\Builder
    {
        $table = $this->resolveTableName($report->data_source);
        $query = DB::table($table);
        
        // Select fields
        $selectFields = $this->buildSelectFields($report);
        $query->select($selectFields);
        
        // Apply filters
        $this->applyFilters($query, $report, $filters);
        
        // Apply groupings
        $this->applyGroupings($query, $report);
        
        // Apply sorting
        $this->applySorting($query, $report, $filters);
        
        // Apply joins if needed
        $this->applyJoins($query, $report);
        
        return $query;
    }
    
    /**
     * Build SELECT clause from report fields
     */
    protected function buildSelectFields(Report $report): array
    {
        $fields = [];
        
        foreach ($report->fields as $field) {
            $columnName = $field->table_alias 
                ? "{$field->table_alias}.{$field->field_name}"
                : $field->field_name;
            
            $fields[] = "{$columnName} AS {$field->field_name}";
        }
        
        return empty($fields) ? ['*'] : $fields;
    }
    
    /**
     * Apply WHERE clauses based on filters
     */
    protected function applyFilters($query, Report $report, array $filters): void
    {
        foreach ($report->filters as $filterDef) {
            $filterValue = $filters[$filterDef->field_name] ?? null;
            
            // Skip if no value and not required
            if ($filterValue === null && !$filterDef->is_required) {
                continue;
            }
            
            // Apply default value if no filter provided
            if ($filterValue === null && $filterDef->default_value) {
                $filterValue = $filterDef->default_value;
            }
            
            $columnName = $filterDef->field_name;
            
            switch ($filterDef->filter_type) {
                case 'date_range':
                    if (isset($filterValue['start'])) {
                        $query->where($columnName, '>=', $filterValue['start']);
                    }
                    if (isset($filterValue['end'])) {
                        $query->where($columnName, '<=', $filterValue['end']);
                    }
                    break;
                    
                case 'multi_select':
                    $values = is_array($filterValue) ? $filterValue : explode(',', $filterValue);
                    $query->whereIn($columnName, $values);
                    break;
                    
                case 'text':
                    if ($filterDef->operator === 'like') {
                        $query->where($columnName, 'LIKE', "%{$filterValue}%");
                    } else {
                        $query->where($columnName, $filterDef->operator, $filterValue);
                    }
                    break;
                    
                case 'number':
                    $query->where($columnName, $filterDef->operator, $filterValue);
                    break;
                    
                case 'boolean':
                    $query->where($columnName, '=', (bool) $filterValue);
                    break;
                    
                default:
                    $query->where($columnName, $filterDef->operator ?? '=', $filterValue);
            }
        }
    }
    
    /**
     * Apply GROUP BY clauses
     */
    protected function applyGroupings($query, Report $report): void
    {
        foreach ($report->groupings as $grouping) {
            $query->groupBy($grouping->field_name);
        }
    }
    
    /**
     * Apply ORDER BY clauses
     */
    protected function applySorting($query, Report $report, array $filters): void
    {
        // Default sorting from report definition
        foreach ($report->fields as $field) {
            if ($field->is_sortable && isset($filters['sort_by']) && $filters['sort_by'] === $field->field_name) {
                $direction = ($filters['sort_direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
                $query->orderBy($field->field_name, $direction);
                return;
            }
        }
        
        // Default to first sortable field
        foreach ($report->fields as $field) {
            if ($field->is_sortable) {
                $query->orderBy($field->field_name, 'ASC');
                break;
            }
        }
    }
    
    /**
     * Apply JOINs for related tables
     */
    protected function applyJoins($query, Report $report): void
    {
        $config = $report->configuration;
        $joins = $config['joins'] ?? [];
        
        foreach ($joins as $join) {
            $query->leftJoin(
                $join['table'],
                $join['first'],
                $join['operator'] ?? '=',
                $join['second']
            );
        }
    }
    
    /**
     * Calculate aggregations for the report
     */
    protected function calculateAggregations(Report $report, array $filters): array
    {
        $aggregations = [];
        
        foreach ($report->aggregations as $agg) {
            try {
                $table = $this->resolveTableName($report->data_source);
                $query = DB::table($table);
                
                // Apply same filters as main query
                $this->applyFilters($query, $report, $filters);
                
                $result = match (strtoupper($agg->aggregation_type)) {
                    'SUM' => $query->sum($agg->field_name),
                    'COUNT' => $query->count('*'),
                    'AVG' => $query->avg($agg->field_name),
                    'MIN' => $query->min($agg->field_name),
                    'MAX' => $query->max($agg->field_name),
                    'CUSTOM' => $this->executeCustomExpression($query, $agg->expression),
                    default => 0,
                };
                
                $aggregations[] = [
                    'field' => $agg->field_name,
                    'type' => $agg->aggregation_type,
                    'alias' => $agg->alias,
                    'alias_ar' => $agg->alias_ar,
                    'value' => round((float) $result, $agg->decimal_places),
                    'format' => $agg->format,
                ];
            } catch (\Exception $e) {
                Log::warning('Aggregation failed', [
                    'aggregation' => $agg->field_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $aggregations;
    }
    
    /**
     * Execute custom aggregation expression
     */
    protected function executeCustomExpression($query, ?array $expression): float
    {
        if (!$expression) {
            return 0;
        }
        
        // Example: (amount - discount + tax)
        // This is simplified - in production, use a proper expression parser
        $fields = $expression['fields'] ?? [];
        $operation = $expression['operation'] ?? 'sum';
        
        $values = [];
        foreach ($fields as $field) {
            $values[] = DB::raw("COALESCE({$field}, 0)");
        }
        
        $expressionStr = implode(' ', $values);
        
        return match ($operation) {
            'sum' => $query->sum(DB::raw($expressionStr)),
            'avg' => $query->avg(DB::raw($expressionStr)),
            default => 0,
        };
    }
    
    /**
     * Format results based on field configuration
     */
    protected function formatResults(Report $report, $results): array
    {
        $formatted = [];
        
        foreach ($results as $row) {
            $formattedRow = [];
            
            foreach ($report->fields as $field) {
                $value = $row->{$field->field_name} ?? null;
                
                // Apply formatting
                if ($field->formatting) {
                    $value = $this->applyFieldFormatting($value, $field->formatting);
                }
                
                $formattedRow[$field->field_name] = $value;
            }
            
            $formatted[] = $formattedRow;
        }
        
        return $formatted;
    }
    
    /**
     * Apply field-level formatting
     */
    protected function applyFieldFormatting($value, array $formatting)
    {
        if ($value === null) {
            return null;
        }
        
        return match ($formatting['type'] ?? null) {
            'date' => date($formatting['format'] ?? 'Y-m-d', strtotime($value)),
            'datetime' => date($formatting['format'] ?? 'Y-m-d H:i:s', strtotime($value)),
            'currency' => number_format((float) $value, $formatting['decimals'] ?? 2),
            'percentage' => number_format((float) $value * 100, $formatting['decimals'] ?? 2) . '%',
            'number' => number_format((float) $value, $formatting['decimals'] ?? 0),
            default => $value,
        };
    }
    
    /**
     * Build cache key from report and filters
     */
    protected function buildCacheKey(string $reportId, array $filters): string
    {
        $filterHash = md5(json_encode($filters, SORT_KEYS));
        return "report:{$reportId}:{$filterHash}";
    }
    
    /**
     * Log report execution for audit
     */
    protected function logExecution(Report $report, array $filters, int $rows, float $time, ?string $cacheKey): void
    {
        \App\Models\ReportExecution::create([
            'report_id' => $report->id,
            'user_id' => auth()->id(),
            'filters_applied' => $filters,
            'rows_returned' => $rows,
            'execution_time_ms' => $time,
            'cache_key' => $cacheKey,
            'from_cache' => false,
            'ip_address' => request()->ip(),
        ]);
    }
    
    /**
     * Check if user has permission to execute report
     */
    protected function checkPermission(Report $report, string $permissionType): void
    {
        // System reports are always accessible
        if ($report->is_system) {
            return;
        }
        
        // Check user-specific permissions
        $user = auth()->user();
        
        // Check report permissions table
        $hasPermission = \App\Models\ReportPermission::where('report_id', $report->id)
            ->where('permissionable_type', \App\Models\User::class)
            ->where('permissionable_id', $user->id)
            ->where('permission_type', $permissionType)
            ->exists();
        
        if ($hasPermission) {
            return;
        }
        
        // Check role-based permissions
        foreach ($user->roles as $role) {
            $hasPermission = \App\Models\ReportPermission::where('report_id', $report->id)
                ->where('permissionable_type', \App\Models\Role::class)
                ->where('permissionable_id', $role->id)
                ->where('permission_type', $permissionType)
                ->exists();
            
            if ($hasPermission) {
                return;
            }
        }
        
        // Check visibility
        if ($report->visibility === 'public') {
            return;
        }
        
        if ($report->visibility === 'shared' && $report->created_by === $user->id) {
            return;
        }
        
        // Deny access
        abort(403, 'You do not have permission to execute this report');
    }
    
    /**
     * Resolve table name from data source
     */
    protected function resolveTableName(string $dataSource): string
    {
        // Check if it's a model class
        if (class_exists($dataSource)) {
            return (new $dataSource)->getTable();
        }
        
        // Otherwise assume it's a table name
        return $dataSource;
    }
    
    /**
     * Get available fields for a data source
     */
    public function getAvailableFields(string $dataSource): array
    {
        $table = $this->resolveTableName($dataSource);
        
        $columns = DB::select("DESCRIBE {$table}");
        
        $fields = [];
        foreach ($columns as $column) {
            $fields[] = [
                'name' => $column->Field,
                'type' => $this->mapDatabaseType($column->Type),
                'nullable' => $column->Null === 'YES',
                'default' => $column->Default,
            ];
        }
        
        return $fields;
    }
    
    /**
     * Map database type to application type
     */
    protected function mapDatabaseType(string $dbType): string
    {
        return match (true) {
            str_contains($dbType, 'int') => 'number',
            str_contains($dbType, 'decimal') || str_contains($dbType, 'float') => 'currency',
            str_contains($dbType, 'date') => 'date',
            str_contains($dbType, 'time') => 'datetime',
            str_contains($dbType, 'bool') => 'boolean',
            str_contains($dbType, 'text') => 'text',
            default => 'string',
        };
    }
    
    /**
     * Generate chart data from report results
     */
    public function generateChartData(string $reportId, array $filters, int $chartId): array
    {
        $report = Report::with('charts')->findOrFail($reportId);
        
        $chart = $report->charts->find($chartId);
        
        if (!$chart) {
            throw new \Exception('Chart not found');
        }
        
        // Execute report
        $results = $this->executeReport($reportId, $filters, ['use_cache' => true]);
        
        $config = $chart->configuration;
        
        return [
            'chart' => [
                'id' => $chart->id,
                'name' => $chart->chart_name,
                'type' => $chart->chart_type,
            ],
            'data' => [
                'labels' => $this->extractLabels($results['data'], $chart),
                'datasets' => $this->extractDatasets($results['data'], $chart, $config),
            ],
            'options' => $config['options'] ?? [],
        ];
    }
    
    /**
     * Extract labels for chart
     */
    protected function extractLabels(array $data, \App\Models\ReportChart $chart): array
    {
        $field = $chart->x_axis_field ?? $chart->group_by_field;
        
        if (!$field) {
            return array_keys($data);
        }
        
        return array_column($data, $field);
    }
    
    /**
     * Extract datasets for chart
     */
    protected function extractDatasets(array $data, \App\Models\ReportChart $chart, array $config): array
    {
        $datasets = [];
        
        $yField = $chart->y_axis_field;
        
        if ($yField) {
            $datasets[] = [
                'label' => $yField,
                'data' => array_column($data, $yField),
                'backgroundColor' => $config['colors'] ?? self::DEFAULT_COLORS,
                'borderColor' => $config['borderColors'] ?? self::DEFAULT_BORDER_COLORS,
                'borderWidth' => 2,
            ];
        }
        
        return $datasets;
    }
    
    protected const DEFAULT_COLORS = [
        'rgba(54, 162, 235, 0.6)',
        'rgba(255, 99, 132, 0.6)',
        'rgba(75, 192, 192, 0.6)',
        'rgba(255, 206, 86, 0.6)',
        'rgba(153, 102, 255, 0.6)',
    ];
    
    protected const DEFAULT_BORDER_COLORS = [
        'rgba(54, 162, 235, 1)',
        'rgba(255, 99, 132, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(153, 102, 255, 1)',
    ];
}

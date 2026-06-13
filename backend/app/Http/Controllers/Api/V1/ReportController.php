<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Report;
use App\Models\ReportExecution;
use App\Services\Reports\BusinessFieldService;
use App\Services\Reports\ReportEngine;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportController extends ApiController
{
    public function __construct(
        protected ReportEngine $reportEngine,
        protected ReportExporter $exporter,
        protected BusinessFieldService $businessFieldService,
    ) {}

    /**
     * List all available reports
     */
    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['creator', 'register'])
            ->active()
            ->visibleToUser(Auth::user());

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('data_source')) {
            $query->where('data_source', $request->data_source);
        }

        if ($request->boolean('paginate')) {
            $paginated = $query->paginate($request->per_page ?? 25);

            return $this->success($paginated->items(), '', $this->paginationMeta($paginated));
        }

        return $this->success($query->get());
    }

    /**
     * Get report definition with all configurations
     */
    public function show(string $id): JsonResponse
    {
        $report = Report::with([
            'fields',
            'filters',
            'aggregations',
            'groupings',
            'charts',
            'creator',
            'register',
        ])->findOrFail($id);

        $this->authorize('view', $report);

        return $this->success(['report' => $report]);
    }

    /**
     * Create a new report
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Report::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50|unique:reports,code',
            'description' => 'nullable|string',
            'data_source' => 'required|string',
            'configuration' => 'nullable|array',
            'type' => 'sometimes|in:custom,system,analytics',
            'visibility' => 'sometimes|in:private,shared,public,role,department',
            'scope' => 'sometimes|in:user,role,department,system',
            'register_id' => 'nullable|uuid|exists:registers,id',
            'fields' => 'nullable|array',
            'filters' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'groupings' => 'nullable|array',
            'charts' => 'nullable|array',
        ]);

        $report = DB::transaction(function () use ($validated) {
            $report = Report::create([
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'code' => $validated['code'] ?? strtoupper('RPT_'.Str::random(8)),
                'description' => $validated['description'] ?? null,
                'data_source' => $validated['data_source'],
                'configuration' => $validated['configuration'] ?? [],
                'type' => $validated['type'] ?? 'custom',
                'visibility' => $validated['visibility'] ?? 'private',
                'scope' => $validated['scope'] ?? 'user',
                'register_id' => $validated['register_id'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Create fields
            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $fieldData) {
                    $report->fields()->create($fieldData);
                }
            }

            // Create filters
            if (isset($validated['filters'])) {
                foreach ($validated['filters'] as $filterData) {
                    $report->filters()->create($filterData);
                }
            }

            // Create aggregations
            if (isset($validated['aggregations'])) {
                foreach ($validated['aggregations'] as $aggData) {
                    $report->aggregations()->create($aggData);
                }
            }

            // Create groupings
            if (isset($validated['groupings'])) {
                foreach ($validated['groupings'] as $groupData) {
                    $report->groupings()->create($groupData);
                }
            }

            // Create charts
            if (isset($validated['charts'])) {
                foreach ($validated['charts'] as $chartData) {
                    $report->charts()->create($chartData);
                }
            }

            return $report->load(['fields', 'filters', 'aggregations', 'groupings', 'charts']);
        });

        return $this->success(['report' => $report], 'Report created successfully', [], 201);
    }

    /**
     * Update an existing report
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('update', $report);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
            'visibility' => 'sometimes|in:private,shared,public,role,department',
            'is_active' => 'sometimes|boolean',
        ]);

        $report->update($validated);

        return $this->success(['report' => $report->fresh()]);
    }

    /**
     * Delete a report
     */
    public function destroy(string $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('delete', $report);

        if ($report->is_system) {
            return $this->error('Cannot delete system reports', 403);
        }

        $report->delete();

        return $this->success([], 'Report deleted successfully');
    }

    /**
     * Execute a report and get results
     */
    public function execute(string $id, Request $request): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('execute', $report);

        $filters = $request->input('filters', []);
        $options = [
            'page' => $request->input('page', 1),
            'per_page' => min($request->input('per_page', 50), 100),
            'use_cache' => $request->boolean('use_cache', true),
        ];

        try {
            $results = $this->reportEngine->executeReport($id, $filters, $options);

            return $this->success($results);
        } catch (\Exception $e) {
            return $this->error('Report execution failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get chart data for a report
     */
    public function chart(string $reportId, int $chartId, Request $request): JsonResponse
    {
        $report = Report::findOrFail($reportId);
        $this->authorize('execute', $report);

        $filters = $request->input('filters', []);

        try {
            $chartData = $this->reportEngine->generateChartData($reportId, $filters, $chartId);

            return $this->success($chartData);
        } catch (\Exception $e) {
            return $this->error('Chart generation failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Export report results
     */
    public function export(string $id, Request $request): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('execute', $report);

        $format = $request->input('format', 'json');
        $filters = $request->input('filters', []);

        try {
            $results = $this->reportEngine->executeReport($id, $filters, ['use_cache' => true]);

            $exportResult = $this->exporter->export($results, $format, [
                'report_name' => $report->name,
                'generated_at' => now(),
                'generated_by' => Auth::user()->name,
            ]);

            // Log export
            ReportExecution::create([
                'report_id' => $id,
                'user_id' => Auth::id(),
                'filters_applied' => $filters,
                'rows_returned' => count($results['data'] ?? []),
                'execution_time_ms' => $results['meta']['execution_time_ms'] ?? 0,
                'export_format' => $exportResult['format'] ?? $format,
                'from_cache' => $results['meta']['from_cache'] ?? false,
                'ip_address' => request()->ip(),
            ]);

            if ($format === 'json') {
                return response()->json($exportResult);
            }

            // For PDF and Excel, return download URL or base64
            return $this->success($exportResult);

        } catch (\Exception $e) {
            return $this->error('Export failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Download exported file
     */
    public function download(string $filename): Response
    {
        $path = storage_path('app/temp/'.$filename);

        if (! file_exists($path)) {
            abort(404, 'File not found');
        }

        // Clean up old files (older than 24 hours)
        $this->cleanupOldFiles();

        return response()->download($path, $filename);
    }

    /**
     * Clean up export files older than 24 hours
     */
    protected function cleanupOldFiles(): void
    {
        $tempDir = storage_path('app/temp');

        if (! is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir.'/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > 86400)) {
                @unlink($file);
            }
        }
    }

    /**
     * Get available fields for a data source
     */
    public function availableFields(Request $request): JsonResponse
    {
        try {
            $dataSource = $request->input('data_source', 'receipts');
            $registerIds = $request->input('register_ids', []);
            $developerMode = $request->boolean('developer_mode');
            $user = Auth::user();

            if (is_string($registerIds)) {
                $registerIds = array_filter(explode(',', $registerIds));
            }

            // Hide system tables unless admin explicitly enables developer mode
            if (
                BusinessFieldService::isSystemTable($dataSource) &&
                (! $developerMode || ! $user || ! $user->hasRole('Administrator'))
            ) {
                return $this->success(['fields' => []]);
            }

            \Log::info('Getting available fields', [
                'data_source' => $dataSource,
                'register_ids' => $registerIds,
            ]);

            // Get table name - use data_source directly or map common names
            $tableMap = [
                'receipts' => 'receipts',
                'registers' => 'registers',
                'users' => 'users',
                'transactions' => 'transactions',
            ];
            $table = $tableMap[$dataSource] ?? $dataSource;

            \Log::info('Using table name', ['table' => $table]);

            // Get table columns
            try {
                $columns = \DB::select("PRAGMA table_info({$table})");
                \Log::info('Table columns', ['count' => count($columns), 'columns' => $columns]);
            } catch (\Exception $e) {
                \Log::error('Failed to get table columns', ['table' => $table, 'error' => $e->getMessage()]);
                $columns = [];
            }

            $fields = [];
            foreach ($columns as $column) {
                // Skip timestamp columns
                if (in_array($column->name, ['created_at', 'updated_at', 'deleted_at'])) {
                    continue;
                }

                // Map database type to application type
                $dbType = strtolower($column->type);
                $fieldType = match (true) {
                    str_contains($dbType, 'int') => 'number',
                    str_contains($dbType, 'decimal') || str_contains($dbType, 'real') => 'currency',
                    str_contains($dbType, 'date') => 'date',
                    str_contains($dbType, 'time') => 'datetime',
                    str_contains($dbType, 'bool') => 'boolean',
                    default => 'string',
                };

                $fields[] = [
                    'name' => $column->name,
                    'type' => $fieldType,
                    'nullable' => $column->notnull === 0,
                ];
            }

            // If register_ids provided, add custom register fields
            if (! empty($registerIds)) {
                \Log::info('Adding custom register fields for', ['register_ids' => $registerIds]);
                try {
                    // Get custom fields from register_fields table
                    $customFields = \DB::table('register_fields')
                        ->whereIn('register_id', $registerIds)
                        ->get(['name', 'name_ar', 'field_type', 'is_required']);

                    \Log::info('Custom register fields', ['count' => count($customFields)]);

                    foreach ($customFields as $field) {
                        $fields[] = [
                            'name' => $field->name,
                            'label' => $field->name_ar ?? $field->name,
                            'type' => $field->field_type ?? 'string',
                            'nullable' => ! $field->is_required,
                            'table' => 'register_fields',
                            'register_id' => $field->register_id,
                        ];
                    }
                    \Log::info('Added custom register fields', ['total_fields' => count($fields)]);
                } catch (\Exception $e) {
                    \Log::error('Failed to get custom register fields', ['error' => $e->getMessage()]);
                }
            }

            \Log::info('Returning fields', ['count' => count($fields)]);

            return $this->success(['fields' => $fields]);
        } catch (\Exception $e) {
            \Log::error('Available fields error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_source' => $request->input('data_source'),
            ]);

            // Return empty fields on error instead of 500
            return $this->success(['fields' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get business registers as report data sources.
     */
    public function businessRegisters(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');
        $registers = $this->businessFieldService->getBusinessRegisters($includeInactive);

        return $this->success(['registers' => $registers]);
    }

    /**
     * Get business fields for selected registers.
     */
    public function businessFields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'register_ids' => 'required|array',
            'register_ids.*' => 'uuid|exists:registers,id',
        ]);

        $fields = $this->businessFieldService->getBusinessFields($validated['register_ids']);

        return $this->success(['fields' => $fields]);
    }

    /**
     * Analyze automatic relationships between selected registers.
     */
    public function businessRelationships(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'register_ids' => 'required|array|min:2',
            'register_ids.*' => 'uuid|exists:registers,id',
        ]);

        $relationships = $this->businessFieldService->analyzeRelationships($validated['register_ids']);

        return $this->success(['relationships' => $relationships]);
    }

    /**
     * Preview business data for selected registers and fields.
     */
    public function businessPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'register_ids' => 'required|array',
            'register_ids.*' => 'uuid|exists:registers,id',
            'field_ids' => 'required|array',
            'field_ids.*' => 'uuid|exists:register_fields,id',
            'filters' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->businessFieldService->previewBusinessData(
            $validated['register_ids'],
            $validated['field_ids'],
            $validated['filters'] ?? [],
            $validated['limit'] ?? 50
        );

        return $this->success($result);
    }

    /**
     * Save report design
     */
    public function saveDesign(Request $request, string $id): JsonResponse
    {
        try {
            $report = Report::findOrFail($id);
            $this->authorize('update', $report);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'name_ar' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'data_source' => 'sometimes|string',
                'configuration' => 'nullable|array',
                'visibility' => 'sometimes|in:private,shared,public,role,department',
            ]);

            $report->update($validated);

            // Save sections
            if ($request->has('sections')) {
                $report->fields()->delete();
                foreach ($request->input('sections', []) as $section) {
                    foreach ($section['objects'] ?? [] as $object) {
                        if ($object['type'] === 'field' && $object['field']) {
                            $report->fields()->create([
                                'field_name' => $object['field']['name'],
                                'field_label' => $object['field']['label'] ?? $object['field']['name'],
                                'field_label_ar' => $object['field']['label_ar'] ?? $object['field']['label'] ?? $object['field']['name'],
                                'field_type' => $object['field']['type'] ?? 'string',
                                'sort_order' => $object['x'] ?? 0,
                            ]);
                        }
                    }
                }
            }

            // Save filters
            if ($request->has('filters')) {
                $report->filters()->delete();
                foreach ($request->input('filters', []) as $filter) {
                    $report->filters()->create($filter);
                }
            }

            // Save aggregations
            if ($request->has('calculatedFields')) {
                $report->aggregations()->delete();
                foreach ($request->input('calculatedFields', []) as $calc) {
                    $report->aggregations()->create([
                        'field_name' => $calc['name'],
                        'aggregation_type' => 'CUSTOM',
                        'alias' => $calc['label'],
                        'alias_ar' => $calc['label'],
                        'expression' => $calc['formula'],
                    ]);
                }
            }

            // Save charts
            if ($request->has('charts')) {
                $report->charts()->delete();
                foreach ($request->input('charts', []) as $chart) {
                    $report->charts()->create([
                        'chart_name' => $chart['title'],
                        'chart_type' => $chart['type'],
                        'configuration' => $chart,
                        'x_axis_field' => $chart['xAxis'] ?? null,
                        'y_axis_field' => $chart['yAxis'] ?? null,
                    ]);
                }
            }

            return $this->success(['report' => $report->load(['fields', 'filters', 'aggregations', 'charts'])], 'Report design saved successfully');
        } catch (\Exception $e) {
            \Log::error('Save design error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to save design: '.$e->getMessage(), 500);
        }
    }

    /**
     * Load report design
     */
    public function loadDesign(string $id): JsonResponse
    {
        try {
            $report = Report::with(['fields', 'filters', 'aggregations', 'charts', 'groupings'])->findOrFail($id);
            $this->authorize('view', $report);

            $design = [
                'id' => $report->id,
                'name' => $report->name,
                'name_ar' => $report->name_ar,
                'description' => $report->description,
                'data_source' => $report->data_source,
                'configuration' => $report->configuration,
                'visibility' => $report->visibility,
                'sections' => $this->buildSectionsFromDesign($report),
                'filters' => $report->filters,
                'calculatedFields' => $report->aggregations->map(function ($agg) {
                    return [
                        'id' => $agg->id,
                        'name' => $agg->field_name,
                        'label' => $agg->alias,
                        'formula' => $agg->expression ? json_encode($agg->expression) : '',
                        'type' => 'number',
                    ];
                }),
                'charts' => $report->charts->map(function ($chart) {
                    return array_merge([
                        'id' => $chart->id,
                        'title' => $chart->chart_name,
                        'type' => $chart->chart_type,
                        'xAxis' => $chart->x_axis_field,
                        'yAxis' => $chart->y_axis_field,
                    ], $chart->configuration ?? []);
                }),
                'theme' => $report->configuration['theme'] ?? 'modern',
                'version' => $report->version,
                'status' => $report->published_at ? 'published' : 'draft',
            ];

            return $this->success(['design' => $design]);
        } catch (\Exception $e) {
            \Log::error('Load design error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to load design: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get report templates
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = Report::where('is_system', true)
                ->orWhere('visibility', 'public')
                ->with(['fields', 'filters', 'aggregations', 'charts'])
                ->get();

            return $this->success(['templates' => $templates]);
        } catch (\Exception $e) {
            \Log::error('Get templates error', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to get templates: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get version history
     */
    public function getVersionHistory(string $id): JsonResponse
    {
        try {
            $report = Report::findOrFail($id);
            $this->authorize('view', $report);

            $versions = Report::where('id', $report->id)
                ->orWhere('parent_report_id', $report->id)
                ->orderBy('version', 'desc')
                ->get(['id', 'version', 'status', 'created_at', 'published_by', 'change_summary']);

            return $this->success(['versions' => $versions]);
        } catch (\Exception $e) {
            \Log::error('Get version history error', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to get version history: '.$e->getMessage(), 500);
        }
    }

    /**
     * Restore previous version
     */
    public function restoreVersion(Request $request, string $id, string $versionId): JsonResponse
    {
        try {
            $report = Report::findOrFail($id);
            $this->authorize('update', $report);

            $version = Report::where('parent_report_id', $report->id)
                ->where('id', $versionId)
                ->firstOrFail();

            // Create new version from restored version
            $newVersion = $report->replicate();
            $newVersion->id = (string) Str::uuid();
            $newVersion->version = $report->version + 1;
            $newVersion->published_at = null;
            $newVersion->change_summary = 'Restored from version '.$version->version;
            $newVersion->save();

            // Clone related data
            $this->cloneRelatedData($version, $newVersion);

            return $this->success(['report' => $newVersion], 'Version restored successfully');
        } catch (\Exception $e) {
            \Log::error('Restore version error', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to restore version: '.$e->getMessage(), 500);
        }
    }

    /**
     * Validate formula
     */
    public function validateFormula(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'formula' => 'required|string',
                'data_source' => 'required|string',
            ]);

            // Basic validation - check for balanced parentheses
            $formula = $validated['formula'];
            $openCount = substr_count($formula, '(');
            $closeCount = substr_count($formula, ')');

            if ($openCount !== $closeCount) {
                return $this->success([
                    'valid' => false,
                    'error' => 'Unbalanced parentheses',
                ]);
            }

            // Check for valid functions
            $validFunctions = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX', 'IF', 'CASE', 'ROUND', 'CONCAT', 'DATE'];
            preg_match_all('/([A-Z]+)\(/', $formula, $matches);

            foreach ($matches[1] as $func) {
                if (! in_array($func, $validFunctions)) {
                    return $this->success([
                        'valid' => false,
                        'error' => "Unknown function: {$func}",
                    ]);
                }
            }

            return $this->success([
                'valid' => true,
                'result_type' => 'number',
            ]);
        } catch (\Exception $e) {
            return $this->error('Validation failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Test filter
     */
    public function testFilter(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'data_source' => 'required|string',
                'filters' => 'nullable|array',
            ]);

            $table = $validated['data_source'];
            $query = \DB::table($table);

            // Apply filters
            foreach ($validated['filters'] ?? [] as $filter) {
                $field = $filter['field'];
                $operator = $filter['operator'];
                $value = $filter['value'];

                if ($operator === 'LIKE') {
                    $query->where($field, 'LIKE', "%{$value}%");
                } elseif ($operator === 'IN') {
                    $query->whereIn($field, explode(',', $value));
                } elseif ($operator === 'BETWEEN') {
                    $parts = explode(',', $value);
                    $query->whereBetween($field, $parts);
                } else {
                    $query->where($field, $operator, $value);
                }
            }

            $count = $query->count();
            $sample = $query->limit(5)->get();

            return $this->success([
                'count' => $count,
                'sample' => $sample,
            ]);
        } catch (\Exception $e) {
            return $this->error('Filter test failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Helper: Build sections from design
     */
    protected function buildSectionsFromDesign(Report $report): array
    {
        return [
            [
                'id' => 'header',
                'type' => 'report_header',
                'name' => 'Report Header',
                'height' => 80,
                'objects' => [],
            ],
            [
                'id' => 'page_header',
                'type' => 'page_header',
                'name' => 'Page Header',
                'height' => 60,
                'objects' => [],
            ],
            [
                'id' => 'details',
                'type' => 'details',
                'name' => 'Details',
                'height' => 40,
                'objects' => $report->fields->map(function ($field, $index) {
                    return [
                        'id' => "obj_{$field->id}",
                        'type' => 'field',
                        'field' => [
                            'name' => $field->field_name,
                            'label' => $field->field_label,
                            'type' => $field->field_type,
                        ],
                        'x' => $field->sort_order ?? 0,
                        'y' => 10,
                        'width' => 150,
                        'height' => 30,
                    ];
                })->toArray(),
            ],
            [
                'id' => 'page_footer',
                'type' => 'page_footer',
                'name' => 'Page Footer',
                'height' => 60,
                'objects' => [],
            ],
            [
                'id' => 'footer',
                'type' => 'report_footer',
                'name' => 'Report Footer',
                'height' => 80,
                'objects' => [],
            ],
        ];
    }

    /**
     * Helper: Clone related data
     */
    protected function cloneRelatedData(Report $source, Report $target): void
    {
        // Clone fields
        foreach ($source->fields as $field) {
            $newField = $field->replicate();
            $newField->report_id = $target->id;
            $newField->save();
        }

        // Clone filters
        foreach ($source->filters as $filter) {
            $newFilter = $filter->replicate();
            $newFilter->report_id = $target->id;
            $newFilter->save();
        }

        // Clone aggregations
        foreach ($source->aggregations as $agg) {
            $newAgg = $agg->replicate();
            $newAgg->report_id = $target->id;
            $newAgg->save();
        }

        // Clone charts
        foreach ($source->charts as $chart) {
            $newChart = $chart->replicate();
            $newChart->report_id = $target->id;
            $newChart->save();
        }
    }

    /**
     * Get report execution history
     */
    public function executions(string $id, Request $request): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('view', $report);

        $executions = ReportExecution::where('report_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 25);

        return $this->success($executions);
    }

    /**
     * Publish a report
     */
    public function publish(string $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('update', $report);

        $report->publish();

        return $this->success(['report' => $report], 'Report published successfully');
    }

    /**
     * Clone a report
     */
    public function clone(string $id, Request $request): JsonResponse
    {
        $source = Report::findOrFail($id);
        $this->authorize('view', $source);

        $newReport = DB::transaction(function () use ($source) {
            $newReport = $source->replicate();
            $newReport->id = (string) Str::uuid();
            $newReport->code = 'RPT_'.strtoupper(Str::random(8));
            $newReport->name = $source->name.' (Copy)';
            $newReport->name_ar = $source->name_ar ? $source->name_ar.' (نسخة)' : null;
            $newReport->published_at = null;
            $newReport->version = 1;
            $newReport->parent_report_id = $source->id;
            $newReport->created_by = Auth::id();
            $newReport->save();

            // Clone fields
            foreach ($source->fields as $field) {
                $newField = $field->replicate();
                $newField->id = (string) Str::uuid();
                $newField->report_id = $newReport->id;
                $newField->save();
            }

            // Clone filters
            foreach ($source->filters as $filter) {
                $newFilter = $filter->replicate();
                $newFilter->id = (string) Str::uuid();
                $newFilter->report_id = $newReport->id;
                $newFilter->save();
            }

            // Clone aggregations
            foreach ($source->aggregations as $agg) {
                $newAgg = $agg->replicate();
                $newAgg->id = (string) Str::uuid();
                $newAgg->report_id = $newReport->id;
                $newAgg->save();
            }

            // Clone groupings
            foreach ($source->groupings as $grouping) {
                $newGrouping = $grouping->replicate();
                $newGrouping->id = (string) Str::uuid();
                $newGrouping->report_id = $newReport->id;
                $newGrouping->save();
            }

            // Clone charts
            foreach ($source->charts as $chart) {
                $newChart = $chart->replicate();
                $newChart->id = (string) Str::uuid();
                $newChart->report_id = $newReport->id;
                $newChart->save();
            }

            return $newReport->load(['fields', 'filters', 'aggregations', 'groupings', 'charts']);
        });

        return $this->success(['report' => $newReport], 'Report cloned successfully', [], 201);
    }

    // ============================================
    // Legacy Methods (for backward compatibility)
    // ============================================

    /**
     * Legacy daily report (placeholder)
     */
    public function daily(Request $request): JsonResponse
    {
        // Return sample data for backward compatibility
        $date = $request->input('date', now()->toDateString());

        return $this->success([
            'report' => [
                'name' => 'Daily Report',
                'name_ar' => 'التقرير اليومي',
                'date' => $date,
            ],
            'data' => [
                [
                    'transaction_number' => 'TXN001',
                    'amount' => 150.000,
                    'status' => 'completed',
                    'created_at' => now()->toDateTimeString(),
                ],
                [
                    'transaction_number' => 'TXN002',
                    'amount' => 250.000,
                    'status' => 'completed',
                    'created_at' => now()->toDateTimeString(),
                ],
            ],
            'aggregations' => [
                [
                    'field' => 'amount',
                    'type' => 'SUM',
                    'alias' => 'Total Revenue',
                    'alias_ar' => 'إجمالي الإيرادات',
                    'value' => 400.000,
                    'format' => 'currency',
                ],
                [
                    'field' => '*',
                    'type' => 'COUNT',
                    'alias' => 'Total Transactions',
                    'alias_ar' => 'إجمالي المعاملات',
                    'value' => 2,
                    'format' => 'number',
                ],
            ],
            'message' => 'Legacy endpoint - migrate to new dynamic report engine',
        ], 'Daily report data (legacy endpoint)');
    }

    /**
     * Legacy monthly report (placeholder)
     */
    public function monthly(Request $request): JsonResponse
    {
        return $this->success([
            'message' => 'Please use the new dynamic report engine',
            'endpoint' => '/api/v1/reports/{id}/execute',
        ], 'Legacy endpoint - migrate to new report system');
    }

    /**
     * Legacy user activity report (placeholder)
     */
    public function userActivity(Request $request): JsonResponse
    {
        return $this->success([
            'message' => 'Please use the new dynamic report engine',
            'endpoint' => '/api/v1/reports/{id}/execute',
        ], 'Legacy endpoint - migrate to new report system');
    }

    /**
     * Legacy register summary report (placeholder)
     */
    public function registerSummary(Request $request): JsonResponse
    {
        return $this->success([
            'message' => 'Please use the new dynamic report engine',
            'endpoint' => '/api/v1/reports/{id}/execute',
        ], 'Legacy endpoint - migrate to new report system');
    }

    /**
     * Legacy custom report (placeholder)
     */
    public function custom(Request $request): JsonResponse
    {
        return $this->success([
            'message' => 'Please use the new dynamic report engine',
            'endpoint' => '/api/v1/reports/{id}/execute',
        ], 'Legacy endpoint - migrate to new report system');
    }

    /**
     * Legacy CSV export (placeholder)
     */
    public function exportCsv(Request $request): JsonResponse
    {
        return $this->success([
            'message' => 'Please use the new export endpoint',
            'endpoint' => '/api/v1/reports/{id}/export',
        ], 'Legacy endpoint - migrate to new report system');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Report;
use App\Models\ReportField;
use App\Models\ReportFilter;
use App\Models\ReportAggregation;
use App\Models\ReportGrouping;
use App\Models\ReportChart;
use App\Services\Reports\ReportEngine;
use App\Services\Reports\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportController extends ApiController
{
    public function __construct(
        protected ReportEngine $reportEngine,
        protected ReportExporter $exporter
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

        $report = DB::transaction(function () use ($validated, $request) {
            $report = Report::create([
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'code' => $validated['code'] ?? strtoupper('RPT_' . Str::random(8)),
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
            return $this->error('Report execution failed: ' . $e->getMessage(), 500);
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
            return $this->error('Chart generation failed: ' . $e->getMessage(), 500);
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
            \App\Models\ReportExecution::create([
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
            return $this->error('Export failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download exported file
     */
    public function download(string $filename): \Illuminate\Http\Response
    {
        $path = storage_path('app/temp/' . $filename);

        if (!file_exists($path)) {
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
        
        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . '/*');
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
        $dataSource = $request->input('data_source');

        if (!$dataSource) {
            return $this->error('data_source parameter is required', 422);
        }

        $fields = $this->reportEngine->getAvailableFields($dataSource);

        return $this->success(['fields' => $fields]);
    }

    /**
     * Get report execution history
     */
    public function executions(string $id, Request $request): JsonResponse
    {
        $report = Report::findOrFail($id);
        $this->authorize('view', $report);

        $executions = \App\Models\ReportExecution::where('report_id', $id)
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

        $newReport = DB::transaction(function () use ($source, $request) {
            $newReport = $source->replicate();
            $newReport->id = (string) Str::uuid();
            $newReport->code = 'RPT_' . strtoupper(Str::random(8));
            $newReport->name = $source->name . ' (Copy)';
            $newReport->name_ar = $source->name_ar ? $source->name_ar . ' (نسخة)' : null;
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
        return $this->success([
            'message' => 'Please use the new dynamic report engine',
            'endpoint' => '/api/v1/reports/{id}/execute',
        ], 'Legacy endpoint - migrate to new report system');
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

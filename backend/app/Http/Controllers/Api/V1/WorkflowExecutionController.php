<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\WorkflowExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowBranchController;
use App\Services\WorkflowExecutionService;
use App\Services\WorkflowFieldSchemaBuilder;
use App\Services\ValidationEngine;
use App\Services\EnterpriseRuleEngine;
use App\Services\RealTimeRuleEngine;
use App\Services\DependencyResolver;
use App\Services\ExecutionStateManager;
use App\Services\FinancialRecalculator;
use App\Services\FeeEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowExecutionController extends ApiController
{
    protected WorkflowExecutionService $executionService;
    protected WorkflowFieldSchemaBuilder $schemaBuilder;
    protected ValidationEngine $validationEngine;
    protected EnterpriseRuleEngine $enterpriseEngine;
    protected WorkflowBranchController $branchController;
    protected RealTimeRuleEngine $realTimeEngine;
    
    public function __construct(
        WorkflowExecutionService $executionService,
        WorkflowFieldSchemaBuilder $schemaBuilder,
        ValidationEngine $validationEngine,
        EnterpriseRuleEngine $enterpriseEngine,
        WorkflowBranchController $branchController,
        RealTimeRuleEngine $realTimeEngine
    ) {
        $this->executionService = $executionService;
        $this->schemaBuilder = $schemaBuilder;
        $this->validationEngine = $validationEngine;
        $this->enterpriseEngine = $enterpriseEngine;
        $this->branchController = $branchController;
        $this->realTimeEngine = $realTimeEngine;
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkflowExecution::class);

        $data = $request->validate([
            'workflow_version_id' => 'required|string|exists:workflow_versions,id',
        ]);

        $version = WorkflowVersion::with(['workflow', 'steps', 'fields.registerField', 'rules'])
            ->where('id', $data['workflow_version_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $execution = $this->executionService->start(
            $version,
            $request->user()->id,
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return $this->success([
            'execution' => $execution,
            'version' => $version,
            'current_step' => $version->steps->sortBy('sort_order')->values()->first(),
            'total_steps' => $version->steps->count(),
        ], '', [], 201);
    }

    public function show(string $id): JsonResponse
    {
        $execution = WorkflowExecution::with(['version.workflow', 'receipt', 'starter'])
            ->findOrFail($id);

        $this->authorize('view', $execution);

        return $this->success($execution);
    }

    public function submitStep(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::with('version.steps', 'version.fields.registerField')
            ->findOrFail($id);

        $this->authorize('update', $execution);

        if (!$execution->isInProgress() && !$execution->isPaused()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'step_index' => 'required|integer|min:0',
            'values' => 'required|array',
        ]);

        $result = $this->executionService->submitStep(
            $execution,
            $data['step_index'],
            $data['values']
        );

        $execution = $result['execution'];
        $version = $execution->version;
        $steps = $version->steps->sortBy('sort_order')->values();
        $isLastStep = $execution->current_step_index >= $steps->count();

        return $this->success([
            'execution' => $execution,
            'current_step_index' => $execution->current_step_index,
            'current_step' => $isLastStep ? null : ($steps[$execution->current_step_index] ?? null),
            'is_review' => $isLastStep,
            'calculated_items' => $result['calculated_items'],
            'total_amount' => $result['total_amount'],
            'grand_total' => $result['total_amount'],
            'financial_trace' => $result['financial_trace'] ?? [],
            'discount_applied' => $result['discount_applied'] ?? '0.000',
            'snapshot_hash' => $result['snapshot_hash'] ?? null,
            'modified_values' => $result['modified_values'],
            'field_states' => $result['field_states'],
            'insurance_snapshots' => $result['insurance_snapshots'] ?? [],
            'computed_values' => $result['computed_values'] ?? [],
            'audit_summary' => $result['audit_summary'] ?? [],
            'validation_warnings' => $result['legacy_warnings'] ?? [],
            'routing_decisions' => !empty($result['enterprise_routing'] ?? []) ? $result['enterprise_routing'] : (!empty($result['legacy_routing'] ?? []) ? array_values($result['legacy_routing']) : []),
            'enterprise_stats' => $result['enterprise_stats'] ?? null,
            'enterprise_results' => $result['enterprise_results'] ?? [],
            'version_info' => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status,
                'validation_rules_count' => $version->validationRules()->count(),
                'enterprise_rules_count' => $version->validationRules()->whereNotNull('rule_config')->where('is_active', true)->count(),
            ],
        ]);
    }

    public function getFieldSchema(Request $request, string $versionId): JsonResponse
    {
        $version = WorkflowVersion::with(['fields.registerField', 'rules'])
            ->where('id', $versionId)
            ->firstOrFail();

        $values = $request->input('values', []);
        $stepId = $request->input('step_id');

        $schema = $this->schemaBuilder->buildForVersion($version->fields, $values);

        if ($stepId) {
            $schema = $this->schemaBuilder->filterByStep($schema, $stepId);
        }

        $visibleOnly = $request->boolean('visible_only', false);
        if ($visibleOnly) {
            $schema = $this->schemaBuilder->filterVisible($schema);
        }

        return $this->success([
            'version_id' => $versionId,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'total_fields' => count($schema),
            'valid_field_types' => $this->schemaBuilder->getValidTypes(),
            'fields' => $schema,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('preview', WorkflowExecution::class);

        $data = $request->validate([
            'workflow_version_id' => 'required|string|exists:workflow_versions,id',
            'values' => 'required|array',
        ]);

        $version = WorkflowVersion::with(['workflow', 'steps', 'fields.registerField', 'rules'])
            ->where('id', $data['workflow_version_id'])
            ->firstOrFail();

        $preview = $this->executionService->preview($version, $data['values']);

        return $this->success($preview);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $execution = WorkflowExecution::lockForUpdate()->findOrFail($id);
            
            $this->authorize('complete', $execution);

            if (!$execution->isInProgress()) {
                return $this->error('هذا التنفيذ ليس نشطاً', 422);
            }

            $data = $request->validate([
                'notes' => 'nullable|string',
            ]);

            $receipt = $this->executionService->complete($execution, $data['notes'] ?? null);

            return $this->success([
                'execution' => $execution->fresh(),
                'receipt' => $receipt,
            ], 'تم إنشاء الوصل بنجاح');
        });
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $execution = WorkflowExecution::lockForUpdate()->findOrFail($id);
            
            $this->authorize('cancel', $execution);

            if (!$execution->isInProgress()) {
                return $this->error('هذا التنفيذ ليس نشطاً', 422);
            }

            $data = $request->validate([
                'reason' => 'required|string',
            ]);

            $execution = $this->executionService->cancel($execution, $data['reason']);

            return $this->success($execution->fresh(), 'تم إلغاء التنفيذ');
        });
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkflowExecution::class);

        $query = WorkflowExecution::with(['version.workflow', 'register', 'starter', 'receipt'])
            ->when($request->workflow_id, fn($q, $id) => $q->whereHas('version', fn($vq) => $vq->where('workflow_id', $id)))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->started_by, fn($q, $id) => $q->where('started_by', $id))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('started_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('started_at', '<=', $d))
            ->orderByDesc('started_at');

        if ($request->boolean('paginate')) {
            $paginated = $query->paginate($request->per_page ?? 25);
            return $this->success($paginated->items(), '', $this->paginationMeta($paginated));
        }

        return $this->success($query->get());
    }

    // --- Branch Control ---

    public function getBranchState(string $id): JsonResponse
    {
        $execution = WorkflowExecution::with(['version.workflow'])->findOrFail($id);

        $this->authorize('branch', $execution);

        return $this->success([
            'execution_id' => $execution->id,
            'workflow_id' => $execution->version->workflow_id,
            'workflow_name' => $execution->version->workflow->name_ar,
            'mode' => $execution->getMode(),
            'branch_state' => $execution->getBranchState(),
            'routing_history' => $execution->getRoutingHistory(),
            'preserved_values' => $execution->getPreservedValues(),
            'state_mapping' => $execution->getStateMapping(),
            'has_redirect' => $execution->hasRedirect(),
            'redirect_target' => $execution->getRedirectTarget(),
            'is_paused' => $execution->isPaused(),
        ]);
    }

    public function switchMode(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('branch', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'mode' => 'required|in:create,update,renewal,review',
            'reason' => 'nullable|string',
        ]);

        $execution->switchMode($data['mode'], $data['reason'] ?? 'manual_switch');

        return $this->success([
            'execution_id' => $execution->id,
            'mode' => $execution->getMode(),
            'routing_history' => $execution->getRoutingHistory(),
        ]);
    }

    public function pauseExecution(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('branch', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $execution->pauseExecution($data['reason'] ?? 'manual_pause');

        return $this->success([
            'execution_id' => $execution->id,
            'paused' => true,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function resumeExecution(string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('branch', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $execution->resumeExecution();

        return $this->success([
            'execution_id' => $execution->id,
            'paused' => false,
        ]);
    }

    public function redirectExecution(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::with('version.workflow')->findOrFail($id);

        $this->authorize('branch', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'target_workflow_id' => 'required|string|exists:workflows,id',
            'target_step_id' => 'nullable|string',
            'state_mapping' => 'nullable|array',
            'reason' => 'nullable|string',
        ]);

        $targetWorkflow = \App\Models\Workflow::findOrFail($data['target_workflow_id']);
        $targetVersion = $targetWorkflow->versions()->where('status', 'active')->first();

        if (!$targetVersion) {
            return $this->error('لا توجد نسخة منشورة من سير العمل الهدف', 422);
        }

        // Set redirect
        $execution->setRedirect($targetVersion->id, $data['target_step_id'] ?? null);

        if (!empty($data['state_mapping'])) {
            $execution->setStateMapping($data['state_mapping']);
            $mappedValues = $this->branchController->mapValues($execution->values_snapshot ?? [], $data['state_mapping']);
            $execution->preserveValues($mappedValues);
        }

        // Log event
        $execution->addRoutingEvent([
            'event' => 'manual_redirect',
            'from_workflow_id' => $execution->version->workflow_id,
            'to_workflow_id' => $data['target_workflow_id'],
            'reason' => $data['reason'] ?? 'manual_redirect',
        ]);

        return $this->success([
            'execution_id' => $execution->id,
            'redirect_target' => $execution->getRedirectTarget(),
            'target_workflow_name' => $targetWorkflow->name_ar,
            'target_version_id' => $targetVersion->id,
        ]);
    }

    public function saveDraft(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('update', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'values' => 'required|array',
        ]);

        $currentValues = $execution->values_snapshot ?? [];
        $execution->values_snapshot = array_merge($currentValues, $data['values']);
        $execution->save();

        return $this->success([
            'execution_id' => $execution->id,
            'saved_values' => $execution->values_snapshot,
        ]);
    }

    /**
     * Real-time rule execution endpoint
     * Executes rules immediately when a field changes
     */
    public function executeRealTime(Request $request, string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('update', $execution);

        if (!$execution->isInProgress()) {
            return $this->error('هذا التنفيذ ليس نشطاً', 422);
        }

        $data = $request->validate([
            'field_id' => 'required|string',
            'value' => 'nullable',
            'values' => 'required|array',
        ]);

        $changedFieldId = $data['field_id'];
        $values = $data['values'];

        // Execute real-time rule evaluation
        $result = $this->realTimeEngine->execute(
            $execution->workflow_version_id,
            $changedFieldId,
            $values,
            $execution->id
        );

        // Log the result for debugging
        \Log::info('[executeRealTime] Result:', $result);

        // Persist execution status
        $execution->setExecutionStatus($result['success'] ? 'READY' : 'ERROR');
        if (!$result['success']) {
            $execution->setExecutionError($result['error']);
        }

        return $this->success($result);
    }

    /**
     * Get execution status
     */
    public function getExecutionStatus(string $id): JsonResponse
    {
        $execution = WorkflowExecution::findOrFail($id);

        $this->authorize('view', $execution);

        return $this->success([
            'execution_id' => $execution->id,
            'status' => $execution->getExecutionStatus(),
            'error' => $execution->getExecutionError(),
            'is_ready' => $execution->isExecutionReady(),
            'is_executing' => $execution->isExecutionInProgress(),
        ]);
    }
}

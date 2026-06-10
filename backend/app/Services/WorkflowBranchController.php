<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;

class WorkflowBranchController
{
    protected ValidationEngine $validationEngine;

    public function __construct(ValidationEngine $validationEngine)
    {
        $this->validationEngine = $validationEngine;
    }

    /**
     * Handle a validation decision and determine execution effect.
     *
     * @param WorkflowExecution $execution Current execution
     * @param array $decision Decision from ValidationEngine
     * @param array $values Current field values
     * @return array ['effect' => string, 'data' => array]
     *
     * Effects: continue, block, warn, redirect, mode_switch, pause
     */
    public function handleDecision(WorkflowExecution $execution, array $decision, array $values): array
    {
        $status = $decision['status'] ?? 'passed';
        $decisionType = $decision['decision'] ?? null;

        // Passed or not_found → continue
        if ($status === 'passed' || $status === 'not_found' || $status === 'skipped') {
            return $this->effectContinue($decision);
        }

        // Error → block
        if ($status === 'error') {
            return $this->effectBlock($decision);
        }

        // Found → evaluate routing
        if ($status === 'found') {
            return $this->handleFoundDecision($execution, $decision, $values);
        }

        // Failed with response type
        if ($status === 'failed') {
            return $this->handleFailedDecision($execution, $decision);
        }

        return $this->effectContinue($decision);
    }

    /**
     * Handle a "found" decision (record exists).
     */
    protected function handleFoundDecision(WorkflowExecution $execution, array $decision, array $values): array
    {
        $routeConfig = $decision['route_config'] ?? [];
        $onMatch = $routeConfig['on_match'] ?? [];
        $action = $onMatch['action'] ?? 'warn';

        return match ($action) {
            'block' => $this->effectBlock($decision),
            'warn' => $this->effectWarn($decision),
            'route_workflow' => $this->effectRedirect($execution, $decision, $values),
            'switch_mode' => $this->effectModeSwitch($execution, $decision, $values),
            default => $this->effectWarn($decision),
        };
    }

    /**
     * Handle a "failed" decision (validation failed).
     */
    protected function handleFailedDecision(WorkflowExecution $execution, array $decision): array
    {
        $responseType = $decision['response_type'] ?? 'error';

        return match ($responseType) {
            'error' => $this->effectBlock($decision),
            'warning' => $this->effectWarn($decision),
            'confirm' => $this->effectConfirm($decision),
            default => $this->effectBlock($decision),
        };
    }

    /**
     * Continue effect — normal execution.
     */
    protected function effectContinue(array $decision): array
    {
        return [
            'effect' => 'continue',
            'data' => [
                'message' => $decision['message'] ?? null,
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
            ],
        ];
    }

    /**
     * Block effect — stop execution.
     */
    protected function effectBlock(array $decision): array
    {
        return [
            'effect' => 'block',
            'data' => [
                'message' => $decision['message'] ?? 'تم منع العملية',
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
                'validation_type' => $decision['validation_type'] ?? null,
            ],
        ];
    }

    /**
     * Warn effect — show warning but allow continuation.
     */
    protected function effectWarn(array $decision): array
    {
        return [
            'effect' => 'warn',
            'data' => [
                'message' => $decision['message'] ?? 'تحذير',
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
                'actions' => $decision['actions'] ?? [],
                'existing_record' => $decision['existing_record'] ?? null,
            ],
        ];
    }

    /**
     * Confirm effect — ask user before continuing.
     */
    protected function effectConfirm(array $decision): array
    {
        return [
            'effect' => 'confirm',
            'data' => [
                'message' => $decision['confirm_message'] ?? $decision['message'] ?? 'هل تريد المتابعة؟',
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
            ],
        ];
    }

    /**
     * Redirect effect — route to another workflow.
     */
    protected function effectRedirect(WorkflowExecution $execution, array $decision, array $values): array
    {
        $onMatch = $decision['route_config']['on_match'] ?? [];
        $targetWorkflowId = $onMatch['target_workflow_id'] ?? null;
        $targetStepId = $onMatch['target_step_id'] ?? null;

        if (!$targetWorkflowId) {
            // No target → just warn
            return $this->effectWarn($decision);
        }

        // Find target workflow
        $targetWorkflow = Workflow::find($targetWorkflowId);
        if (!$targetWorkflow) {
            return $this->effectBlock([
                'message' => 'سير العمل الهدف غير موجود',
                'rule_id' => $decision['rule_id'] ?? null,
            ]);
        }

        // Get active version of target workflow
        $targetVersion = $targetWorkflow->versions()
            ->where('status', 'active')
            ->first();

        if (!$targetVersion) {
            return $this->effectBlock([
                'message' => 'لا توجد نسخة منشورة من سير العمل الهدف',
                'rule_id' => $decision['rule_id'] ?? null,
            ]);
        }

        // Set redirect on execution
        $execution->setRedirect($targetVersion->id, $targetStepId);

        // Preserve current values for transfer
        $stateMapping = $onMatch['state_mapping'] ?? [];
        $preservedValues = $this->mapValues($values, $stateMapping);
        $execution->preserveValues($preservedValues);

        if (!empty($stateMapping)) {
            $execution->setStateMapping($stateMapping);
        }

        // Log routing event
        $execution->addRoutingEvent([
            'event' => 'workflow_redirect',
            'from_workflow_id' => $execution->version->workflow_id,
            'to_workflow_id' => $targetWorkflowId,
            'from_mode' => $execution->getMode(),
            'to_mode' => $onMatch['target_mode'] ?? 'update',
            'trigger_field' => $decision['trigger_field'] ?? null,
            'trigger_value' => $decision['trigger_value'] ?? null,
            'rule_id' => $decision['rule_id'] ?? null,
            'rule_name' => $decision['rule_name'] ?? null,
            'existing_record_id' => $decision['existing_record']['id'] ?? null,
        ]);

        // Switch mode if specified
        if (!empty($onMatch['target_mode'])) {
            $execution->switchMode($onMatch['target_mode'], 'workflow_redirect');
        }

        return [
            'effect' => 'redirect',
            'data' => [
                'message' => $decision['message'] ?? 'تم العثور على سجل سابق، سيتم تحويلك',
                'target_workflow_id' => $targetWorkflowId,
                'target_workflow_name' => $targetWorkflow->name_ar,
                'target_version_id' => $targetVersion->id,
                'target_step_id' => $targetStepId,
                'target_mode' => $onMatch['target_mode'] ?? 'update',
                'preserved_values' => $preservedValues,
                'state_mapping' => $stateMapping,
                'existing_record' => $decision['existing_record'] ?? null,
                'actions' => $onMatch['actions'] ?? [],
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
            ],
        ];
    }

    /**
     * Mode switch effect — change execution mode within same workflow.
     */
    protected function effectModeSwitch(WorkflowExecution $execution, array $decision, array $values): array
    {
        $onMatch = $decision['route_config']['on_match'] ?? [];
        $targetMode = $onMatch['target_mode'] ?? 'update';

        $execution->switchMode($targetMode, 'field_existence_check');

        // Log event
        $execution->addRoutingEvent([
            'event' => 'mode_switch',
            'from_mode' => $execution->getMode(),
            'to_mode' => $targetMode,
            'trigger_field' => $decision['trigger_field'] ?? null,
            'trigger_value' => $decision['trigger_value'] ?? null,
            'rule_id' => $decision['rule_id'] ?? null,
            'rule_name' => $decision['rule_name'] ?? null,
        ]);

        return [
            'effect' => 'mode_switch',
            'data' => [
                'message' => $decision['message'] ?? 'تم تغيير المسار',
                'from_mode' => $execution->getMode(),
                'to_mode' => $targetMode,
                'existing_record' => $decision['existing_record'] ?? null,
                'rule_id' => $decision['rule_id'] ?? null,
                'rule_name' => $decision['rule_name'] ?? null,
            ],
        ];
    }

    /**
     * Map values based on state mapping configuration.
     */
    protected function mapValues(array $values, array $mapping): array
    {
        if (empty($mapping)) {
            return $values;
        }

        $mapped = [];
        foreach ($mapping as $sourceField => $targetField) {
            if (isset($values[$sourceField])) {
                $mapped[$targetField] = $values[$sourceField];
            }
        }

        return $mapped;
    }

    /**
     * Process all validation results and return the highest-priority effect.
     * Priority: block > redirect > mode_switch > confirm > warn > continue
     */
    public function processValidationResults(WorkflowExecution $execution, array $validationResults, array $values): array
    {
        $priorityOrder = ['block', 'redirect', 'mode_switch', 'confirm', 'warn', 'continue'];
        $highestEffect = null;
        $highestPriority = count($priorityOrder);
        $allWarnings = [];
        $allContinues = [];

        foreach ($validationResults as $result) {
            $effectResult = $this->handleDecision($execution, $result, $values);
            $effect = $effectResult['effect'];
            $priority = array_search($effect, $priorityOrder);

            if ($priority < $highestPriority) {
                $highestPriority = $priority;
                $highestEffect = $effectResult;
            }

            if ($effect === 'warn') {
                $allWarnings[] = $effectResult['data'];
            } elseif ($effect === 'continue') {
                $allContinues[] = $effectResult['data'];
            }
        }

        $result = $highestEffect ?? $this->effectContinue([]);

        // Attach warnings even if higher priority effect exists
        if (!empty($allWarnings)) {
            $result['data']['warnings'] = $allWarnings;
        }

        return $result;
    }

    /**
     * Create a new execution from a redirect, preserving state.
     */
    public function createRedirectedExecution(
        WorkflowExecution $sourceExecution,
        WorkflowVersion $targetVersion,
        array $preservedValues,
        string $startedBy,
        array $context = []
    ): WorkflowExecution {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($sourceExecution, $targetVersion, $preservedValues, $startedBy, $context) {
            $sourceExecution->lockForUpdate()->find($sourceExecution->id);

            $execution = new WorkflowExecution();
            $execution->id = (string) Str::uuid();
            $execution->workflow_version_id = $targetVersion->id;
            $execution->register_id = $sourceExecution->register_id;
            $execution->status = 'in_progress';
            $execution->mode = $sourceExecution->getMode();
            $execution->current_step_index = 0;
            $execution->values_snapshot = $preservedValues;
            $execution->started_by = $startedBy;
            $execution->started_at = now();
            $execution->ip_address = $context['ip_address'] ?? null;
            $execution->user_agent = $context['user_agent'] ?? null;

            // Set branch state
            $execution->branch_state = [
                'active_branch' => 'redirected',
                'redirect_to_workflow_id' => null,
                'redirect_to_step_id' => null,
                'paused' => false,
                'pause_reason' => null,
                'original_execution_id' => $sourceExecution->id,
            ];

            // Preserve values
            $execution->preserved_values = $preservedValues;
            $execution->state_mapping = $sourceExecution->getStateMapping();

            // Log the redirect event
            $execution->routing_history = [[
                'event' => 'redirected_from',
                'from_execution_id' => $sourceExecution->id,
                'from_workflow_id' => $sourceExecution->version->workflow_id,
                'to_workflow_id' => $targetVersion->workflow_id,
                'timestamp' => now()->toISOString(),
            ]];

            $execution->save();

            // Log on source execution too
            $sourceExecution->addRoutingEvent([
                'event' => 'redirected_to',
                'to_execution_id' => $execution->id,
                'to_workflow_id' => $targetVersion->workflow_id,
            ]);

            return $execution;
        });
    }
}

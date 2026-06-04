<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptCalculationSnapshot;
use App\Models\ReceiptItem;
use App\Models\Register;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use App\Models\WorkflowField;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

/**
 * WorkflowExecutionService - Event-sourced workflow execution.
 *
 * All state changes are recorded as immutable events.
 * The execution table is a denormalized read cache, not source of truth.
 * Source of truth = workflow_execution_events table.
 */
class WorkflowExecutionService
{
    protected RuleEngineV2 $ruleEngine;
    protected EnterpriseRuleEngine $enterpriseEngine;
    protected FeeEngine $feeEngine;
    protected EventStore $eventStore;
    protected CalculationContext $ctx;
    protected VisibilityResolver $visibilityResolver;
    protected InsuranceEngine $insuranceEngine;
    protected WorkflowFieldSchemaBuilder $schemaBuilder;
    protected ConditionalValidationEngine $validationEngine;
    protected ComputedFieldEngine $computedEngine;
    protected FieldAuditTrail $auditTrail;
    protected CrossFieldValidationEngine $crossFieldValidation;

    public function __construct(
        RuleEngineV2 $ruleEngine,
        EnterpriseRuleEngine $enterpriseEngine,
        FeeEngine $feeEngine,
        EventStore $eventStore,
        VisibilityResolver $visibilityResolver,
        InsuranceEngine $insuranceEngine,
        WorkflowFieldSchemaBuilder $schemaBuilder,
        ConditionalValidationEngine $validationEngine,
        ComputedFieldEngine $computedEngine,
        FieldAuditTrail $auditTrail,
        CrossFieldValidationEngine $crossFieldValidation
    ) {
        $this->ruleEngine = $ruleEngine;
        $this->enterpriseEngine = $enterpriseEngine;
        $this->feeEngine = $feeEngine;
        $this->eventStore = $eventStore;
        $this->ctx = CalculationContext::default();
        $this->visibilityResolver = $visibilityResolver;
        $this->insuranceEngine = $insuranceEngine;
        $this->schemaBuilder = $schemaBuilder;
        $this->validationEngine = $validationEngine;
        $this->computedEngine = $computedEngine;
        $this->auditTrail = $auditTrail;
        $this->crossFieldValidation = $crossFieldValidation;

        $this->feeEngine->setContext($this->ctx);
        $this->ruleEngine->setContext($this->ctx);
    }

    /**
     * Start a new workflow execution.
     * Creates an execution_started event.
     */
    public function start(WorkflowVersion $version, string $startedBy, array $context = []): WorkflowExecution
    {
        return DB::transaction(function () use ($version, $startedBy, $context) {
            $execution = WorkflowExecution::create([
                'workflow_version_id' => $version->id,
                'register_id' => $version->workflow->register_id,
                'status' => 'in_progress',
                'current_step_index' => 0,
                'values_snapshot' => [],
                'calculated_items' => [],
                'total_amount' => '0.000',
                'started_by' => $startedBy,
                'started_at' => now(),
                'ip_address' => $context['ip_address'] ?? request()->ip(),
                'user_agent' => $context['user_agent'] ?? request()->userAgent(),
                'lock_version' => 0,
            ]);

            // Append event (source of truth)
            $this->eventStore->appendExecutionEvent(
                executionId: $execution->id,
                eventType: WorkflowExecutionEvent::EXECUTION_STARTED,
                payload: [
                    'workflow_version_id' => $version->id,
                    'register_id' => $execution->register_id,
                    'started_by' => $startedBy,
                    'step_index' => 0,
                    'values' => [],
                ],
                contextSnapshot: $this->captureContext(),
                idempotencyKey: $context['idempotency_key'] ?? null,
                causedBy: $startedBy,
            );

            return $execution;
        });
    }

    /**
     * Submit data for a step and advance.
     * Creates a step_submitted event.
     * State is derived from the event stream.
     */
    public function submitStep(WorkflowExecution $execution, int $stepIndex, array $values): array
    {
        if (!$execution->isInProgress()) {
            throw new \RuntimeException('هذا التنفيذ ليس نشطاً');
        }

        return DB::transaction(function () use ($execution, $stepIndex, $values) {
            $currentValues = $execution->values_snapshot ?? [];

            $version = $execution->version;
            $steps = $version->steps;
            $fields = $version->fields;
            $rules = $version->rules;

            $stepFields = $fields->where('step_id', $steps[$stepIndex]->id ?? null);

            $sanitizedValues = $this->sanitizeInput($stepFields, $values, $currentValues);
            $mergedValues = array_merge($currentValues, $sanitizedValues);

            $validationErrors = $this->validationEngine->validateAll($stepFields, $mergedValues);
            $crossFieldErrors = $this->crossFieldValidation->validateAll($stepFields, $mergedValues);
            $allErrors = array_merge($validationErrors, $crossFieldErrors);

            if (!empty($allErrors)) {
                throw new \RuntimeException(json_encode([
                    'error' => 'validation_failed',
                    'message' => 'فشل التحقق من صحة الحقول',
                    'errors' => $allErrors,
                ], JSON_UNESCAPED_UNICODE));
            }

            $computedValues = $this->computedEngine->recalculateAll($stepFields, $mergedValues);
            $mergedValues = array_merge($mergedValues, $computedValues);

            // Apply rules (legacy)
            $ruleResult = $this->ruleEngine->evaluate(
                $rules->toArray(),
                $mergedValues,
                ['step_index' => $stepIndex, 'execution_id' => $execution->id]
            );

            // Run enterprise rules and merge actions
            $enterpriseResult = $this->enterpriseEngine->execute(
                $version->id,
                $mergedValues,
                ['step_index' => $stepIndex, 'execution_id' => $execution->id]
            );
            $enterpriseActions = [];
            foreach ($enterpriseResult['results'] ?? [] as $r) {
                if ($r['matched'] && !empty($r['field_effects'])) {
                    // Transform enterprise field_effects to legacy action format
                    foreach ($r['field_effects'] as $effect) {
                        $action = [
                            'target_field_id' => $effect['field_id'],
                            'action' => $effect['action'] ?? 'set_value',
                        ];
                        if ($effect['action'] === 'set_fee') {
                            $action['fee_code'] = $effect['fee_code'] ?? null;
                            $action['resolved_amount'] = $effect['amount'] ?? null;
                        } elseif (isset($effect['value'])) {
                            $action['resolved_value'] = $effect['value'];
                        }
                        $enterpriseActions[] = $action;
                    }
                }
            }

            $allActions = array_merge($ruleResult['actions'] ?? [], $enterpriseActions);

            $modifiedValues = $this->applySetValueActions($mergedValues, $allActions);
            $fieldStates = $this->buildFieldStates($fields, $allActions);
            $fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $allActions);

            $stepId = $steps[$stepIndex]->id ?? null;
            $schema = $this->schemaBuilder->buildForVersion($stepFields, $modifiedValues);
            $visibleSchema = $this->schemaBuilder->filterVisible($schema);

            $visibleFieldIds = array_column($visibleSchema, 'field_id');
            $visibleFields = $stepFields->filter(fn($f) => in_array($f->register_field_id ?? 'custom_'.$f->id, $visibleFieldIds, true));

            $fieldChanges = $this->auditTrail->recordFieldChanges(
                $execution->id,
                $stepFields,
                $currentValues,
                $modifiedValues,
                $execution->started_by,
                "Step {$stepIndex} submission"
            );

            // Calculate fees
            $calculatedItems = $this->calculateItems($visibleFields, $modifiedValues, $allActions);
            $stepTotal = $this->sumItems($calculatedItems);

            $financialTrace = $this->buildFinancialTrace($visibleFields, $modifiedValues, $calculatedItems, $allActions);

            // Insurance snapshots
            $insuranceSnapshots = $this->insuranceEngine->collectInsuranceSnapshots($visibleFields, $modifiedValues);

            // Compute new total from event stream (not from cached value)
            $replayedState = $this->replayExecutionState($execution->id);
            $newTotal = bcadd($replayedState['total_amount'], $stepTotal, $this->ctx->scale());

            $nextStepIndex = $this->findNextVisibleStep($version, $stepIndex + 1, $modifiedValues);

            // Build fee snapshot
            $feeSnapshot = $this->buildFeeSnapshotFromItems($calculatedItems);

            // Append event (source of truth)
            $this->eventStore->appendExecutionEvent(
                executionId: $execution->id,
                eventType: WorkflowExecutionEvent::STEP_SUBMITTED,
                payload: [
                    'step_index' => $stepIndex,
                    'next_step_index' => $nextStepIndex,
                    'values' => $modifiedValues,
                    'step_total' => $stepTotal,
                    'matched_rules' => $ruleResult['matched_rules'],
                    'insurance_snapshots' => $insuranceSnapshots,
                    'field_changes' => $fieldChanges,
                    'computed_values' => $computedValues,
                ],
                calculatedItems: $calculatedItems,
                feeSnapshot: $feeSnapshot,
                contextSnapshot: $this->captureContext(),
                idempotencyKey: $values['idempotency_key'] ?? null,
                causedBy: $execution->started_by,
            );

            // Update denormalized cache (not source of truth)
            $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', 'in_progress')
                ->update([
                    'current_step_index' => $nextStepIndex,
                    'values_snapshot' => $modifiedValues,
                    'calculated_items' => array_merge($execution->calculated_items ?? [], $calculatedItems),
                    'total_amount' => $newTotal,
                    'lock_version' => $execution->lock_version + 1,
                ]);

            $fresh = $execution->fresh();

            return [
                'execution' => $fresh,
                'modified_values' => $modifiedValues,
                'field_states' => $fieldStates,
                'calculated_items' => array_merge($execution->calculated_items ?? [], $calculatedItems),
                'total_amount' => $newTotal,
                'insurance_snapshots' => $insuranceSnapshots,
                'computed_values' => $computedValues,
                'field_changes' => $fieldChanges,
                'audit_summary' => $this->auditTrail->getSummary(),
                'financial_calculation_trace' => $financialTrace,
                'enterprise_routing' => $enterpriseResult['routing_decisions'] ?? [],
                'enterprise_stats' => [
                    'total_rules_evaluated' => $enterpriseResult['total_rules_evaluated'] ?? 0,
                    'matched_rules' => $enterpriseResult['matched_rules'] ?? 0,
                    'failed_rules' => $enterpriseResult['failed_rules'] ?? 0,
                    'execution_time_ms' => $enterpriseResult['execution_time_ms'] ?? 0,
                ],
                'enterprise_results' => array_map(function ($r) {
                    return [
                        'rule_id' => $r['rule_id'],
                        'rule_name' => $r['rule_name'],
                        'rule_type' => $r['rule_type'] ?? 'enterprise',
                        'matched' => $r['matched'],
                        'executed_actions' => $r['executed_actions'] ?? [],
                        'field_effects' => $r['field_effects'] ?? [],
                        'messages' => $r['messages'] ?? [],
                        'routing' => $r['routing'] ?? null,
                        'condition_trace' => $r['condition_trace'] ?? null,
                    ];
                }, $enterpriseResult['results'] ?? []),
            ];
        });
    }

    /**
     * Preview the final calculation without saving.
     * No events created (read-only operation).
     */
    public function preview(WorkflowVersion $version, array $values): array
    {
        $fields = $version->fields;
        $rules = $version->rules;

        $ruleResult = $this->ruleEngine->evaluate(
            $rules->toArray(),
            $values,
            ['preview' => true]
        );

        $fieldStates = $this->buildFieldStates($fields, $ruleResult['actions']);
        $fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $ruleResult['actions']);
        $modifiedValues = $this->applySetValueActions($values, $ruleResult['actions']);

        $schema = $this->schemaBuilder->buildForVersion($fields, $modifiedValues);
        $visibleSchema = $this->schemaBuilder->filterVisible($schema);

        $visibleFieldIds = array_column($visibleSchema, 'field_id');
        $visibleFields = $fields->filter(fn($f) => in_array($f->register_field_id ?? 'custom_'.$f->id, $visibleFieldIds, true));

        $calculatedItems = $this->calculateItems($visibleFields, $modifiedValues, $ruleResult['actions']);
        $totalAmount = $this->sumItems($calculatedItems);

        $insuranceSnapshots = $this->insuranceEngine->collectInsuranceSnapshots($visibleFields, $modifiedValues);
        $financialTrace = $this->buildFinancialTrace($visibleFields, $modifiedValues, $calculatedItems, $ruleResult['actions']);

        return [
            'items' => $calculatedItems,
            'total_amount' => $totalAmount,
            'matched_rules' => $ruleResult['matched_rules'],
            'actions' => $ruleResult['actions'],
            'values' => $values,
            'modified_values' => $modifiedValues,
            'field_states' => $fieldStates,
            'insurance_snapshots' => $insuranceSnapshots,
            'schema' => $visibleSchema,
            'financial_calculation_trace' => $financialTrace,
        ];
    }

    /**
     * Complete the execution and generate a receipt.
     * Creates execution_completed event, then receipt_created + receipt_issued events.
     */
    public function complete(WorkflowExecution $execution, string $notes = null): Receipt
    {
        if (!$execution->isInProgress()) {
            throw new \RuntimeException('هذا التنفيذ ليس نشطاً');
        }

        return DB::transaction(function () use ($execution, $notes) {
            $version = $execution->version;
            $register = Register::lockForUpdate()->findOrFail($execution->register_id);

            // Replay to verify state consistency
            $replayedState = $this->replayExecutionState($execution->id);
            if ($replayedState['total_amount'] !== $this->ctx->normalize((string) $execution->total_amount)) {
                throw new \RuntimeException(
                    "State mismatch: cached total {$execution->total_amount} vs replayed total {$replayedState['total_amount']}"
                );
            }

            // Append completion event
            $this->eventStore->appendExecutionEvent(
                executionId: $execution->id,
                eventType: WorkflowExecutionEvent::EXECUTION_COMPLETED,
                payload: [
                    'total_amount' => $execution->total_amount,
                    'notes' => $notes,
                    'calculated_items_count' => count($execution->calculated_items ?? []),
                ],
                calculatedItems: $execution->calculated_items ?? [],
                feeSnapshot: $this->buildFeeSnapshotFromExecution($execution),
                contextSnapshot: $this->captureContext(),
                causedBy: $execution->started_by,
            );

            // Update execution cache
            $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'lock_version' => $execution->lock_version + 1,
                ]);

            $execution->refresh();

            // Generate receipt number
            $receiptNumber = $register->generateReceiptNumber();

            // Create receipt
            $receipt = Receipt::create([
                'receipt_number' => $receiptNumber,
                'register_id' => $register->id,
                'workflow_execution_id' => $execution->id,
                'workflow_version_id' => $version->id,
                'created_by' => $execution->started_by,
                'total_amount' => $execution->total_amount,
                'status' => 'draft',
                'version' => 1,
                'lock_version' => 0,
                'notes' => $notes,
                'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            // Create receipt items
            foreach ($execution->calculated_items as $item) {
                ReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'field_id' => $item['field_id'] ?? null,
                    'field_name_snapshot' => $item['field_name'] ?? '',
                    'label_ar_snapshot' => $item['label'] ?? '',
                    'amount' => $item['amount'] ?? '0',
                    'text_value' => $item['text_value'] ?? null,
                ]);
            }

            // Append receipt_created event
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: \App\Models\ReceiptEvent::RECEIPT_CREATED,
                afterState: [
                    'receipt_number' => $receiptNumber,
                    'total_amount' => $receipt->total_amount,
                    'status' => 'draft',
                    'notes' => $notes,
                    'items' => $execution->calculated_items,
                ],
                feeSnapshot: $this->buildFeeSnapshotFromExecution($execution),
                contextSnapshot: $this->captureContext(),
                lockVersion: 0,
                causedBy: $execution->started_by,
            );

            // Create calculation snapshot
            $snapshot = $this->buildSnapshot($execution, $receipt);
            ReceiptCalculationSnapshot::create($snapshot);

            // Append receipt_issued event (auto-issue on completion)
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: \App\Models\ReceiptEvent::RECEIPT_ISSUED,
                afterState: [
                    'receipt_number' => $receiptNumber,
                    'total_amount' => $receipt->total_amount,
                    'status' => 'issued',
                    'notes' => $notes,
                    'items' => $execution->calculated_items,
                    'qr_payload' => $snapshot['calculation_hash'],
                ],
                beforeState: [
                    'status' => 'draft',
                ],
                feeSnapshot: $this->buildFeeSnapshotFromExecution($execution),
                contextSnapshot: $this->captureContext(),
                lockVersion: 1,
                causedBy: $execution->started_by,
            );

            // Update receipt status
            $receipt->where('id', $receipt->id)->update([
                'status' => 'issued',
                'approved_by' => $execution->started_by,
                'qr_payload' => json_encode($snapshot),
                'lock_version' => 1,
            ]);

            // Link execution to receipt
            $execution->update(['receipt_id' => $receipt->id]);

            return $receipt->load('items');
        });
    }

    /**
     * Cancel an execution.
     * Creates execution_cancelled event.
     */
    public function cancel(WorkflowExecution $execution, string $reason): WorkflowExecution
    {
        if (!$execution->isInProgress()) {
            throw new \RuntimeException('هذا التنفيذ ليس نشطاً');
        }

        return DB::transaction(function () use ($execution, $reason) {
            $this->eventStore->appendExecutionEvent(
                executionId: $execution->id,
                eventType: WorkflowExecutionEvent::EXECUTION_CANCELLED,
                payload: [
                    'reason' => $reason,
                    'total_amount_at_cancel' => $execution->total_amount,
                ],
                contextSnapshot: $this->captureContext(),
                causedBy: $execution->started_by,
            );

            $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason,
                    'lock_version' => $execution->lock_version + 1,
                ]);

            return $execution->fresh();
        });
    }

    /**
     * Replay execution state from events.
     * This proves state = function(events).
     */
    public function replayExecutionState(string $executionId): array
    {
        $events = $this->eventStore->getExecutionEvents($executionId);

        $state = [
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
        ];

        foreach ($events as $event) {
            match ($event['event_type']) {
                WorkflowExecutionEvent::EXECUTION_STARTED => $this->applyExecutionStarted($state, $event),
                WorkflowExecutionEvent::STEP_SUBMITTED => $this->applyStepSubmitted($state, $event),
                WorkflowExecutionEvent::EXECUTION_COMPLETED => $this->applyExecutionCompleted($state, $event),
                WorkflowExecutionEvent::EXECUTION_CANCELLED => $this->applyExecutionCancelled($state, $event),
                default => null,
            };
        }

        return $state;
    }

    // ============================================================
    // EVENT APPLICATORS (Internal)
    // ============================================================

    protected function applyExecutionStarted(array &$state, array $event): void
    {
        $state['status'] = 'in_progress';
        $state['current_step_index'] = $event['event_payload']['step_index'] ?? 0;
    }

    protected function applyStepSubmitted(array &$state, array $event): void
    {
        $payload = $event['event_payload'];
        $state['current_step_index'] = $payload['next_step_index'] ?? $state['current_step_index'];
        $state['values_snapshot'] = $payload['values'] ?? $state['values_snapshot'];

        $newItems = $event['calculated_items'] ?? [];
        if (!empty($newItems)) {
            $state['calculated_items'] = array_merge($state['calculated_items'], $newItems);
        }

        $stepTotal = $payload['step_total'] ?? '0';
        $state['total_amount'] = bcadd($state['total_amount'], $stepTotal, $this->ctx->scale());
    }

    protected function applyExecutionCompleted(array &$state, array $event): void
    {
        $state['status'] = 'completed';
    }

    protected function applyExecutionCancelled(array &$state, array $event): void
    {
        $state['status'] = 'cancelled';
    }

    // ============================================================
    // HELPERS (Unchanged from Phase 2)
    // ============================================================

    protected function sanitizeInput($stepFields, array $values, array $currentValues): array
    {
        $sanitized = [];

        foreach ($values as $fieldId => $value) {
            $field = null;

            if (str_starts_with($fieldId, 'custom_')) {
                $wfId = substr($fieldId, 7);
                $field = $stepFields->firstWhere('id', $wfId);
            }

            if (!$field) {
                $field = $stepFields->firstWhere('register_field_id', $fieldId);
            }

            if (!$field) {
                $field = $stepFields->firstWhere('id', $fieldId);
            }

            if (!$field) {
                continue;
            }

            $resolvedFieldId = $field->register_field_id ?? 'custom_'.$field->id;

            if ($field->is_locked) {
                if (isset($currentValues[$resolvedFieldId])) {
                    $sanitized[$resolvedFieldId] = $currentValues[$resolvedFieldId];
                }
                continue;
            }

            if ($field->is_visible === false) {
                continue;
            }

            $sanitized[$resolvedFieldId] = $value;
        }

        return $sanitized;
    }

    protected function buildFieldStates($fields, array $actions): array
    {
        $states = [];
        foreach ($fields as $field) {
            $rfId = $field->register_field_id ?? 'custom_'.$field->id;
            $states[$rfId] = [
                'is_visible' => $field->is_visible,
                'is_editable' => ($field->is_editable ?? true) && !$field->is_locked,
                'is_locked' => $field->is_locked,
                'is_required' => $field->is_required,
                'is_readonly' => ($field->is_readonly ?? false) || $field->is_locked,
                'is_financial' => $field->is_financial,
                'is_insured' => $field->is_insured,
                'insurance_value' => $field->insurance_value,
                'priority' => $field->priority,
                'field_type' => $field->field_type,
                'options' => $field->resolved_options,
                'validation_rules' => $field->resolved_validation_rules,
                'is_custom' => $field->register_field_id === null,
                'workflow_field_id' => $field->id,
            ];
        }

        foreach ($actions as $action) {
            $targetId = $action['target_field_id'] ?? null;
            if (!$targetId) continue;

            switch ($action['action'] ?? '') {
                case 'hide':
                    if (isset($states[$targetId])) $states[$targetId]['is_visible'] = false;
                    break;
                case 'show':
                    if (isset($states[$targetId])) $states[$targetId]['is_visible'] = true;
                    break;
                case 'set_visibility':
                    if (isset($states[$targetId])) {
                        $val = $action['value'] ?? $action['resolved_value'] ?? 'visible';
                        $states[$targetId]['is_visible'] = in_array($val, ['visible', 'show', 'true', true, '1', 1], true);
                    }
                    break;
                case 'set_required':
                    if (isset($states[$targetId])) $states[$targetId]['is_required'] = (bool) ($action['value'] ?? $action['resolved_value'] ?? true);
                    break;
                case 'set_readonly':
                    if (isset($states[$targetId])) $states[$targetId]['is_readonly'] = (bool) ($action['value'] ?? $action['resolved_value'] ?? true);
                    break;
                case 'set_field_type':
                    if (isset($states[$targetId])) $states[$targetId]['field_type'] = $action['resolved_value'] ?? $action['value'] ?? 'text';
                    break;
                case 'set_options':
                    if (isset($states[$targetId])) $states[$targetId]['options'] = $action['resolved_value'] ?? $action['value'] ?? [];
                    break;
                case 'set_lock':
                    if (isset($states[$targetId])) $states[$targetId]['is_locked'] = (bool) ($action['value'] ?? $action['resolved_value'] ?? true);
                    break;
                case 'set_editable':
                    if (isset($states[$targetId])) $states[$targetId]['is_editable'] = (bool) ($action['value'] ?? $action['resolved_value'] ?? true);
                    break;
            }
        }

        return $states;
    }

    protected function applySetValueActions(array $values, array $actions): array
    {
        $modified = $values;
        foreach ($actions as $action) {
            $act = $action['action'] ?? '';
            $targetId = $action['target_field_id'] ?? null;

            if ($act === 'set_value' && $targetId) {
                $modified[$targetId] = $action['resolved_value'] ?? $action['value'] ?? '';
            } elseif ($act === 'calculate' && $targetId) {
                $modified[$targetId] = $action['resolved_amount'] ?? '0';
            } elseif ($act === 'set_fee' && $targetId) {
                $modified[$targetId] = $action['resolved_amount'] ?? '0';
            } elseif ($act === 'apply_discount' && $targetId) {
                $modified[$targetId] = $action['resolved_amount'] ?? '0';
            } elseif ($act === 'override_value' && $targetId) {
                $modified[$targetId] = $action['resolved_value'] ?? $action['value'] ?? '';
            }
        }
        return $modified;
    }

    protected function calculateItems($fields, array $values, array $actions): array
    {
        $items = [];
        $feeAmounts = [];

        $actionsByField = [];
        foreach ($actions as $action) {
            $targetId = $action['target_field_id'] ?? null;
            if ($targetId) {
                $actionsByField[$targetId][] = $action;
            }
        }

        foreach ($fields as $field) {
            if (!empty($field->fee_code)) {
                $feeVersion = $this->feeEngine->resolve($field->fee_code);
                $feeAmounts[$field->fee_code] = $feeVersion?->amount ?? '0';

                $this->ctx->recordFeeSnapshot($field->fee_code, [
                    'fee_name' => $feeVersion?->fee?->name_ar ?? $field->fee_code,
                    'amount' => $feeVersion?->amount ?? '0',
                    'version' => $feeVersion?->version,
                    'effective_from' => $feeVersion?->effective_from?->toDateString(),
                ]);
            }
        }

        foreach ($fields as $field) {
            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            $textValue = $values[$fieldId] ?? null;
            $fieldActions = $actionsByField[$fieldId] ?? [];

            $feeActions = [];
            $otherActions = [];
            foreach ($fieldActions as $action) {
                $act = $action['action'] ?? '';
                if ($act === 'set_fee') {
                    $feeActions[] = $action;
                } else {
                    $otherActions[] = $action;
                }
            }

            $amount = '0';
            $actionType = null;
            foreach ($otherActions as $action) {
                $act = $action['action'] ?? '';
                if ($act === 'calculate') {
                    $amount = (string) ($action['resolved_amount'] ?? '0');
                    $actionType = 'calculate';
                } elseif ($act === 'set_value') {
                    $textValue = $action['resolved_value'] ?? $textValue;
                    $actionType = 'set_value';
                    if ($field->is_financial && is_numeric($textValue) && $textValue !== '') {
                        $amount = $this->normalizeDecimal((string) $textValue);
                    }
                }
            }

            if ($amount === '0' && $actionType === null) {
                if (!empty($field->fee_code)) {
                    $amount = (string) ($feeAmounts[$field->fee_code] ?? '0');
                } elseif (!empty($field->calculation_formula)) {
                    $amount = $this->feeEngine->calculateRaw($field->calculation_formula, $values, $feeAmounts);
                } elseif ($field->is_financial && is_numeric($textValue) && $textValue !== '' && $textValue !== null) {
                    $amount = $this->normalizeDecimal((string) $textValue);
                }
            }

            foreach ($feeActions as $feeAction) {
                $feeCode = $feeAction['fee_code'] ?? null;
                $feeVersionId = $feeAction['fee_version_id'] ?? null;
                $feeAmount = (string) ($feeAction['resolved_amount'] ?? '0');

                $amountIsPositive = bccomp($feeAmount, '0', $this->ctx->scale()) > 0;
                if ($amountIsPositive) {
                    $items[] = [
                        'field_id' => $fieldId,
                        'field_name' => $field->name,
                        'label' => $field->label,
                        'amount' => $feeAmount,
                        'text_value' => null,
                        'fee_code' => $feeCode,
                        'fee_version_id' => $feeVersionId,
                        'action' => 'set_fee',
                        'is_insured' => $field->is_insured,
                        'insurance_value' => $field->insurance_value,
                    ];
                }
            }

            $amountIsPositive = bccomp($amount, '0', $this->ctx->scale()) > 0;
            $hasSetValue = $actionType === 'set_value';
            $shouldInclude = $field->is_financial
                || !empty($field->fee_code)
                || !empty($field->calculation_formula)
                || $hasSetValue
                || $amountIsPositive;

            if (empty($feeActions) && $shouldInclude && ($amountIsPositive || $textValue !== null)) {
                $feeCode = !empty($field->fee_code) ? $field->fee_code : null;
                $feeVersionId = null;
                if (!empty($feeCode)) {
                    $feeVersion = $this->feeEngine->resolve($feeCode);
                    $feeVersionId = $feeVersion?->id;
                }

                $items[] = [
                    'field_id' => $fieldId,
                    'field_name' => $field->name,
                    'label' => $field->label,
                    'amount' => $amount,
                    'text_value' => $textValue,
                    'fee_code' => $feeCode,
                    'fee_version_id' => $feeVersionId,
                    'action' => $actionType,
                    'is_insured' => $field->is_insured,
                    'insurance_value' => $field->insurance_value,
                    'field_type' => $field->field_type,
                ];
            }
        }

        return $items;
    }

    /**
     * Build financial calculation trace for debugging.
     */
    protected function buildFinancialTrace($fields, array $values, array $calculatedItems, array $actions): array
    {
        $trace = [];
        foreach ($fields as $field) {
            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            if (!$field->is_financial && empty($field->fee_code) && empty($field->calculation_formula)) {
                continue;
            }
            $rawValue = $values[$fieldId] ?? null;
            $item = null;
            foreach ($calculatedItems as $ci) {
                if ($ci['field_id'] === $fieldId) {
                    $item = $ci;
                    break;
                }
            }
            $trace[] = [
                'field_id' => $fieldId,
                'field_name' => $field->name,
                'label' => $field->label,
                'is_financial' => $field->is_financial,
                'fee_code' => $field->fee_code,
                'raw_value' => $rawValue,
                'is_numeric' => is_numeric($rawValue),
                'calculated_amount' => $item ? $item['amount'] : '0',
                'included_in_total' => $item !== null,
            ];
        }
        return $trace;
    }

    /**
     * Normalize a string value to a BC Math decimal string.
     */
    protected function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '0.000';
        }
        if (str_contains($value, '.')) {
            $parts = explode('.', $value, 2);
            $integerPart = $parts[0];
            $decimalPart = str_pad(substr($parts[1], 0, $this->ctx->scale()), $this->ctx->scale(), '0');
            return $integerPart . '.' . $decimalPart;
        }
        return $value . '.000';
    }

    protected function sumItems(array $items): string
    {
        $total = '0';
        foreach ($items as $item) {
            $amt = (string) ($item['amount'] ?? '0');
            $total = bcadd($total, $amt, $this->ctx->scale());
        }
        return $total;
    }

    protected function findNextVisibleStep(WorkflowVersion $version, int $startIndex, array $values): int
    {
        $steps = $version->steps->sortBy('sort_order')->values();

        for ($i = $startIndex; $i < $steps->count(); $i++) {
            $step = $steps[$i];
            if ($this->ruleEngine->isStepVisible($step->condition_logic ?? [], $values)) {
                return $i;
            }
        }

        return $steps->count();
    }

    protected function buildSnapshot(WorkflowExecution $execution, Receipt $receipt): array
    {
        $version = $execution->version;

        $snapshot = [
            'receipt_id' => $receipt->id,
            'workflow_version_id' => $version->id,
            'workflow_definition' => $this->canonicalJson($version->fields->toArray()),
            'rules_applied' => $execution->calculated_items,
            'fees_used' => $this->buildFeeSnapshotsFromExecution($execution),
            'field_values' => $execution->values_snapshot,
        ];

        $snapshot['calculation_hash'] = hash('sha256', $this->canonicalJson([
            'field_values' => $snapshot['field_values'],
            'fees_used' => $snapshot['fees_used'],
            'receipt_id' => $snapshot['receipt_id'],
            'rules_applied' => $snapshot['rules_applied'],
            'workflow_definition' => $snapshot['workflow_definition'],
            'workflow_version_id' => $snapshot['workflow_version_id'],
        ]));

        return $snapshot;
    }

    protected function buildFeeSnapshotFromItems(array $calculatedItems): array
    {
        $snapshot = [];
        foreach ($calculatedItems as $item) {
            if (!empty($item['fee_code'])) {
                $snapshot[$item['fee_code']] = [
                    'fee_version_id' => $item['fee_version_id'] ?? null,
                    'amount' => $item['amount'] ?? '0',
                    'fee_name' => $item['field_name'] ?? $item['fee_code'],
                ];
            }
        }
        return $snapshot;
    }

    protected function buildFeeSnapshotsFromExecution(WorkflowExecution $execution): array
    {
        $fees = [];
        $calculatedItems = $execution->calculated_items ?? [];

        $feeCodesUsed = [];
        foreach ($calculatedItems as $item) {
            if (!empty($item['fee_code'])) {
                $feeCodesUsed[$item['fee_code']] = true;
            }
        }

        foreach ($feeCodesUsed as $feeCode => $_) {
            $chargedAmount = '0';
            foreach ($calculatedItems as $item) {
                if (($item['fee_code'] ?? null) === $feeCode) {
                    $chargedAmount = bcadd($chargedAmount, (string) ($item['amount'] ?? '0'), $this->ctx->scale());
                }
            }

            $feeVersionId = null;
            foreach ($calculatedItems as $item) {
                if (($item['fee_code'] ?? null) === $feeCode && !empty($item['fee_version_id'])) {
                    $feeVersionId = $item['fee_version_id'];
                    break;
                }
            }

            $feeVersion = $this->feeEngine->resolve($feeCode);

            $fees[$feeCode] = [
                'charged_amount' => $chargedAmount,
                'effective_from' => $feeVersion?->effective_from?->toDateString(),
                'fee_name' => $feeVersion?->fee?->name_ar ?? $feeCode,
                'fee_version_at_resolution' => $feeVersion?->version,
                'fee_version_id' => $feeVersionId,
            ];
        }

        return $fees;
    }

    protected function canonicalJson(mixed $data): string
    {
        return json_encode(
            $this->sortKeysRecursive($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    protected function sortKeysRecursive(mixed $data): mixed
    {
        if (is_array($data)) {
            if (array_keys($data) !== range(0, count($data) - 1)) {
                ksort($data);
                foreach ($data as $key => $value) {
                    $data[$key] = $this->sortKeysRecursive($value);
                }
            } else {
                foreach ($data as $key => $value) {
                    $data[$key] = $this->sortKeysRecursive($value);
                }
            }
        }
        return $data;
    }

    protected function buildFeeSnapshotFromExecution(WorkflowExecution $execution): array
    {
        $snapshots = [];
        foreach ($execution->calculated_items ?? [] as $item) {
            $snapshots[] = [
                'field_id' => $item['field_id'] ?? null,
                'fee_code' => $item['fee_code'] ?? null,
                'amount' => $item['amount'] ?? '0.000',
                'quantity' => $item['quantity'] ?? '1',
                'total' => $item['total'] ?? $item['amount'] ?? '0.000',
            ];
        }
        return $snapshots;
    }

    protected function captureContext(): array
    {
        return [
            'scale' => $this->ctx->scale(),
            'rounding_mode' => $this->ctx->roundingMode(),
            'strict_mode' => $this->ctx->strictMode(),
            'max_value' => $this->ctx->maxValue(),
            'division_by_zero_policy' => $this->ctx->divisionByZeroPolicy(),
            'fee_snapshots' => $this->ctx->feeSnapshots(),
        ];
    }
}

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
    protected \App\Services\ValidationEngine $legacyValidationEngine;
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
        \App\Services\ValidationEngine $legacyValidationEngine,
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
        $this->legacyValidationEngine = $legacyValidationEngine;
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
        if (!$execution->isInProgress() && !$execution->isPaused()) {
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

            // Normalize field keys so rule engines can look up by UUID (condition field_id)
            // or canonical key (register_field_id / custom_<id>) interchangeably.
            // This is used ONLY for engine consumption; $mergedValues stays canonical-only.
            $normalizedValues = $this->normalizeFieldKeys($mergedValues, $fields);

            $validationErrors = $this->validationEngine->validateAll($stepFields, $normalizedValues);
            $crossFieldErrors = $this->crossFieldValidation->validateAll($stepFields, $normalizedValues);
            $allErrors = array_merge($validationErrors, $crossFieldErrors);

            if (!empty($allErrors)) {
                throw new \RuntimeException(json_encode([
                    'error' => 'validation_failed',
                    'message' => 'فشل التحقق من صحة الحقول',
                    'errors' => $allErrors,
                ], JSON_UNESCAPED_UNICODE));
            }

            $computedValues = $this->computedEngine->recalculateAll($stepFields, $normalizedValues);
            $normalizedValues = array_merge($normalizedValues, $computedValues);

            // 1. Run legacy validation engine (rules without rule_config)
            $legacyResult = $this->legacyValidationEngine->validate(
                $version->id,
                $normalizedValues,
                ['execution_id' => $execution->id, 'step_index' => $stepIndex, 'acting_user' => auth()->user()]
            );

            // Extract legacy validation blocks, warnings, and routing decisions
            $legacyBlocks = [];
            $legacyWarnings = [];
            $legacyRouting = [];
            foreach ($legacyResult['results'] ?? [] as $r) {
                if ($r['status'] === 'failed' && ($r['response_type'] ?? '') === 'error') {
                    $legacyBlocks[] = [
                        'rule_id' => $r['rule_id'],
                        'action' => 'legacy_validation',
                        'validation_type' => $r['validation_type'],
                        'result' => 'failed',
                        'response_type' => 'error',
                        'message_ar' => $r['message'] ?? '',
                        'message_en' => $r['message'] ?? '',
                    ];
                }
                if ($r['status'] === 'failed' && ($r['response_type'] ?? '') === 'warning') {
                    $legacyWarnings[] = $r;
                }
                if (($r['status'] ?? '') === 'found' && ($r['decision'] ?? '') !== 'continue_workflow') {
                    $legacyRouting[] = $r;
                }
            }

            // Load validation rules for dynamic execute_validation actions
            // Only enterprise rules (rule_config IS NOT NULL) to prevent legacy double-execution.
            $validationRules = \App\Models\ValidationRule::where('workflow_version_id', $version->id)
                ->where('is_active', true)
                ->whereNotNull('rule_config')
                ->get();

            // 2. Unified rule evaluation — EnterpriseRuleEngine handles ALL rule types:
            // - Enterprise rules (validation_rules with rule_config)
            // - Simple rules (workflow_rules)
            // - Case-based rules (workflow_rules)
            $enterpriseResult = $this->enterpriseEngine->execute(
                $version->id,
                $normalizedValues,
                [
                    'step_index' => $stepIndex,
                    'execution_id' => $execution->id,
                    'validation_rules' => $validationRules,
                ]
            );

            // Detect pause/resume status changes from rule results
            $statusChange = null;
            foreach ($enterpriseResult['results'] ?? [] as $r) {
                if ($r['matched'] && !empty($r['status_change'])) {
                    $statusChange = $r['status_change'];
                }
            }

            // If execution is paused and no resume action triggered, reject
            if ($execution->isPaused() && $statusChange !== 'in_progress') {
                throw new \App\Exceptions\Workflow\ExecutionPausedException();
            }

            // 3. Check for execute_validation blocks (error only — warnings flow through)
            $enterpriseBlocks = [];
            foreach ($enterpriseResult['results'] ?? [] as $r) {
                if ($r['matched'] && !empty($r['field_effects'])) {
                    foreach ($r['field_effects'] as $effect) {
                        if ($effect['action'] === 'execute_validation'
                            && ($effect['result'] ?? '') === 'failed'
                            && ($effect['response_type'] ?? '') === 'error') {
                            $enterpriseBlocks[] = $effect;
                        }
                    }
                }
            }

            // 4. Merge ALL blocks and block if any exist
            $allBlocks = array_merge($legacyBlocks, $enterpriseBlocks);
            if (!empty($allBlocks)) {
                throw new \App\Exceptions\Workflow\ValidationBlockedException($allBlocks);
            }

            // Build a quick lookup so engine effects (authored with UUID field_ids)
            // are applied to the canonical key we persist in the snapshot.
            $fieldIdToCanonical = [];
            foreach ($fields as $field) {
                $canonical = $field->register_field_id ?? 'custom_'.$field->id;
                $fieldIdToCanonical[$field->id] = $canonical;
                $fieldIdToCanonical[$canonical] = $canonical;
                $fieldIdToCanonical['custom_'.$field->id] = $canonical;
                if (!empty($field->register_field_id)) {
                    $fieldIdToCanonical[$field->register_field_id] = $canonical;
                }
            }

            // Transform enterprise field_effects to legacy action format for downstream consumers
            $allActions = [];
            foreach ($enterpriseResult['results'] ?? [] as $r) {
                if ($r['matched'] && !empty($r['field_effects'])) {
                    foreach ($r['field_effects'] as $effect) {
                        $canonicalFieldId = $fieldIdToCanonical[$effect['field_id'] ?? ''] ?? ($effect['field_id'] ?? '');
                        $action = [
                            'target_field_id' => $canonicalFieldId,
                            'action' => $effect['action'] ?? 'set_value',
                        ];
                        if ($effect['action'] === 'set_fee') {
                            $action['fee_code'] = $effect['fee_code'] ?? null;
                            $action['resolved_amount'] = $effect['amount'] ?? null;
                            $action['fee_version_id'] = $effect['fee_version_id'] ?? null;
                            \Log::debug('set_fee action transformed', ['effect' => $effect, 'canonicalFieldId' => $canonicalFieldId, 'action' => $action]);
                        } elseif ($effect['action'] === 'calculate') {
                            $action['resolved_amount'] = $effect['result'] ?? null;
                        } elseif ($effect['action'] === 'apply_discount') {
                            $action['resolved_amount'] = $effect['value'] ?? null;
                        } elseif ($effect['action'] === 'clear_value') {
                            $action['resolved_value'] = null;
                        } elseif ($effect['action'] === 'copy_value') {
                            $action['resolved_value'] = $effect['value'] ?? null;
                        } elseif (array_key_exists('value', $effect)) {
                            $action['resolved_value'] = $effect['value'];
                        }
                        $allActions[] = $action;
                    }
                }
            }

            $modifiedValues = $this->applySetValueActions($mergedValues, $allActions);

            // Preserve generate_reference in snapshot for reuse
            foreach ($enterpriseResult['results'] ?? [] as $r) {
                if ($r['matched'] && !empty($r['field_effects'])) {
                    foreach ($r['field_effects'] as $effect) {
                        if ($effect['action'] === 'generate_reference' && isset($effect['value'])) {
                            $modifiedValues['__generated_reference__'] = $effect['value'];
                        }
                    }
                }
            }
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
            $calculatedItems = $this->calculateItems($visibleFields, $modifiedValues, $allActions, $fields);
            $stepTotal = $this->sumItems($calculatedItems);

            // Build detailed financial trace from the rule engine (step-by-step transformations)
            $financialTrace = $enterpriseResult['financial_trace'] ?? [];

            // Compute total discount applied in this step
            $discountApplied = '0';
            foreach ($financialTrace as $t) {
                if ($t['step'] === 'discount') {
                    $discountApplied = bcadd($discountApplied, (string) ($t['discount_amount'] ?? '0'), $this->ctx->scale());
                }
            }
            $discountApplied = $this->normalizeDecimal($discountApplied);

            // Compute snapshot hash for integrity verification
            $snapshotHash = hash('sha256', $this->canonicalJson([
                'calculated_items' => $calculatedItems,
                'financial_trace' => $financialTrace,
                'step_total' => $stepTotal,
                'execution_id' => $execution->id,
                'step_index' => $stepIndex,
            ]));

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
                    'matched_rules' => $enterpriseResult['matched_rules'] ?? 0,
                    'insurance_snapshots' => $insuranceSnapshots,
                    'field_changes' => $fieldChanges,
                    'computed_values' => $computedValues,
                    'financial_trace' => $financialTrace,
                    'discount_applied' => $discountApplied,
                    'snapshot_hash' => $snapshotHash,
                ],
                calculatedItems: $calculatedItems,
                feeSnapshot: $feeSnapshot,
                contextSnapshot: $this->captureContext(),
                idempotencyKey: $values['idempotency_key'] ?? null,
                causedBy: $execution->started_by,
            );

            // Update denormalized cache (not source of truth)
            // CRITICAL: Deduplicate items by field_id to prevent double-counting
            $mergedItems = array_merge($execution->calculated_items ?? [], $calculatedItems);
            $uniqueItems = $this->deduplicateItemsByFieldId($mergedItems);
            
            $updateData = [
                'current_step_index' => $nextStepIndex,
                'values_snapshot' => $modifiedValues,
                'calculated_items' => $uniqueItems,
                'financial_trace' => array_merge($execution->financial_trace ?? [], $financialTrace),
                'total_amount' => $newTotal,
                'lock_version' => $execution->lock_version + 1,
            ];
            if ($statusChange) {
                $updateData['status'] = $statusChange;
            }

            $affected = $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', $execution->status)
                ->update($updateData);

            if ($affected === 0) {
                throw new \App\Exceptions\Workflow\ExecutionNotInProgressException(
                    'Execution was modified by another request. Please refresh and try again.',
                    409
                );
            }

            $fresh = $execution->fresh();

            return [
                'execution' => $fresh,
                'modified_values' => $modifiedValues,
                'field_states' => $fieldStates,
                'calculated_items' => $uniqueItems,
                'total_amount' => $newTotal,
                'insurance_snapshots' => $insuranceSnapshots,
                'computed_values' => $computedValues,
                'field_changes' => $fieldChanges,
                'audit_summary' => $this->auditTrail->getSummary(),
                'financial_trace' => $financialTrace,
                'discount_applied' => $discountApplied,
                'snapshot_hash' => $snapshotHash,
                // TODO: legacy_warnings و legacy_routing مؤقتان
                // دمجهما في execution_result موحَّد عند Phase 7 refactor
                'legacy_warnings' => $legacyWarnings,
                'legacy_routing' => $legacyRouting,
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

        // Normalize field keys so rule engines can look up by UUID or canonical key
        $normalizedValues = $this->normalizeFieldKeys($values, $fields);

        // Unified rule evaluation
        $ruleResult = $this->enterpriseEngine->execute(
            $version->id,
            $normalizedValues,
            ['preview' => true]
        );

        // Build canonical lookup for engine effects
        $fieldIdToCanonical = [];
        foreach ($fields as $field) {
            $canonical = $field->register_field_id ?? 'custom_'.$field->id;
            $fieldIdToCanonical[$field->id] = $canonical;
            $fieldIdToCanonical[$canonical] = $canonical;
            $fieldIdToCanonical['custom_'.$field->id] = $canonical;
            if (!empty($field->register_field_id)) {
                $fieldIdToCanonical[$field->register_field_id] = $canonical;
            }
        }

        // Transform enterprise field_effects to legacy action format
        $allActions = [];
        foreach ($ruleResult['results'] ?? [] as $r) {
            if ($r['matched'] && !empty($r['field_effects'])) {
                foreach ($r['field_effects'] as $effect) {
                    $canonicalFieldId = $fieldIdToCanonical[$effect['field_id'] ?? ''] ?? ($effect['field_id'] ?? '');
                    $action = [
                        'target_field_id' => $canonicalFieldId,
                        'action' => $effect['action'] ?? 'set_value',
                    ];
                    if ($effect['action'] === 'set_fee') {
                        $action['fee_code'] = $effect['fee_code'] ?? null;
                        $action['resolved_amount'] = $effect['amount'] ?? null;
                        $action['fee_version_id'] = $effect['fee_version_id'] ?? null;
                    } elseif ($effect['action'] === 'calculate') {
                        $action['resolved_amount'] = $effect['result'] ?? null;
                    } elseif ($effect['action'] === 'apply_discount') {
                        $action['resolved_amount'] = $effect['value'] ?? null;
                    } elseif ($effect['action'] === 'clear_value') {
                        $action['resolved_value'] = null;
                    } elseif ($effect['action'] === 'copy_value') {
                        $action['resolved_value'] = $effect['value'] ?? null;
                    } elseif (array_key_exists('value', $effect)) {
                        $action['resolved_value'] = $effect['value'];
                    }
                    $allActions[] = $action;
                }
            }
        }

        $fieldStates = $this->buildFieldStates($fields, $allActions);
        $fieldStates = $this->visibilityResolver->applyFieldControlActions($fieldStates, $allActions);
        $modifiedValues = $this->applySetValueActions($normalizedValues, $allActions);

        $schema = $this->schemaBuilder->buildForVersion($fields, $modifiedValues);
        $visibleSchema = $this->schemaBuilder->filterVisible($schema);

        $visibleFieldIds = array_column($visibleSchema, 'field_id');
        $visibleFields = $fields->filter(fn($f) => in_array($f->register_field_id ?? 'custom_'.$f->id, $visibleFieldIds, true));

        $calculatedItems = $this->calculateItems($visibleFields, $modifiedValues, $allActions, $fields);
        $totalAmount = $this->sumItems($calculatedItems);

        $insuranceSnapshots = $this->insuranceEngine->collectInsuranceSnapshots($visibleFields, $modifiedValues);
        $financialTrace = $ruleResult['financial_trace'] ?? [];

        $discountApplied = '0';
        foreach ($financialTrace as $t) {
            if ($t['step'] === 'discount') {
                $discountApplied = bcadd($discountApplied, (string) ($t['discount_amount'] ?? '0'), $this->ctx->scale());
            }
        }
        $discountApplied = $this->normalizeDecimal($discountApplied);

        $snapshotHash = hash('sha256', $this->canonicalJson([
            'calculated_items' => $calculatedItems,
            'financial_trace' => $financialTrace,
            'step_total' => $totalAmount,
        ]));

        return [
            'items' => $calculatedItems,
            'total_amount' => $totalAmount,
            'grand_total' => $totalAmount,
            'matched_rules' => $ruleResult['matched_rules'] ?? 0,
            'actions' => $allActions,
            'values' => $values,
            'modified_values' => $modifiedValues,
            'field_states' => $fieldStates,
            'insurance_snapshots' => $insuranceSnapshots,
            'schema' => $visibleSchema,
            'financial_trace' => $financialTrace,
            'discount_applied' => $discountApplied,
            'snapshot_hash' => $snapshotHash,
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

            // Update execution cache with optimistic lock verification
            $affected = $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'lock_version' => $execution->lock_version + 1,
                ]);

            if ($affected === 0) {
                throw new \App\Exceptions\Workflow\ExecutionNotInProgressException(
                    'Execution was modified by another request. Please refresh and try again.',
                    409
                );
            }

            $execution->refresh();

            // Generate receipt number (reuse if a reference was generated earlier by generate_reference action)
            $preGenerated = $execution->values_snapshot['__generated_reference__'] ?? null;
            $receiptNumber = $preGenerated ?? $register->generateReceiptNumber();

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
                'idempotency_key' => 'exec_' . $execution->id . '_' . md5($execution->lock_version . json_encode($execution->calculated_items ?? [])),
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

            $affected = $execution->where('id', $execution->id)
                ->where('lock_version', $execution->lock_version)
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason,
                    'lock_version' => $execution->lock_version + 1,
                ]);

            if ($affected === 0) {
                throw new \App\Exceptions\Workflow\ExecutionNotInProgressException(
                    'Execution was modified by another request. Please refresh and try again.',
                    409
                );
            }

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

    /**
     * Normalize field keys so that every alias (UUID, register_field_id, custom_<id>)
     * maps to the same canonical value. This ensures rule engines can look up values
     * by whichever key format the condition/action was authored with.
     */
    protected function normalizeFieldKeys(array $values, $fields): array
    {
        $normalized = $values;

        foreach ($fields as $field) {
            $canonical = $field->register_field_id ?? 'custom_'.$field->id;
            $aliases = [$field->id, 'custom_'.$field->id];
            if (!empty($field->register_field_id)) {
                $aliases[] = $field->register_field_id;
            }

            // Prefer canonical value (authoritative, set by sanitizeInput),
            // then fall back to any alias.
            $bestValue = null;
            $bestFound = false;
            if (array_key_exists($canonical, $values)) {
                $bestValue = $values[$canonical];
                $bestFound = true;
            } else {
                foreach ($aliases as $alias) {
                    if (array_key_exists($alias, $values)) {
                        $bestValue = $values[$alias];
                        $bestFound = true;
                        break;
                    }
                }
            }

            if ($bestFound) {
                $normalized[$canonical] = $bestValue;
                foreach ($aliases as $alias) {
                    $normalized[$alias] = $bestValue;
                }
            }
        }

        return $normalized;
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
                    if (isset($states[$targetId])) $states[$targetId]['is_required'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0, 'optional'], true);
                    break;
                case 'set_optional':
                    if (isset($states[$targetId])) $states[$targetId]['is_required'] = false;
                    break;
                case 'set_readonly':
                    if (isset($states[$targetId])) $states[$targetId]['is_readonly'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0, 'editable'], true);
                    break;
                case 'set_editable':
                    if (isset($states[$targetId])) {
                        $states[$targetId]['is_editable'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0], true);
                        $states[$targetId]['is_readonly'] = !$states[$targetId]['is_editable'];
                    }
                    break;
                case 'set_field_type':
                    if (isset($states[$targetId])) $states[$targetId]['field_type'] = $action['resolved_value'] ?? $action['value'] ?? 'text';
                    break;
                case 'set_options':
                    if (isset($states[$targetId])) $states[$targetId]['options'] = $action['resolved_value'] ?? $action['value'] ?? [];
                    break;
                case 'append_options':
                    if (isset($states[$targetId])) {
                        $existing = $states[$targetId]['options'] ?? [];
                        $new = $action['resolved_value'] ?? $action['value'] ?? [];
                        $states[$targetId]['options'] = array_values(array_merge($existing, $new));
                    }
                    break;
                case 'remove_options':
                    if (isset($states[$targetId])) {
                        $existing = $states[$targetId]['options'] ?? [];
                        $toRemove = $action['resolved_value'] ?? $action['value'] ?? [];
                        $removeKeys = is_array($toRemove) ? array_column($toRemove, 'value') : (array) $toRemove;
                        $states[$targetId]['options'] = array_values(array_filter($existing, fn($opt) => !in_array($opt['value'] ?? $opt, $removeKeys, true)));
                    }
                    break;
                case 'enable':
                    if (isset($states[$targetId])) $states[$targetId]['is_visible'] = true;
                    break;
                case 'disable':
                    if (isset($states[$targetId])) $states[$targetId]['is_visible'] = false;
                    break;
                case 'set_lock':
                    if (isset($states[$targetId])) {
                        $states[$targetId]['is_locked'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0, 'unlock'], true);
                        if ($states[$targetId]['is_locked']) {
                            $states[$targetId]['is_editable'] = false;
                            $states[$targetId]['is_readonly'] = true;
                        }
                    }
                    break;
                case 'unlock':
                    if (isset($states[$targetId])) {
                        $states[$targetId]['is_locked'] = false;
                        $states[$targetId]['is_editable'] = true;
                        $states[$targetId]['is_readonly'] = false;
                    }
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
            } elseif ($act === 'clear_value' && $targetId) {
                $modified[$targetId] = null;
            } elseif ($act === 'copy_value' && $targetId) {
                $modified[$targetId] = $action['resolved_value'] ?? $action['value'] ?? '';
            } elseif ($act === 'generate_reference' && $targetId) {
                $modified[$targetId] = $action['resolved_value'] ?? '';
            }
        }
        return $modified;
    }

    protected function calculateItems($fields, array $values, array $actions, $allFields = null): array
    {
        $items = [];
        $feeAmounts = [];

        // Map every identifier a rule action might target → the field's canonical key.
        // Rules may be authored against register_field_id, the workflow_field PK, or
        // custom_<id>; calculateItems keys everything by the canonical
        // (register_field_id ?? custom_<id>). Without this, an action targeting the
        // workflow_field PK matched no field and its amount was silently dropped → zero total.
        // TODO: Field key convention is normalized at consumption (calculateItems).
        // Clean fix: normalize at rule-build time + migration. Phase 2 candidate.
        // Build alias map from ALL fields in the version so cross-step actions
        // (e.g. set_fee targeting a field in step 2 while evaluating step 1)
        // resolve correctly instead of being treated as unmatched/orphaned.
        $aliasToCanonical = [];
        foreach (($allFields ?? $fields) as $field) {
            $canonical = $field->register_field_id ?? 'custom_'.$field->id;
            $aliasToCanonical[$canonical] = $canonical;
            $aliasToCanonical[$field->id] = $canonical;
            if (!empty($field->register_field_id)) {
                $aliasToCanonical[$field->register_field_id] = $canonical;
            }
            $aliasToCanonical['custom_'.$field->id] = $canonical;
        }

        // Aliases known to the ENTIRE version — used to tell a truly orphaned target
        // (unknown everywhere → hazard) from one that simply belongs to another step
        // (deferred to that step's own calculation, not an error).
        $knownAliases = [];
        foreach (($allFields ?? $fields) as $field) {
            $knownAliases[$field->register_field_id ?? 'custom_'.$field->id] = true;
            $knownAliases[$field->id] = true;
            if (!empty($field->register_field_id)) {
                $knownAliases[$field->register_field_id] = true;
            }
            $knownAliases['custom_'.$field->id] = true;
        }

        $actionsByField = [];
        $unmatchedFinancial = [];
        foreach ($actions as $action) {
            $targetId = $action['target_field_id'] ?? null;
            if (!$targetId) {
                continue;
            }
            $canonical = $aliasToCanonical[$targetId] ?? null;
            if ($canonical === null) {
                // Not in the current step. Check if it belongs to another step in the version.
                // If unknown to the entire version AND it's a positive financial action, fail closed.
                if (!isset($knownAliases[$targetId]) && $this->isPositiveFinancialAction($action)) {
                    $unmatchedFinancial[] = $action;
                }
                continue;
            }
            $actionsByField[$canonical][] = $action;
        }

        if (!empty($unmatchedFinancial)) {
            $this->failOnUnmatchedFinancialActions($unmatchedFinancial);
        }


        foreach ($fields as $field) {
            if (!empty($field->fee_code)) {
                $feeVersion = $this->feeEngine->resolveActive($field->fee_code);
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
                    $feeVersion = $this->feeEngine->resolveActive($feeCode);
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

        // Process remaining financial actions that targeted fields not in the current
        // step's visible fields list (cross-step or dynamic fields set by rules).
        // Without this, rule-assigned fees/calculations on fields outside the current
        // step vanish silently → zero total on review/receipt.
        
        // Track all field IDs that have been processed (from visible fields loop above)
        $processedFieldIds = [];
        foreach ($items as $item) {
            $processedFieldIds[$item['field_id']] = true;
        }

        foreach ($actionsByField as $fieldId => $fieldActions) {
            if (isset($processedFieldIds[$fieldId])) {
                continue;
            }

            foreach ($fieldActions as $action) {
                $act = $action['action'] ?? '';
                if ($act === 'set_fee') {
                    $feeAmount = (string) ($action['resolved_amount'] ?? '0');
                    if (bccomp($feeAmount, '0', $this->ctx->scale()) > 0) {
                        $items[] = [
                            'field_id' => $fieldId,
                            'field_name' => $fieldId,
                            'label' => $fieldId,
                            'amount' => $feeAmount,
                            'text_value' => null,
                            'fee_code' => $action['fee_code'] ?? null,
                            'fee_version_id' => $action['fee_version_id'] ?? null,
                            'action' => 'set_fee',
                            'is_insured' => false,
                            'insurance_value' => null,
                        ];
                        $processedFieldIds[$fieldId] = true;
                    }
                } elseif ($act === 'calculate') {
                    $amount = (string) ($action['resolved_amount'] ?? '0');
                    if (bccomp($amount, '0', $this->ctx->scale()) > 0) {
                        $items[] = [
                            'field_id' => $fieldId,
                            'field_name' => $fieldId,
                            'label' => $fieldId,
                            'amount' => $amount,
                            'text_value' => null,
                            'fee_code' => null,
                            'fee_version_id' => null,
                            'action' => 'calculate',
                            'is_insured' => false,
                            'insurance_value' => null,
                        ];
                        $processedFieldIds[$fieldId] = true;
                    }
                } elseif ($act === 'set_value') {
                    // Handle set_value actions on financial fields outside current step
                    $value = (string) ($action['resolved_value'] ?? '');
                    if (is_numeric($value) && bccomp($value, '0', $this->ctx->scale()) > 0) {
                        // Find the field definition to check if it's financial
                        $fieldDef = null;
                        foreach (($allFields ?? []) as $f) {
                            $fId = $f->register_field_id ?? 'custom_'.$f->id;
                            if ($fId === $fieldId || $f->id === $fieldId) {
                                $fieldDef = $f;
                                break;
                            }
                        }
                        // Include if field is financial or has a positive numeric value from rule
                        if ($fieldDef && ($fieldDef->is_financial || !empty($fieldDef->fee_code))) {
                            $items[] = [
                                'field_id' => $fieldId,
                                'field_name' => $fieldDef->name ?? $fieldId,
                                'label' => $fieldDef->label ?? $fieldId,
                                'amount' => $this->normalizeDecimal($value),
                                'text_value' => $value,
                                'fee_code' => $fieldDef->fee_code ?? null,
                                'fee_version_id' => null,
                                'action' => 'set_value',
                                'is_insured' => $fieldDef->is_insured ?? false,
                                'insurance_value' => $fieldDef->insurance_value ?? null,
                            ];
                            $processedFieldIds[$fieldId] = true;
                        }
                    }
                }
            }
        }

        // CRITICAL: Deduplicate items by field_id + fee_code to prevent double-counting.
        // This is a safety net to ensure financial integrity.
        // Allows multiple items for the same field if they have different fee_codes.
        $uniqueItems = [];
        $seenKeys = [];
        foreach ($items as $item) {
            $fieldId = $item['field_id'] ?? null;
            $feeCode = $item['fee_code'] ?? null;
            
            // Create a unique key based on field_id and fee_code
            // If no fee_code, use field_id + amount to allow multiple different amounts
            if ($feeCode) {
                $key = $fieldId . '|' . $feeCode;
            } else {
                $amount = $item['amount'] ?? '0';
                $key = $fieldId . '|' . $amount;
            }
            
            if (!isset($seenKeys[$key])) {
                $uniqueItems[] = $item;
                $seenKeys[$key] = true;
            }
        }

        return $uniqueItems;
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

    /**
     * A set_fee / calculate action that resolved to a strictly positive amount.
     * Used to decide whether an unmatched target is a silent-drop hazard.
     */
    protected function isPositiveFinancialAction(array $action): bool
    {
        $act = $action['action'] ?? '';
        if (!in_array($act, ['set_fee', 'calculate'], true)) {
            return false;
        }
        $amount = (string) ($action['resolved_amount'] ?? '0');
        return is_numeric($amount) && bccomp($amount, '0', $this->ctx->scale()) > 0;
    }

    /**
     * Fail closed: a positive fee/charge targeted a field absent from the step.
     * Surfaces as a 422 FINANCIAL_INTEGRITY_ERROR rather than a silent zero total.
     */
    protected function failOnUnmatchedFinancialActions(array $unmatched): void
    {
        $summary = implode(', ', array_map(
            fn ($a) => ($a['action'] ?? '?').' → '.($a['target_field_id'] ?? '?').' ('.($a['resolved_amount'] ?? '0').')',
            $unmatched
        ));

        \Illuminate\Support\Facades\Log::error('calculateItems: financial action targets a field absent from the step', [
            'unmatched_actions' => $unmatched,
        ]);

        throw new \App\Exceptions\Workflow\FinancialIntegrityException(
            'إجراء مالي يستهدف حقلاً غير موجود في الخطوة الحالية — أُوقف الحفظ لمنع مجموع صفري صامت: '.$summary
        );
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

            $feeVersion = $this->feeEngine->resolveActive($feeCode);

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

    /**
     * Deduplicate calculated items by field_id + fee_code to prevent double-counting.
     * Keeps the first occurrence of each unique combination.
     * Allows multiple items for the same field if they have different fee_codes.
     */
    protected function deduplicateItemsByFieldId(array $items): array
    {
        $uniqueItems = [];
        $seenKeys = [];
        
        foreach ($items as $item) {
            $fieldId = $item['field_id'] ?? null;
            $feeCode = $item['fee_code'] ?? null;
            
            // Create a unique key based on field_id and fee_code
            // If no fee_code, use field_id + amount to allow multiple different amounts
            if ($feeCode) {
                $key = $fieldId . '|' . $feeCode;
            } else {
                $amount = $item['amount'] ?? '0';
                $key = $fieldId . '|' . $amount;
            }
            
            // If not seen before, add it
            if (!isset($seenKeys[$key])) {
                $uniqueItems[] = $item;
                $seenKeys[$key] = true;
            }
            // Skip duplicates
        }
        
        return $uniqueItems;
    }
}

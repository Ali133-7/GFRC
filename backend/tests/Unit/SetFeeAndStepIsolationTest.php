<?php

namespace Tests\Unit;

use App\Exceptions\Workflow\FinancialIntegrityException;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\RegisterField;
use App\Models\ValidationRule;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Services\EnterpriseRuleEngine;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

class SetFeeAndStepIsolationTest extends TestCase
{
    /**
     * Bug A: set_fee uses OfficialFee.amount when no fee version exists (backward compatibility).
     * It must NOT silently return 0 - it should use the parent fee's amount.
     */
    public function test_set_fee_uses_official_fee_amount_when_no_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $category = OfficialFeeCategory::create([
            'name_ar' => 'رسوم تجريبية',
            'code' => 'TEST-CAT-1',
        ]);

        OfficialFee::create([
            'category_id' => $category->id,
            'name_ar' => 'رسوم بدون إصدار',
            'fee_code' => 'S0_N',
            'amount' => 7500,
            'effective_from' => now()->subYear(),
            'is_active' => true,
        ]);

        // Intentionally NO fee_versions created

        $triggerField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'text',
            'label' => 'مفعّل',
            'sort_order' => 1,
        ]);

        $targetField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'number',
            'label' => 'المبلغ',
            'sort_order' => 2,
        ]);

        ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Set Fee Rule',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'c1',
                        'type' => 'simple',
                        'field_id' => $triggerField->id,
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                ],
                'actions' => [
                    ['type' => 'set_fee', 'field_id' => $targetField->id, 'fee_code' => 'S0_N'],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        $service = app(WorkflowExecutionService::class);
        $result = $service->preview($version, [
            'custom_'.$triggerField->id => 'yes',
        ]);

        // Should use OfficialFee.amount (7500) when no FeeVersion exists
        $this->assertEquals('7500.000', $result['modified_values']['custom_'.$targetField->id] ?? null,
            'set_fee should use OfficialFee.amount when no FeeVersion exists');
    }

    /**
     * Bug A: set_fee applies the correct amount when an active fee version exists.
     */
    public function test_set_fee_applies_correct_amount(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $category = OfficialFeeCategory::create([
            'name_ar' => 'رسوم تجريبية',
            'code' => 'TEST-CAT-2',
        ]);

        $officialFee = OfficialFee::create([
            'category_id' => $category->id,
            'name_ar' => 'رسوم الصنف الممتاز',
            'fee_code' => 'S0_N',
            'amount' => 0,
            'effective_from' => now()->subYear(),
            'is_active' => true,
        ]);

        $officialFee->feeVersions()->create([
            'amount' => 150.000,
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addMonth(),
            'version' => 1,
        ]);

        $triggerField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'text',
            'label' => 'مفعّل',
            'sort_order' => 1,
        ]);

        $targetField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'number',
            'label' => 'المبلغ',
            'sort_order' => 2,
        ]);

        ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Set Fee Rule',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'c1',
                        'type' => 'simple',
                        'field_id' => $triggerField->id,
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                ],
                'actions' => [
                    ['type' => 'set_fee', 'field_id' => $targetField->id, 'fee_code' => 'S0_N'],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        $service = app(WorkflowExecutionService::class);
        $result = $service->preview($version, [
            'custom_'.$triggerField->id => 'yes',
        ]);

        $this->assertEquals(1, $result['matched_rules'] ?? 0);
        $this->assertEquals('150.000', $result['modified_values']['custom_'.$targetField->id] ?? null,
            'set_fee should apply the active fee version amount (150.000), not 0');
        $this->assertEquals('150.000', $result['total_amount'] ?? null,
            'Total amount should reflect the fee amount');
    }

    /**
     * Bug B: submitStep must return canonical-only keys in modified_values.
     * No UUID keys should leak into the snapshot.
     */
    public function test_modified_values_are_canonical_only(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step1 = $this->createWorkflowStep($version);

        $field1 = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step1->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'text',
            'label' => 'حقل الخطوة 1',
            'sort_order' => 1,
        ]);

        ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Set Value Rule',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [],
                'actions' => [
                    // Action authored with UUID field_id (as frontend rule builders do)
                    ['type' => 'set_value', 'field_id' => $field1->id, 'value' => 'updated'],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
            'lock_version' => 0,
        ]);
        $execution->refresh();

        $service = app(WorkflowExecutionService::class);
        $result = $service->submitStep($execution, 0, [
            'custom_'.$field1->id => 'initial',
        ]);

        $modified = $result['modified_values'] ?? [];

        // Should contain canonical key only
        $this->assertArrayHasKey('custom_'.$field1->id, $modified,
            'Modified values should contain canonical key');
        $this->assertArrayNotHasKey($field1->id, $modified,
            'Modified values should NOT contain raw UUID key (Bug B)');

        // Verify execution snapshot is also canonical-only
        $execution->refresh();
        $snapshot = $execution->values_snapshot ?? [];
        $this->assertArrayHasKey('custom_'.$field1->id, $snapshot);
        $this->assertArrayNotHasKey($field1->id, $snapshot,
            'Snapshot should NOT contain raw UUID key');
    }
}

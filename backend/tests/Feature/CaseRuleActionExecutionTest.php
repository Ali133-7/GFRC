<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowRule;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

/**
 * Reproduces the reported bug: a case_based rule whose matched case has set_value /
 * set_fee actions did not apply to the target field, because case actions were passed
 * to executeActions WITHOUT the WorkflowRule→enterprise key conversion (action→type,
 * target_field_id→field_id). Only simple-rule actions were converted.
 */
class CaseRuleActionExecutionTest extends TestCase
{
    private function service(): WorkflowExecutionService
    {
        return $this->app->make(WorkflowExecutionService::class);
    }

    /** Create a select trigger field "category" (ممتاز/عادي) on the register. */
    private function categoryField(): RegisterField
    {
        return RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'category',
            'label_ar' => 'الفئة',
            'field_type' => 'select',
            'options' => [
                ['label' => 'ممتاز', 'value' => 'ممتاز'],
                ['label' => 'عادي', 'value' => 'عادي'],
            ],
            'sort_order' => 5,
        ]);
    }

    public function test_case_set_value_action_applies_to_target_field(): void
    {
        $category = $this->categoryField();

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $category, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]); // service_fee

        // Case rule "aaa": SWITCH category — CASE 'ممتاز' → set_value(service_fee = 50000)
        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'aaa',
            'rule_type' => 'case_based',
            'trigger_field_id' => $category->id, // value key = register_field_id
            'cases' => [
                ['value' => 'ممتاز', 'actions' => [
                    ['action' => 'set_value', 'target_field_id' => $this->financialField->id, 'value' => '50000'],
                ], 'priority' => 100],
                ['value' => 'عادي', 'actions' => [
                    ['action' => 'set_value', 'target_field_id' => $this->financialField->id, 'value' => '10000'],
                ], 'priority' => 90],
            ],
            'default_actions' => [],
            'match_mode' => 'exact',
            'condition_logic' => ['operator' => 'and', 'conditions' => []],
            'actions' => [],
            'is_active' => true,
        ]);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [
            $category->id => 'ممتاز',
        ]);

        // The matched case must set the target field's value.
        $this->assertEquals('50000', $result['modified_values'][$this->financialField->id] ?? null,
            'case set_value did not apply to the target field');
        // And because the target is financial, it must surface in the total.
        $this->assertNotEquals('0.000', $result['total_amount']);
    }

    public function test_case_set_fee_action_applies_fee_from_library(): void
    {
        $category = $this->categoryField();

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $category, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        // CASE 'ممتاز' → set_fee(service_fee = GOV-001 → 15.500)
        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'aaa-fee',
            'rule_type' => 'case_based',
            'trigger_field_id' => $category->id,
            'cases' => [
                ['value' => 'ممتاز', 'actions' => [
                    // Mirrors CaseRuleBuilder exactly: it stores BOTH fee_code (the code) and
                    // value (the amount, for display). The engine must use the code, not the amount.
                    ['action' => 'set_fee', 'target_field_id' => $this->financialField->id, 'fee_code' => 'GOV-001', 'value' => 15.5],
                ], 'priority' => 100],
            ],
            'default_actions' => [],
            'match_mode' => 'exact',
            'condition_logic' => ['operator' => 'and', 'conditions' => []],
            'actions' => [],
            'is_active' => true,
        ]);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [
            $category->id => 'ممتاز',
        ]);

        $this->assertEquals('15.500', $result['total_amount'], 'case set_fee did not pull the fee from the library');
        $hasFee = collect($result['calculated_items'])->contains(fn ($i) => ($i['fee_code'] ?? null) === 'GOV-001');
        $this->assertTrue($hasFee, 'set_fee item missing from calculated_items');
    }

    public function test_set_fee_falls_back_to_value_when_fee_code_absent(): void
    {
        // Enterprise-builder convention: the fee CODE is stored in `value`, no `fee_code` key.
        // The engine must still resolve it (fallback), not break.
        $category = $this->categoryField();

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $category, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'aaa-fee-valueonly',
            'rule_type' => 'case_based',
            'trigger_field_id' => $category->id,
            'cases' => [
                ['value' => 'ممتاز', 'actions' => [
                    ['action' => 'set_fee', 'target_field_id' => $this->financialField->id, 'value' => 'GOV-001'],
                ], 'priority' => 100],
            ],
            'default_actions' => [],
            'match_mode' => 'exact',
            'condition_logic' => ['operator' => 'and', 'conditions' => []],
            'actions' => [],
            'is_active' => true,
        ]);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [$category->id => 'ممتاز']);

        $this->assertEquals('15.500', $result['total_amount']);
    }

    public function test_case_default_actions_apply_when_no_case_matches(): void
    {
        $category = $this->categoryField();

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $category, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'aaa-default',
            'rule_type' => 'case_based',
            'trigger_field_id' => $category->id,
            'cases' => [
                ['value' => 'ممتاز', 'actions' => [
                    ['action' => 'set_value', 'target_field_id' => $this->financialField->id, 'value' => '50000'],
                ], 'priority' => 100],
            ],
            'default_actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->financialField->id, 'value' => '1'],
            ],
            'match_mode' => 'exact',
            'condition_logic' => ['operator' => 'and', 'conditions' => []],
            'actions' => [],
            'is_active' => true,
        ]);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [
            $category->id => 'عادي', // no case matches → default actions
        ]);

        $this->assertEquals('1', $result['modified_values'][$this->financialField->id] ?? null,
            'default_actions did not apply when no case matched');
    }
}

<?php

namespace Tests\Feature;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\RegisterField;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\CalculationContext;
use App\Services\EventReplayEngine;
use App\Services\EventStore;
use App\Services\FeeEngine;
use App\Services\RuleEngineV2;
use App\Services\WorkflowExecutionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowEngineValidationTest extends TestCase
{
    protected RegisterField $numberField1;
    protected RegisterField $numberField2;
    protected RegisterField $textField1;
    protected RegisterField $decimalField;
    protected OfficialFee $fee1;
    protected OfficialFee $fee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numberField1 = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount_a',
            'label_ar' => 'المبلغ أ',
            'field_type' => 'number',
            'is_required' => false,
            'is_financial' => true,
            'sort_order' => 3,
        ]);

        $this->numberField2 = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount_b',
            'label_ar' => 'المبلغ ب',
            'field_type' => 'number',
            'is_required' => false,
            'is_financial' => true,
            'sort_order' => 4,
        ]);

        $this->textField1 = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'customer_type',
            'label_ar' => 'نوع العميل',
            'field_type' => 'text',
            'is_required' => false,
            'is_financial' => false,
            'sort_order' => 5,
        ]);

        $this->decimalField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'discount_pct',
            'label_ar' => 'نسبة الخصم',
            'field_type' => 'decimal',
            'is_required' => false,
            'is_financial' => false,
            'sort_order' => 6,
        ]);

        $category = OfficialFeeCategory::create([
            'name_ar' => 'رسوم إضافية',
            'name_en' => 'Additional Fees',
            'code' => 'ADD-CAT',
            'sort_order' => 2,
        ]);

        $this->fee1 = OfficialFee::create([
            'category_id' => $category->id,
            'fee_code' => 'FEE-EXPEDITED',
            'name_ar' => 'رسوم مستعجل',
            'name_en' => 'Expedited Fee',
            'is_active' => true,
        ]);

        FeeVersion::create([
            'fee_id' => $this->fee1->id,
            'amount' => '50.000',
            'version' => 1,
            'effective_from' => now()->subYear(),
        ]);

        $this->fee2 = OfficialFee::create([
            'category_id' => $category->id,
            'fee_code' => 'FEE-PENALTY',
            'name_ar' => 'رسوم غرامة',
            'name_en' => 'Penalty Fee',
            'is_active' => true,
        ]);

        FeeVersion::create([
            'fee_id' => $this->fee2->id,
            'amount' => '25.000',
            'version' => 1,
            'effective_from' => now()->subYear(),
        ]);
    }

    // ============================================================
    // 1. WORKFLOW EXECUTION FLOW VALIDATION
    // ============================================================

    public function test_execution_steps_execute_in_correct_order(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['title_ar' => 'Step 1', 'sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['title_ar' => 'Step 2', 'sort_order' => 2]);
        $step3 = $this->createWorkflowStep($version, ['title_ar' => 'Step 3', 'sort_order' => 3]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id, 'sort_order' => 1]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id, 'sort_order' => 1]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step3->id, 'sort_order' => 1]);

        $execution = $this->startExecution($version);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $execution->refresh();
        $this->assertEquals('in_progress', $execution->status);

        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '200']);
        $execution->refresh();
        $this->assertEquals('in_progress', $execution->status);

        $this->submitStepViaApi($execution->id, 2, [$this->textField1->id => 'VIP']);
        $execution->refresh();

        $this->completeExecutionViaApi($execution->id);
        $execution->refresh();
        $this->assertEquals('completed', $execution->status);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->orderBy('sequence')
            ->get();

        $this->assertEquals('execution_started', $events[0]->event_type);
        $this->assertEquals('step_submitted', $events[1]->event_type);
        $this->assertEquals('step_submitted', $events[2]->event_type);
        $this->assertEquals('step_submitted', $events[3]->event_type);
        $this->assertEquals('execution_completed', $events[4]->event_type);

        for ($i = 1; $i < $events->count(); $i++) {
            $this->assertEquals($events[$i - 1]->sequence + 1, $events[$i]->sequence);
        }
    }

    public function test_execution_state_is_consistent_at_every_step(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $execution = $this->startExecution($version);

        $execution->refresh();
        $this->assertEquals(0, $execution->current_step_index);
        $this->assertEquals('0.000', $execution->total_amount);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '500']);
        $execution->refresh();
        $this->assertEquals(1, $execution->current_step_index);
        $this->assertEquals('500.000', $execution->total_amount);

        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '300']);
        $execution->refresh();
        $this->assertEquals(2, $execution->current_step_index);
        $this->assertEquals('800.000', $execution->total_amount);
    }

    public function test_cannot_submit_step_on_completed_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->completeExecutionViaApi($execution->id);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->numberField1->id => '999'],
        ]);
        $response->assertStatus(422);
    }

    public function test_cannot_submit_step_on_cancelled_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->cancelExecutionViaApi($execution->id, 'Test cancel');

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->numberField1->id => '999'],
        ]);
        $response->assertStatus(422);
    }

    public function test_cannot_complete_completed_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->completeExecutionViaApi($execution->id);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/complete", []);
        $response->assertStatus(422);
    }

    public function test_cannot_cancel_completed_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->completeExecutionViaApi($execution->id);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/cancel", [
            'reason' => 'Should fail',
        ]);
        $response->assertStatus(422);
    }

    // ============================================================
    // 2. RULE ENGINE EXECUTION VALIDATION
    // ============================================================

    public function test_rule_with_equals_condition_triggers_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP Fee Rule',
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->textField1->id,
                'value' => 'VIP',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField1->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->textField1->id => 'VIP',
        ]);

        $calculatedItems = $result['calculated_items'] ?? [];
        $hasExpeditedFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasExpeditedFee, 'VIP rule should have triggered expedited fee');
    }

    public function test_rule_with_equals_condition_does_not_trigger_when_false(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP Fee Rule',
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->textField1->id,
                'value' => 'VIP',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField1->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->textField1->id => 'Regular',
        ]);

        $calculatedItems = $result['calculated_items'] ?? [];
        $hasExpeditedFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasExpeditedFee, 'VIP rule should NOT have triggered for Regular customer');
    }

    public function test_rule_with_gt_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'High Value Penalty',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '1000',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-PENALTY'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1500']);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasPenalty = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');
        $this->assertTrue($hasPenalty, 'Penalty fee should trigger for amount > 1000');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [$this->numberField1->id => '500']);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasPenalty2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');
        $this->assertFalse($hasPenalty2, 'Penalty fee should NOT trigger for amount < 1000');
    }

    public function test_rule_with_lt_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Low Value Discount',
            'condition_logic' => [
                'operator' => 'lt',
                'field_id' => $this->numberField1->id,
                'value' => '100',
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->numberField2->id, 'value' => 'discounted'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '50']);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('discounted', $modifiedValues[$this->numberField2->id] ?? null, 'Low value rule should set discount');
    }

    public function test_rule_with_between_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Mid Range Rule',
            'condition_logic' => [
                'operator' => 'between',
                'field_id' => $this->numberField1->id,
                'value' => ['100', '500'],
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '300']);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee, 'Between rule should trigger for value in range');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [$this->numberField1->id => '50']);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasFee2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee2, 'Between rule should NOT trigger for value below range');

        $execution3 = $this->startExecution($version);
        $result3 = $this->submitStepViaApi($execution3->id, 0, [$this->numberField1->id => '600']);
        $calculatedItems3 = $result3['calculated_items'] ?? [];
        $hasFee3 = collect($calculatedItems3)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee3, 'Between rule should NOT trigger for value above range');
    }

    public function test_rule_with_and_logic(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP High Value',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['operator' => 'gt', 'field_id' => $this->numberField1->id, 'value' => '500'],
                    ['operator' => 'equals', 'field_id' => $this->textField1->id, 'value' => 'VIP'],
                ],
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '1000',
            $this->textField1->id => 'VIP',
        ]);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee, 'AND rule should trigger when all conditions met');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [
            $this->numberField1->id => '1000',
            $this->textField1->id => 'Regular',
        ]);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasFee2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee2, 'AND rule should NOT trigger when one condition fails');
    }

    public function test_rule_with_or_logic(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'High Value OR VIP',
            'condition_logic' => [
                'operator' => 'or',
                'conditions' => [
                    ['operator' => 'gt', 'field_id' => $this->numberField1->id, 'value' => '1000'],
                    ['operator' => 'equals', 'field_id' => $this->textField1->id, 'value' => 'VIP'],
                ],
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '2000',
            $this->textField1->id => 'Regular',
        ]);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee, 'OR rule should trigger when first condition met');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [
            $this->numberField1->id => '100',
            $this->textField1->id => 'VIP',
        ]);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasFee2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee2, 'OR rule should trigger when second condition met');

        $execution3 = $this->startExecution($version);
        $result3 = $this->submitStepViaApi($execution3->id, 0, [
            $this->numberField1->id => '100',
            $this->textField1->id => 'Regular',
        ]);
        $calculatedItems3 = $result3['calculated_items'] ?? [];
        $hasFee3 = collect($calculatedItems3)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee3, 'OR rule should NOT trigger when no conditions met');
    }

    public function test_rule_with_not_equals_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Non-VIP Surcharge',
            'condition_logic' => [
                'operator' => 'not_equals',
                'field_id' => $this->textField1->id,
                'value' => 'VIP',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField1->id, 'fee_code' => 'FEE-PENALTY'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->textField1->id => 'Regular']);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');
        $this->assertTrue($hasFee, 'Not-equals rule should trigger for non-VIP');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [$this->textField1->id => 'VIP']);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasFee2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');
        $this->assertFalse($hasFee2, 'Not-equals rule should NOT trigger for VIP');
    }

    public function test_nested_conditions_evaluate_correctly(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Nested Condition',
            'condition_logic' => [
                'operator' => 'or',
                'conditions' => [
                    [
                        'operator' => 'and',
                        'conditions' => [
                            ['operator' => 'gt', 'field_id' => $this->numberField1->id, 'value' => '100'],
                            ['operator' => 'gt', 'field_id' => $this->numberField2->id, 'value' => '200'],
                        ],
                    ],
                    ['operator' => 'equals', 'field_id' => $this->textField1->id, 'value' => 'VIP'],
                ],
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '150',
            $this->numberField2->id => '300',
            $this->textField1->id => 'Regular',
        ]);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee, 'Nested (AND true) OR false should trigger');

        $execution2 = $this->startExecution($version);
        $result2 = $this->submitStepViaApi($execution2->id, 0, [
            $this->numberField1->id => '50',
            $this->numberField2->id => '100',
            $this->textField1->id => 'VIP',
        ]);
        $calculatedItems2 = $result2['calculated_items'] ?? [];
        $hasFee2 = collect($calculatedItems2)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasFee2, 'Nested (AND false) OR true should trigger');

        $execution3 = $this->startExecution($version);
        $result3 = $this->submitStepViaApi($execution3->id, 0, [
            $this->numberField1->id => '50',
            $this->numberField2->id => '100',
            $this->textField1->id => 'Regular',
        ]);
        $calculatedItems3 = $result3['calculated_items'] ?? [];
        $hasFee3 = collect($calculatedItems3)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee3, 'Nested (AND false) OR false should NOT trigger');
    }

    public function test_inactive_rule_does_not_execute(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Inactive Rule',
            'is_active' => false,
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '999']);
        $calculatedItems = $result['calculated_items'] ?? [];
        $hasFee = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertFalse($hasFee, 'Inactive rule should not execute');
    }

    // ============================================================
    // 3. FIELD AUTO-UPDATE VALIDATION (CRITICAL)
    // ============================================================

    public function test_set_value_action_updates_field_persistently(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Auto Premium',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '100',
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->textField1->id, 'value' => 'Premium'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '500']);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('Premium', $modifiedValues[$this->textField1->id] ?? null, 'set_value should update field');

        $execution->refresh();
        $valuesSnapshot = $execution->values_snapshot ?? [];
        $this->assertEquals('Premium', $valuesSnapshot[$this->textField1->id] ?? null, 'set_value should persist in execution state');
    }

    public function test_updated_values_persist_across_steps(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step2->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP Auto',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '100',
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->textField1->id, 'value' => 'VIP'],
            ],
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP Fee Step2',
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->textField1->id,
                'value' => 'VIP',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);

        $result1 = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '500']);
        $this->assertEquals('VIP', ($result1['modified_values'] ?? [])[$this->textField1->id] ?? null);

        $result2 = $this->submitStepViaApi($execution->id, 1, [$this->textField1->id => 'VIP', $this->numberField2->id => '0']);
        $calculatedItems = $result2['calculated_items'] ?? [];
        $hasExpedited = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertTrue($hasExpedited, 'VIP fee should trigger in step 2 because value persisted from step 1');
    }

    public function test_set_fee_action_applies_correct_fee(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Apply Expedited Fee',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1']);

        $calculatedItems = $result['calculated_items'] ?? [];
        $expeditedItem = collect($calculatedItems)->first(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertNotNull($expeditedItem, 'Expedited fee item should exist');
        $this->assertEquals('50.000', $expeditedItem['amount'] ?? null, 'Expedited fee amount should be 50.000');
    }

    public function test_calculate_action_evaluates_formula(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Calculate 10% surcharge',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'calculate', 'target_field_id' => $this->numberField2->id, 'formula' => '{{' . $this->numberField1->id . '}} * 0.1'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1000']);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('100.000', $modifiedValues[$this->numberField2->id] ?? null, 'Calculate action should evaluate formula correctly');
    }

    public function test_multiple_rules_execute_in_order(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Set High Type',
            'sort_order' => 1,
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '500',
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->textField1->id, 'value' => 'High'],
            ],
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'High Penalty',
            'sort_order' => 2,
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->textField1->id,
                'value' => 'High',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-PENALTY'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1000']);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('High', $modifiedValues[$this->textField1->id] ?? null);

        $calculatedItems = $result['calculated_items'] ?? [];
        $hasPenalty = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');
        $this->assertTrue($hasPenalty, 'Rule 2 should trigger after Rule 1 sets the value');
    }

    public function test_manual_input_does_not_override_rule_set_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Auto Set',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $this->textField1->id, 'value' => 'AutoSet'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '100',
            $this->textField1->id => 'Manual',
        ]);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('AutoSet', $modifiedValues[$this->textField1->id] ?? null, 'Rule set_value should override manual input');
    }

    // ============================================================
    // 4. FEE CALCULATION VALIDATION
    // ============================================================

    public function test_fee_engine_returns_string_not_float(): void
    {
        $feeEngine = app(FeeEngine::class);
        $result = $feeEngine->calculate('100 + 200', []);
        $this->assertIsString($result);
        $this->assertEquals('300.000', $result);
    }

    public function test_fee_engine_bc_math_consistency(): void
    {
        $feeEngine = app(FeeEngine::class);

        $result = $feeEngine->calculate('999999 * 999999', []);
        $this->assertEquals('999998000001.000', $result);

        $result2 = $feeEngine->calculate('10 / 3', []);
        $this->assertEquals('3.333', $result2);

        $result3 = $feeEngine->calculate('(100 + 200) * 0.15', []);
        $this->assertEquals('45.000', $result3);
    }

    public function test_fee_calculation_reflects_rule_outputs(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => '20% Surcharge',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '100',
            ],
            'actions' => [
                ['action' => 'calculate', 'target_field_id' => $this->numberField2->id, 'formula' => '{{' . $this->numberField1->id . '}} * 0.2'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1000']);

        $modifiedValues = $result['modified_values'] ?? [];
        $this->assertEquals('200.000', $modifiedValues[$this->numberField2->id] ?? null);

        $execution->refresh();
        $this->assertEquals('1200.000', $execution->total_amount);
    }

    public function test_fee_snapshot_matches_execution_time_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Apply Fee',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);

        $stepEvent = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->first();

        $feeSnapshot = $stepEvent->fee_snapshot ?? [];
        $this->assertArrayHasKey('FEE-EXPEDITED', $feeSnapshot);
        $this->assertEquals('50.000', $feeSnapshot['FEE-EXPEDITED']['amount']);
    }

    public function test_no_float_inconsistency_in_fee_calculations(): void
    {
        $feeEngine = app(FeeEngine::class);

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $feeEngine->calculate('1234.567 * 8.9', []);
        }

        $unique = array_unique($results);
        $this->assertCount(1, $unique, 'All 10 calculations should produce identical results');
        $this->assertIsString($results[0]);
    }

    // ============================================================
    // 5. STATE TRANSITION VALIDATION
    // ============================================================

    public function test_valid_state_transitions(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->assertEquals('in_progress', $execution->status);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $execution->refresh();
        $this->assertEquals('in_progress', $execution->status);

        $this->completeExecutionViaApi($execution->id);
        $execution->refresh();
        $this->assertEquals('completed', $execution->status);
    }

    public function test_cancel_transition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->assertEquals('in_progress', $execution->status);

        $this->cancelExecutionViaApi($execution->id, 'Test');
        $execution->refresh();
        $this->assertEquals('cancelled', $execution->status);
    }

    public function test_completed_execution_cannot_be_modified(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->completeExecutionViaApi($execution->id);

        $r1 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->numberField1->id => '999'],
        ]);
        $r1->assertStatus(422);

        $r2 = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/complete", []);
        $r2->assertStatus(422);

        $r3 = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/cancel", ['reason' => 'x']);
        $r3->assertStatus(422);
    }

    public function test_cancelled_execution_cannot_be_modified(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->cancelExecutionViaApi($execution->id, 'Test');

        $r1 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->numberField1->id => '999'],
        ]);
        $r1->assertStatus(422);

        $r2 = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/complete", []);
        $r2->assertStatus(422);

        $r3 = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/cancel", ['reason' => 'x']);
        $r3->assertStatus(422);
    }

    // ============================================================
    // 6. EVENT LOG VALIDATION
    // ============================================================

    public function test_every_step_submission_generates_event(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '200']);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(3, $events);
        $this->assertEquals('execution_started', $events[0]->event_type);
        $this->assertEquals('step_submitted', $events[1]->event_type);
        $this->assertEquals('step_submitted', $events[2]->event_type);
    }

    public function test_event_contains_correct_input_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '42']);

        $stepEvent = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->first();

        $payload = $stepEvent->event_payload ?? [];
        $this->assertEquals('42', $payload['values'][$this->numberField1->id] ?? null);
    }

    public function test_event_contains_calculated_items(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Apply Fee',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);

        $stepEvent = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->first();

        $calculatedItems = $stepEvent->calculated_items ?? [];
        $this->assertNotEmpty($calculatedItems);

        $feeItem = collect($calculatedItems)->first(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $this->assertNotNull($feeItem);
        $this->assertEquals('50.000', $feeItem['amount']);
    }

    public function test_event_order_matches_execution_order(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '200']);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->orderBy('sequence')
            ->get();

        $this->assertEquals(0, $events[0]->sequence);
        $this->assertEquals(1, $events[1]->sequence);
        $this->assertEquals(2, $events[2]->sequence);
    }

    public function test_no_missing_or_duplicated_events(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);
        $step3 = $this->createWorkflowStep($version, ['sort_order' => 3]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);
        $this->createWorkflowField($version, $this->textField1, ['step_id' => $step3->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '200']);
        $this->submitStepViaApi($execution->id, 2, [$this->textField1->id => 'test']);
        $this->completeExecutionViaApi($execution->id);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(5, $events);

        $sequences = $events->pluck('sequence')->toArray();
        $this->assertEquals(count($sequences), count(array_unique($sequences)), 'No duplicate event sequences');

        for ($i = 0; $i < count($sequences); $i++) {
            $this->assertEquals($i, $sequences[$i]);
        }
    }

    // ============================================================
    // 7. DATA CONSISTENCY CHECK
    // ============================================================

    public function test_execution_state_matches_event_derived_state(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1000']);
        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '500']);

        $execution->refresh();

        $replayEngine = app(EventReplayEngine::class);
        $replayed = $replayEngine->replayExecution($execution->id);

        $this->assertEquals($execution->status, $replayed['status']);
        $this->assertEquals((string) $execution->current_step_index, (string) $replayed['current_step_index']);

        $ctx = CalculationContext::default();
        $storedTotal = $ctx->normalize((string) $execution->total_amount);
        $replayedTotal = $ctx->normalize($replayed['total_amount']);
        $this->assertEquals($storedTotal, $replayedTotal, 'Total amount from execution must match replayed total');
    }

    public function test_verify_execution_passes(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '1000']);
        $this->submitStepViaApi($execution->id, 1, [$this->numberField2->id => '500']);

        $replayEngine = app(EventReplayEngine::class);
        $verification = $replayEngine->verifyExecution($execution->id);

        $this->assertEquals('PASS', $verification['integrity']);
        $this->assertEmpty($verification['discrepancies']);
    }

    public function test_hash_chain_integrity_passes(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '200']);
        $this->completeExecutionViaApi($execution->id);

        $replayEngine = app(EventReplayEngine::class);
        $chainReport = $replayEngine->verifyExecutionChain($execution->id);

        $this->assertEquals('PASS', $chainReport['chain_integrity']);
        $this->assertEmpty($chainReport['broken_links']);
    }

    // ============================================================
    // 8. EDGE CASE TESTING
    // ============================================================

    public function test_submit_same_step_twice_accumulates(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '200']);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->get();
        $this->assertCount(2, $events);

        $execution->refresh();
        $this->assertEquals('300.000', $execution->total_amount);
    }

    public function test_submit_steps_out_of_order_advances_correctly(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $step1 = $this->createWorkflowStep($version, ['sort_order' => 1]);
        $step2 = $this->createWorkflowStep($version, ['sort_order' => 2]);

        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step2->id]);

        $execution = $this->startExecution($version);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);
        $execution->refresh();
        $this->assertEquals(1, $execution->current_step_index);

        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '50']);
        $execution->refresh();
        $this->assertGreaterThanOrEqual(1, $execution->current_step_index);
    }

    public function test_submit_empty_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '']);

        $execution->refresh();
        $this->assertEquals('0.000', $execution->total_amount);
    }

    public function test_trigger_multiple_rules_simultaneously(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Rule 1',
            'sort_order' => 1,
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Rule 2',
            'sort_order' => 2,
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-PENALTY'],
            ],
        ]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);

        $calculatedItems = $result['calculated_items'] ?? [];
        $hasExpedited = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED');
        $hasPenalty = collect($calculatedItems)->contains(fn($item) => ($item['fee_code'] ?? null) === 'FEE-PENALTY');

        $this->assertTrue($hasExpedited, 'Both rules should trigger');
        $this->assertTrue($hasPenalty, 'Both rules should trigger');

        $execution->refresh();
        $this->assertEquals('175.000', $execution->total_amount);
    }

    public function test_large_numeric_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '999999999999']);

        $execution->refresh();
        $this->assertEquals('999999999999.000', $execution->total_amount);
    }

    public function test_decimal_precision_cases(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '123.456']);

        $execution->refresh();
        $this->assertEquals('123.456', $execution->total_amount);
    }

    public function test_empty_rule_set_executes_without_error(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);
        $result = $this->submitStepViaApi($execution->id, 0, [$this->numberField1->id => '100']);

        $execution->refresh();
        $this->assertEquals('100.000', $execution->total_amount);
        $this->assertEquals('in_progress', $execution->status);
    }

    // ============================================================
    // 9. CONCURRENCY BEHAVIOR
    // ============================================================

    public function test_duplicate_idempotency_key_prevents_duplicate_events(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);

        $r1 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->numberField1->id => '100',
                'idempotency_key' => 'unique-key-123',
            ],
        ]);
        $r1->assertStatus(200);

        $r2 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->numberField1->id => '100',
                'idempotency_key' => 'unique-key-123',
            ],
        ]);
        $r2->assertStatus(200);

        $events = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->get();
        $this->assertCount(1, $events, 'Idempotency key should prevent duplicate step events');
    }

    public function test_no_duplicate_fee_calculations_on_resubmit(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->numberField2, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Apply Fee',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField1->id,
                'value' => '0',
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->numberField2->id, 'fee_code' => 'FEE-EXPEDITED'],
            ],
        ]);

        $execution = $this->startExecution($version);

        $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '100',
            'idempotency_key' => 'fee-test-key',
        ]);

        $this->submitStepViaApi($execution->id, 0, [
            $this->numberField1->id => '100',
            'idempotency_key' => 'fee-test-key',
        ]);

        $stepEvents = WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', 'step_submitted')
            ->get();
        $this->assertCount(1, $stepEvents);

        $event = $stepEvents->first();
        $calculatedItems = $event->calculated_items ?? [];
        $feeCount = collect($calculatedItems)->filter(fn($item) => ($item['fee_code'] ?? null) === 'FEE-EXPEDITED')->count();
        $this->assertEquals(1, $feeCount, 'Fee should not be duplicated');
    }

    public function test_concurrent_executions_do_not_interfere(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->numberField1, ['step_id' => $step->id]);

        $execution1 = $this->startExecution($version);
        $execution2 = $this->startExecution($version);

        $this->submitStepViaApi($execution1->id, 0, [$this->numberField1->id => '100']);
        $this->submitStepViaApi($execution2->id, 0, [$this->numberField1->id => '200']);

        $execution1->refresh();
        $execution2->refresh();

        $this->assertEquals('100.000', $execution1->total_amount);
        $this->assertEquals('200.000', $execution2->total_amount);

        $events1 = WorkflowExecutionEvent::where('execution_id', $execution1->id)->count();
        $events2 = WorkflowExecutionEvent::where('execution_id', $execution2->id)->count();
        $this->assertEquals(2, $events1);
        $this->assertEquals(2, $events2);
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    protected function startExecution(WorkflowVersion $version): WorkflowExecution
    {
        $service = app(WorkflowExecutionService::class);
        return $service->start($version, $this->admin->id, [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);
    }

    protected function submitStepViaApi(string $executionId, int $stepIndex, array $values): array
    {
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$executionId}/step", [
            'step_index' => $stepIndex,
            'values' => $values,
        ]);

        $response->assertStatus(200);
        return $response->json('data') ?? [];
    }

    protected function completeExecutionViaApi(string $executionId): void
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$executionId}/complete", [
            'notes' => 'Test completion',
        ]);
        $response->assertStatus(200);
    }

    protected function cancelExecutionViaApi(string $executionId, string $reason): void
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$executionId}/cancel", [
            'reason' => $reason,
        ]);
        $response->assertStatus(200);
    }
}

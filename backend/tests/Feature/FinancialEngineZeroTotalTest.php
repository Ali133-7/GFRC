<?php

namespace Tests\Feature;

use App\Models\ValidationRule;
use App\Models\WorkflowRule;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

/**
 * Phase 9 — empirical proof harness for the "zero total" report.
 *
 * Existing financial tests (CustomFieldCalculationTest) only exercise CUSTOM fields
 * (keyed 'custom_<id>') with set_value. They never drive a set_fee/calculate rule onto a
 * REGISTER-field-backed financial field — the exact path the audit flags as the zero-total
 * suspect (calculateItems keys by register_field_id; effects carry the action's field_id).
 *
 * These tests close that gap. If a total comes back '0.000', the keying bug is confirmed.
 */
class FinancialEngineZeroTotalTest extends TestCase
{
    private function service(): WorkflowExecutionService
    {
        return $this->app->make(WorkflowExecutionService::class);
    }

    public function test_set_fee_rule_on_register_field_produces_nonzero_total(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        // Trigger field (register-backed) + financial field (register-backed).
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        // Rule: when the trigger field is filled, set GOV-001 fee on the financial field.
        // target_field_id is the register_field_id — the convention the passing enable test uses.
        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Apply service fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->financialField->id, 'fee_code' => 'GOV-001'],
            ],
            'is_active' => true,
        ]);

        $version->update(['status' => 'active']);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [
            $this->textField->id => 'anything',
        ]);

        // GOV-001 active version amount is 15.500 (TestCase::createOfficialFee).
        $this->assertNotEmpty($result['calculated_items'], 'calculated_items is empty — fee item dropped');
        $this->assertNotEquals('0.000', $result['total_amount'], 'TOTAL IS ZERO — keying bug confirmed');
        $this->assertEquals('15.500', $result['total_amount']);
    }

    public function test_calculate_rule_on_register_field_produces_nonzero_total(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        // Rule: calculate a fixed amount onto the financial field.
        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Calculate fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'calculate', 'target_field_id' => $this->financialField->id, 'value' => '50 + 25'],
            ],
            'is_active' => true,
        ]);

        $version->update(['status' => 'active']);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [
            $this->textField->id => 'anything',
        ]);

        $this->assertNotEmpty($result['calculated_items'], 'calculated_items is empty — calculated amount dropped');
        $this->assertNotEquals('0.000', $result['total_amount'], 'TOTAL IS ZERO — calculate amount lost');
        $this->assertEquals('75.000', $result['total_amount']);
    }

    /**
     * Regression: a rule authored against the workflow_field PK (instead of register_field_id)
     * previously produced a silent zero. The alias normalization in calculateItems now resolves it.
     */
    public function test_set_fee_targeting_workflow_field_id_is_normalized(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);
        $feeWf = $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Apply service fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                // target the WorkflowField PK, not register_field_id — must still resolve.
                ['action' => 'set_fee', 'target_field_id' => $feeWf->id, 'fee_code' => 'GOV-001'],
            ],
            'is_active' => true,
        ]);

        $version->update(['status' => 'active']);

        $execution = $this->service()->start($version, $this->admin->id);
        $result = $this->service()->submitStep($execution, 0, [$this->textField->id => 'x']);

        $this->assertEquals('15.500', $result['total_amount']);
    }

    /**
     * Fail-closed: a positive fee targeting a field that exists in NO form must throw,
     * never silently drop the amount.
     */
    public function test_positive_fee_targeting_unknown_field_fails_closed(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Orphan fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => (string) \Illuminate\Support\Str::uuid(), 'fee_code' => 'GOV-001'],
            ],
            'is_active' => true,
        ]);

        $version->update(['status' => 'active']);

        $execution = $this->service()->start($version, $this->admin->id);

        $this->expectException(\App\Exceptions\Workflow\FinancialIntegrityException::class);
        $this->service()->submitStep($execution, 0, [$this->textField->id => 'x']);
    }

    /**
     * The step response must surface grand_total and financial_calculation_trace, otherwise
     * the frontend renders zero to the user even when the backend total is correct.
     */
    public function test_step_response_surfaces_grand_total_and_trace(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->financialField, ['step_id' => $step->id]);

        WorkflowRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Apply service fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $this->financialField->id, 'fee_code' => 'GOV-001'],
            ],
            'is_active' => true,
        ]);

        $version->update(['status' => 'active']);
        $execution = $this->service()->start($version, $this->admin->id);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'anything'],
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.grand_total', '15.500');
        $trace = $response->json('data.financial_trace');
        $this->assertNotEmpty($trace);
        $this->assertEquals('fee_resolution', $trace[0]['step']);
        $this->assertEquals('GOV-001', $trace[0]['fee_code']);
        $this->assertEquals('15.500', $trace[0]['result']);
        $this->assertArrayHasKey('snapshot_hash', $response->json('data'));
    }
}

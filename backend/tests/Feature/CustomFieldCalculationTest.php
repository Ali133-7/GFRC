<?php

namespace Tests\Feature;

use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

class CustomFieldCalculationTest extends TestCase
{
    protected WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $workflow = $this->createWorkflow();
        $this->version = $this->createWorkflowVersion($workflow);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_total_with_custom_financial_field()
    {
        $step = $this->createWorkflowStep($this->version);

        $amountField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'amount',
            'custom_label' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $fieldId = 'custom_'.$amountField->id;

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $fieldId => '1000',
        ]);

        $this->assertNotEmpty($result['calculated_items']);
        $item = $result['calculated_items'][0];
        $this->assertEquals('1000.000', $item['amount']);
        $this->assertEquals('1000.000', $result['total_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_rule_fee_on_custom_select_field()
    {
        $step = $this->createWorkflowStep($this->version);

        $paymentField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'payment_method',
            'custom_label' => 'طريقة الدفع',
            'field_type' => 'select',
            'options' => [
                ['label' => 'نقدي', 'value' => 'cash'],
                ['label' => 'POS', 'value' => 'pos'],
            ],
            'is_required' => true,
            'is_visible' => true,
        ]);

        $feeField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'pos_fee',
            'custom_label' => 'رسوم POS',
            'field_type' => 'decimal',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $paymentFieldId = 'custom_'.$paymentField->id;
        $feeFieldId = 'custom_'.$feeField->id;

        WorkflowRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'POS fee',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $paymentFieldId, 'operator' => 'equals', 'value' => 'pos'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $feeFieldId, 'value' => '100'],
            ],
            'is_active' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $paymentFieldId => 'pos',
        ]);

        $this->assertEquals('100', $result['modified_values'][$feeFieldId]);
        $this->assertNotEmpty($result['calculated_items']);
        $this->assertEquals('100.000', $result['total_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_financial_trace()
    {
        $step = $this->createWorkflowStep($this->version);

        $amountField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'amount',
            'custom_label' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $fieldId = 'custom_'.$amountField->id;

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $fieldId => '500',
        ]);

        $this->assertArrayHasKey('financial_trace', $result);
        // No fee/calculate/discount actions were triggered → trace is empty for this simple case
        $this->assertEmpty($result['financial_trace']);
        $this->assertArrayHasKey('snapshot_hash', $result);
        $this->assertNotEmpty($result['snapshot_hash']);
        $this->assertEquals('0.000', $result['discount_applied']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_financial_trace_for_fee_and_discount()
    {
        $step = $this->createWorkflowStep($this->version);

        $amountField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'amount',
            'custom_label' => 'المبلغ',
            'field_type' => 'decimal',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $amountFieldId = 'custom_'.$amountField->id;

        WorkflowRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'Set base fee',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $amountFieldId],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $amountFieldId, 'fee_code' => 'GOV-001'],
            ],
            'is_active' => true,
        ]);

        WorkflowRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'Apply discount',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $amountFieldId],
            'actions' => [
                ['action' => 'apply_discount', 'target_field_id' => $amountFieldId, 'discount_type' => 'percentage', 'discount_value' => '10'],
            ],
            'is_active' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $amountFieldId => '500',
        ]);

        $this->assertArrayHasKey('financial_trace', $result);
        $this->assertCount(2, $result['financial_trace']);

        // First trace: fee_resolution
        $trace0 = $result['financial_trace'][0];
        $this->assertEquals('fee_resolution', $trace0['step']);
        $this->assertEquals($amountFieldId, $trace0['field_id']);
        $this->assertEquals('GOV-001', $trace0['fee_code']);
        $this->assertEquals('15.500', $trace0['result']);

        // Second trace: discount
        $trace1 = $result['financial_trace'][1];
        $this->assertEquals('discount', $trace1['step']);
        $this->assertEquals($amountFieldId, $trace1['field_id']);
        $this->assertEquals('percentage', $trace1['type']);
        $this->assertEquals('10.000', $trace1['value']);
        $this->assertEquals('15.500', $trace1['applied_to']);
        $this->assertEquals('13.950', $trace1['result']);

        $this->assertEquals('1.550', $result['discount_applied']);
        $this->assertArrayHasKey('snapshot_hash', $result);
        $this->assertNotEmpty($result['snapshot_hash']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_combines_direct_input_and_rule_fee()
    {
        $step = $this->createWorkflowStep($this->version);

        $amountField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'amount',
            'custom_label' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $serviceFeeField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'service_fee',
            'custom_label' => 'رسوم الخدمة',
            'field_type' => 'decimal',
            'is_financial' => true,
            'is_visible' => true,
        ]);

        $amountFieldId = 'custom_'.$amountField->id;
        $serviceFeeFieldId = 'custom_'.$serviceFeeField->id;

        WorkflowRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'Add service fee',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $amountFieldId, 'operator' => 'gte', 'value' => '1000'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $serviceFeeFieldId, 'value' => '100'],
            ],
            'is_active' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $amountFieldId => '1000',
        ]);

        $total = $result['total_amount'];
        $this->assertEquals('1100.000', $total, "Total should be 1000 (amount) + 100 (fee) = 1100");
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function schema_builder_returns_correct_field_type_for_custom_fields()
    {
        $selectField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'payment_method',
            'custom_label' => 'طريقة الدفع',
            'field_type' => 'select',
            'options' => [
                ['label' => 'نقدي', 'value' => 'cash'],
                ['label' => 'POS', 'value' => 'pos'],
            ],
        ]);

        $numberField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'amount',
            'custom_label' => 'المبلغ',
            'field_type' => 'number',
        ]);

        $checkboxField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'is_urgent',
            'custom_label' => 'عاجل',
            'field_type' => 'checkbox',
        ]);

        $schemaBuilder = $this->app->make(\App\Services\WorkflowFieldSchemaBuilder::class);
        $schema = $schemaBuilder->buildForVersion(collect([$selectField, $numberField, $checkboxField]));

        $this->assertEquals('select', $schema[0]['field_type']);
        $this->assertCount(2, $schema[0]['options']);
        $this->assertEquals('number', $schema[1]['field_type']);
        $this->assertEquals('checkbox', $schema[2]['field_type']);
    }
}

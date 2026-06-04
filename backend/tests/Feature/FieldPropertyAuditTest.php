<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Services\WorkflowExecutionService;
use App\Services\WorkflowFieldSchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldPropertyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterField $baseField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'audit_field',
            'label_ar' => 'حقل التدقيق',
            'field_type' => 'text',
            'is_required' => false,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_financial' => false,
            'is_insured' => false,
            'insurance_value' => null,
            'priority' => 0,
            'options' => [],
            'validation_rules' => [],
        ]);
    }

    protected function startExecution($version): WorkflowExecution
    {
        $service = app(WorkflowExecutionService::class);
        return $service->start($version, $this->admin->id, [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);
    }

    // ============================================================
    // 1. is_visible PROPERTY AUDIT
    // ============================================================

    public function test_is_visible_ui_hidden_field_excluded_from_schema(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_visible' => false,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);
        $visible = $builder->filterVisible($schema);

        $this->assertCount(1, $schema);
        $this->assertFalse($schema[0]['is_visible']);
        $this->assertCount(0, $visible);
    }

    public function test_is_visible_api_hidden_field_excluded_from_calculations(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $hiddenField = $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_visible' => false,
            'is_financial' => true,
            'fee_code' => 'FEE-BASIC',
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $hiddenField->register_field_id => '999999',
            ],
        ]);

        $response->assertSuccessful();
        $calculatedItems = $response->json('data.calculated_items');
        $this->assertEmpty($calculatedItems);
    }

    public function test_is_visible_rule_engine_can_toggle_visibility(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_visible' => true,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Hide Field',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                ['action' => 'set_visibility', 'target_field_id' => $this->baseField->id, 'value' => false],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertFalse($fieldStates[$this->baseField->id]['is_visible']);
    }

    public function test_is_visible_security_hidden_field_cannot_be_injected(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $hiddenField = $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_visible' => false,
            'is_financial' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$hiddenField->register_field_id => 'injected_value'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertArrayNotHasKey($hiddenField->register_field_id, $modifiedValues);
    }

    // ============================================================
    // 2. is_locked PROPERTY AUDIT
    // ============================================================

    public function test_is_locked_ui_locked_field_not_editable(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->baseField, ['is_locked' => true]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertTrue($resolved['is_locked']);
        $this->assertFalse($resolved['is_editable']);
        $this->assertTrue($resolved['is_readonly']);
    }

    public function test_is_locked_api_locked_field_cannot_be_modified(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_locked' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'hacked'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertArrayNotHasKey($this->baseField->id, $modifiedValues);
    }

    public function test_is_locked_rule_engine_can_lock_field(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_locked' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Lock Field',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                ['action' => 'set_lock', 'target_field_id' => $this->baseField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'lock_me'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->baseField->id]['is_locked']);
        $this->assertFalse($fieldStates[$this->baseField->id]['is_editable']);
    }

    public function test_is_locked_security_locked_field_persists_across_steps(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step1 = $this->createWorkflowStep($version);
        $step2 = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step1->id,
            'is_locked' => true,
        ]);

        $execution = $this->startExecution($version);

        $r1 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'first_value'],
        ]);
        $r1->assertSuccessful();

        $r2 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 1,
            'values' => [$this->baseField->id => 'second_value'],
        ]);
        $r2->assertSuccessful();

        $modifiedValues = $r2->json('data.modified_values');
        $this->assertArrayNotHasKey($this->baseField->id, $modifiedValues);
    }

    // ============================================================
    // 3. is_editable PROPERTY AUDIT
    // ============================================================

    public function test_is_editable_ui_non_editable_field_is_readonly(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->baseField, [
            'is_editable' => false,
            'is_readonly' => true,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertFalse($resolved['is_editable']);
        $this->assertTrue($resolved['is_readonly']);
    }

    public function test_is_editable_rule_engine_can_toggle_editability(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_editable' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Enable Edit',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                ['action' => 'set_editable', 'target_field_id' => $this->baseField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'enable'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->baseField->id]['is_editable']);
    }

    // ============================================================
    // 4. is_required PROPERTY AUDIT
    // ============================================================

    public function test_is_required_ui_required_field_marked(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->baseField, ['is_required' => true]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertTrue($resolved['is_required']);
    }

    public function test_is_required_rule_engine_can_make_field_required(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'is_required' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Make Required',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                ['action' => 'set_required', 'target_field_id' => $this->baseField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->baseField->id]['is_required']);
    }

    // ============================================================
    // 5. is_financial PROPERTY AUDIT
    // ============================================================

    public function test_is_financial_execution_financial_field_included_in_calculations(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $financialField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'financial_field',
            'label_ar' => 'حقل مالي',
            'field_type' => 'number',
            'is_financial' => true,
        ]);

        $this->createWorkflowField($version, $financialField, [
            'step_id' => $step->id,
            'is_financial' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$financialField->id => '500'],
        ]);

        $response->assertSuccessful();
        $calculatedItems = $response->json('data.calculated_items');
        $this->assertNotEmpty($calculatedItems);
        $this->assertEquals('500.000', $calculatedItems[0]['amount']);
    }

    public function test_is_financial_schema_marks_financial_fields(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $financialField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount',
            'label_ar' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
        ]);

        $wf = $this->createWorkflowField($version, $financialField, ['is_financial' => true]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertTrue($resolved['is_financial']);
        $this->assertTrue($resolved['metadata']['is_financial']);
    }

    // ============================================================
    // 6. is_insured PROPERTY AUDIT
    // ============================================================

    public function test_is_insured_execution_generates_insurance_snapshot(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $insuredField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'insured_amount',
            'label_ar' => 'المبلغ المؤمن',
            'field_type' => 'number',
            'is_financial' => true,
            'is_insured' => true,
            'insurance_value' => '1000.000',
        ]);

        $this->createWorkflowField($version, $insuredField, [
            'step_id' => $step->id,
            'is_insured' => true,
            'insurance_value' => '1000.000',
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$insuredField->id => '750'],
        ]);

        $response->assertSuccessful();
        $snapshots = $response->json('data.insurance_snapshots');
        $this->assertNotEmpty($snapshots);
        $this->assertEquals('750.000', $snapshots[0]['field_value']);
        $this->assertEquals('1000.000', $snapshots[0]['insurance_value']);
    }

    public function test_is_insured_schema_tracks_insurance_metadata(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $insuredField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'insured_val',
            'label_ar' => 'قيمة مؤمنة',
            'field_type' => 'number',
            'is_insured' => true,
            'insurance_value' => '5000.000',
        ]);

        $wf = $this->createWorkflowField($version, $insuredField, [
            'is_insured' => true,
            'insurance_value' => '5000.000',
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertTrue($resolved['is_insured']);
        $this->assertEquals('5000.000', $resolved['insurance_value']);
        $this->assertTrue($resolved['metadata']['is_insured']);
    }

    // ============================================================
    // 7. insurance_value PROPERTY AUDIT
    // ============================================================

    public function test_insurance_value_schema_includes_insurance_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $insuredField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'val',
            'label_ar' => 'قيمة',
            'field_type' => 'number',
            'is_insured' => true,
            'insurance_value' => '2500.500',
        ]);

        $wf = $this->createWorkflowField($version, $insuredField, [
            'is_insured' => true,
            'insurance_value' => '2500.500',
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('2500.500', $resolved['insurance_value']);
    }

    // ============================================================
    // 8. priority PROPERTY AUDIT
    // ============================================================

    public function test_priority_schema_sorts_by_priority(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf1 = $this->createWorkflowField($version, $this->baseField, ['priority' => 10, 'sort_order' => 1]);
        $wf2 = $this->createWorkflowField($version, $this->baseField, ['priority' => 1, 'sort_order' => 2]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);

        $this->assertEquals(10, $schema[0]['priority']);
        $this->assertEquals(1, $schema[1]['priority']);
    }

    public function test_priority_schema_includes_priority_in_metadata(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->baseField, ['priority' => 5]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals(5, $resolved['priority']);
    }

    // ============================================================
    // 9. validation_rules PROPERTY AUDIT
    // ============================================================

    public function test_validation_rules_schema_includes_workflow_override(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->baseField, [
            'validation_rules' => ['required', 'min:5', 'max:100'],
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals(['required', 'min:5', 'max:100'], $resolved['validation_rules']);
    }

    public function test_validation_rules_schema_falls_back_to_base_rules(): void
    {
        $base = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'with_rules',
            'label_ar' => 'بقواعد',
            'field_type' => 'text',
            'validation_rules' => ['required', 'email'],
        ]);

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $base);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals(['required', 'email'], $resolved['validation_rules']);
    }

    // ============================================================
    // 10. options PROPERTY AUDIT
    // ============================================================

    public function test_options_schema_includes_workflow_override(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $selectBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'status',
            'label_ar' => 'الحالة',
            'field_type' => 'select',
            'options' => [
                ['label' => 'Old A', 'value' => 'old_a'],
                ['label' => 'Old B', 'value' => 'old_b'],
            ],
        ]);

        $wf = $this->createWorkflowField($version, $selectBase, [
            'field_type' => 'select',
            'options' => [
                ['label' => 'New A', 'value' => 'new_a'],
                ['label' => 'New B', 'value' => 'new_b'],
            ],
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertCount(2, $resolved['options']);
        $this->assertEquals('new_a', $resolved['options'][0]['value']);
    }

    // ============================================================
    // 11. CUSTOM FIELD (NO RegisterField) AUDIT
    // ============================================================

    public function test_custom_field_schema_works_without_register_field(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'custom_name' => 'custom_notes',
            'custom_label' => 'ملاحظات مخصصة',
            'field_type' => 'text',
            'is_visible' => true,
            'is_editable' => true,
            'is_required' => false,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('custom_notes', $resolved['name']);
        $this->assertEquals('ملاحظات مخصصة', $resolved['label']);
        $this->assertEquals('text', $resolved['field_type']);
        $this->assertTrue($resolved['is_custom']);
        $this->assertStringStartsWith('custom_', $resolved['field_id']);
    }

    public function test_custom_field_execution_accepts_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'custom_name' => 'custom_field',
            'custom_label' => 'حقل مخصص',
            'field_type' => 'text',
            'is_visible' => true,
            'is_editable' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => ['custom_'.$wf->id => 'custom_value'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('custom_value', $modifiedValues['custom_'.$wf->id] ?? null);
    }

    // ============================================================
    // 12. RUNTIME FIELD TYPE TRANSFORMATION AUDIT
    // ============================================================

    public function test_runtime_transformation_set_field_type_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'field_type' => 'text',
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Change to Select',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                ['action' => 'set_field_type', 'target_field_id' => $this->baseField->id, 'value' => 'select'],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertEquals('select', $fieldStates[$this->baseField->id]['field_type']);
    }

    public function test_runtime_transformation_set_options_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Set Options',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->baseField->id],
            'actions' => [
                [
                    'action' => 'set_options',
                    'target_field_id' => $this->baseField->id,
                    'options' => [
                        ['label' => 'Dynamic A', 'value' => 'dyn_a'],
                        ['label' => 'Dynamic B', 'value' => 'dyn_b'],
                    ],
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertCount(2, $fieldStates[$this->baseField->id]['options']);
        $this->assertEquals('dyn_a', $fieldStates[$this->baseField->id]['options'][0]['value']);
    }

    // ============================================================
    // 13. BACKWARD COMPATIBILITY AUDIT
    // ============================================================

    public function test_backward_compatibility_existing_workflow_still_works(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'test_value'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('test_value', $modifiedValues[$this->baseField->id] ?? null);
    }
}

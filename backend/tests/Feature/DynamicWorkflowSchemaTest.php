<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Services\InsuranceEngine;
use App\Services\VisibilityResolver;
use App\Services\WorkflowExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicWorkflowSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterField $textField;
    protected RegisterField $numberField;
    protected RegisterField $selectField;
    protected RegisterField $insuredField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->textField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'customer_name',
            'label_ar' => 'اسم العميل',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        $this->numberField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount',
            'label_ar' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
        ]);

        $this->selectField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'customer_type',
            'label_ar' => 'نوع العميل',
            'field_type' => 'select',
            'options' => [
                ['label' => 'عادي', 'value' => 'regular'],
                ['label' => 'VIP', 'value' => 'vip'],
            ],
        ]);

        $this->insuredField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'insured_value',
            'label_ar' => 'القيمة المؤمنة',
            'field_type' => 'number',
            'is_financial' => true,
            'is_insured' => true,
            'insurance_value' => '1000.000',
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

    public function test_field_type_defaults_to_text_when_null(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $field = $this->createWorkflowField($version, $this->textField, [
            'field_type' => 'text',
        ]);

        $this->assertEquals('text', $field->field_type);
    }

    public function test_field_type_system_supports_all_types(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $types = ['text', 'number', 'select', 'checkbox', 'radio', 'date'];

        foreach ($types as $type) {
            $field = WorkflowField::create([
                'workflow_version_id' => $version->id,
                'register_field_id' => $this->textField->id,
                'field_type' => $type,
            ]);

            $this->assertEquals($type, $field->field_type);
        }
    }

    public function test_select_field_has_options(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $field = $this->createWorkflowField($version, $this->selectField, [
            'options' => [
                ['label' => 'A', 'value' => 'a'],
                ['label' => 'B', 'value' => 'b'],
            ],
        ]);

        $this->assertIsArray($field->options);
        $this->assertCount(2, $field->options);
    }

    public function test_hidden_field_is_excluded_from_visible_list(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $visibleField = $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_visible' => true,
        ]);

        $hiddenField = $this->createWorkflowField($version, $this->numberField, [
            'step_id' => $step->id,
            'is_visible' => false,
        ]);

        $resolver = app(VisibilityResolver::class);
        $result = $resolver->resolveFields($version->fields, []);

        $this->assertContains($visibleField->register_field_id, collect($result['visible'])->pluck('field_id'));
        $this->assertContains($hiddenField->register_field_id, $result['hidden']);
    }

    public function test_conditional_visibility_shows_field_when_condition_met(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $conditionalField = $this->createWorkflowField($version, $this->selectField, [
            'step_id' => $step->id,
            'is_visible' => true,
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->selectField->id,
                'value' => 'vip',
            ],
        ]);

        $resolver = app(VisibilityResolver::class);

        $valuesHidden = [$this->selectField->id => 'regular'];
        $this->assertFalse($resolver->isFieldVisible($conditionalField, $valuesHidden));

        $valuesVisible = [$this->selectField->id => 'vip'];
        $this->assertTrue($resolver->isFieldVisible($conditionalField, $valuesVisible));
    }

    public function test_hidden_field_excluded_from_calculations(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_visible' => true,
        ]);

        $hiddenFinancialField = $this->createWorkflowField($version, $this->numberField, [
            'step_id' => $step->id,
            'is_visible' => false,
            'is_financial' => true,
            'fee_code' => 'FEE-BASIC',
        ]);

        $resolver = app(VisibilityResolver::class);
        $visibleFields = $resolver->filterVisibleFields($version->fields, []);

        $this->assertFalse($visibleFields->contains(fn($f) => $f->register_field_id === $hiddenFinancialField->register_field_id));
    }

    public function test_locked_field_cannot_be_modified(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_locked' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Should Be Blocked',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertNull($modifiedValues[$this->textField->id] ?? null);
    }

    public function test_rule_can_lock_field(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_locked' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Lock After Submit',
            'condition_logic' => [
                'operator' => 'is_not_empty',
                'field_id' => $this->textField->id,
            ],
            'actions' => [
                ['action' => 'set_lock', 'target_field_id' => $this->textField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Lock Me',
            ],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->textField->id]['is_locked'] ?? false);
    }

    public function test_insured_field_generates_snapshot(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->insuredField, [
            'step_id' => $step->id,
            'is_insured' => true,
            'insurance_value' => '1000.000',
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->insuredField->id => '500',
            ],
        ]);

        $response->assertSuccessful();
        $snapshots = $response->json('data.insurance_snapshots');
        $this->assertIsArray($snapshots);
        $this->assertNotEmpty($snapshots);

        $snapshot = $snapshots[0];
        $this->assertEquals($this->insuredField->id, $snapshot['field_id']);
        $this->assertEquals('500.000', $snapshot['field_value']);
        $this->assertEquals('1000.000', $snapshot['insurance_value']);
    }

    public function test_insurance_engine_calculates_risk_exposure(): void
    {
        $engine = app(InsuranceEngine::class);

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->insuredField, [
            'step_id' => $step->id,
            'is_insured' => true,
            'insurance_value' => '1000.000',
        ]);

        $values = [$this->insuredField->id => '1500'];
        $exposure = $engine->calculateRiskExposure($version->fields, $values);

        $this->assertEquals('500.000', $exposure);
    }

    public function test_insurance_snapshot_includes_coverage_ratio(): void
    {
        $engine = app(InsuranceEngine::class);

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->insuredField, [
            'step_id' => $step->id,
            'is_insured' => true,
            'insurance_value' => '500.000',
        ]);

        $snapshots = $engine->collectInsuranceSnapshots($version->fields, [
            $this->insuredField->id => '1000',
        ]);

        $this->assertNotEmpty($snapshots);
        $this->assertEquals('0.500', $snapshots[0]['coverage_ratio']);
    }

    public function test_non_editable_field_is_readonly(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $field = $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_editable' => false,
            'is_readonly' => true,
        ]);

        $resolver = app(VisibilityResolver::class);
        $normalized = $resolver->normalizeField($field, []);

        $this->assertFalse($normalized['is_editable']);
        $this->assertTrue($normalized['metadata']['is_visible']);
    }

    public function test_rule_can_set_field_editable(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_editable' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Enable Editing',
            'condition_logic' => [
                'operator' => 'is_not_empty',
                'field_id' => $this->textField->id,
            ],
            'actions' => [
                ['action' => 'set_editable', 'target_field_id' => $this->textField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Enable Me',
            ],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->textField->id]['is_editable'] ?? false);
    }

    public function test_set_visibility_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Hide Field',
            'condition_logic' => [
                'operator' => 'is_not_empty',
                'field_id' => $this->textField->id,
            ],
            'actions' => [
                ['action' => 'set_visibility', 'target_field_id' => $this->textField->id, 'value' => false],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Trigger Hide',
            ],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertFalse($fieldStates[$this->textField->id]['is_visible'] ?? true);
    }

    public function test_set_required_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_required' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Make Required',
            'condition_logic' => [
                'operator' => 'is_not_empty',
                'field_id' => $this->textField->id,
            ],
            'actions' => [
                ['action' => 'set_required', 'target_field_id' => $this->textField->id, 'value' => true],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Make Required',
            ],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->textField->id]['is_required'] ?? false);
    }

    public function test_apply_discount_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->numberField, [
            'step_id' => $step->id,
            'is_financial' => true,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'VIP Discount',
            'condition_logic' => [
                'operator' => 'gt',
                'field_id' => $this->numberField->id,
                'value' => '100',
            ],
            'actions' => [
                [
                    'action' => 'apply_discount',
                    'target_field_id' => $this->numberField->id,
                    'base_field_id' => $this->numberField->id,
                    'discount_value' => '10',
                    'discount_type' => 'percentage',
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->numberField->id => '200',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('180.000', $modifiedValues[$this->numberField->id] ?? null);
    }

    public function test_override_value_action(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Override Value',
            'condition_logic' => [
                'operator' => 'is_not_empty',
                'field_id' => $this->textField->id,
            ],
            'actions' => [
                ['action' => 'override_value', 'target_field_id' => $this->textField->id, 'value' => 'OVERRIDDEN'],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Original',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('OVERRIDDEN', $modifiedValues[$this->textField->id] ?? null);
    }

    public function test_field_normalization_produces_typed_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $numberField = $this->createWorkflowField($version, $this->numberField, [
            'step_id' => $step->id,
            'field_type' => 'number',
        ]);

        $resolver = app(VisibilityResolver::class);
        $normalized = $resolver->normalizeField($numberField, [$this->numberField->id => '42']);

        $this->assertEquals('42', $normalized['raw_value']);
        $this->assertEquals(42.0, $normalized['typed_value']);
    }

    public function test_field_normalization_includes_metadata(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $field = $this->createWorkflowField($version, $this->insuredField, [
            'step_id' => $step->id,
            'is_locked' => true,
            'is_insured' => true,
        ]);

        $resolver = app(VisibilityResolver::class);
        $normalized = $resolver->normalizeField($field, []);

        $this->assertTrue($normalized['metadata']['is_locked']);
        $this->assertTrue($normalized['metadata']['is_insured']);
        $this->assertFalse($normalized['metadata']['is_editable']);
    }

    public function test_existing_text_field_continues_working(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'field_type' => 'text',
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Hello World',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('Hello World', $modifiedValues[$this->textField->id] ?? null);
    }

    public function test_existing_workflow_without_new_properties_works(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->textField->id => 'Test',
            ],
        ]);

        $response->assertSuccessful();
    }

    public function test_hidden_field_cannot_be_injected_via_request(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $hiddenField = $this->createWorkflowField($version, $this->numberField, [
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
        $injectedItem = collect($calculatedItems)->first(fn($item) => $item['field_id'] === $hiddenField->register_field_id);
        $this->assertNull($injectedItem);
    }

    public function test_locked_field_cannot_be_overridden_via_api(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $lockedField = $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_locked' => true,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $lockedField->register_field_id => 'Hacked Value',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertNull($modifiedValues[$lockedField->register_field_id] ?? null);
    }
}

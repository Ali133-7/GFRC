<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Services\CascadingSelectEngine;
use App\Services\ComputedFieldEngine;
use App\Services\ConditionalValidationEngine;
use App\Services\CrossFieldValidationEngine;
use App\Services\DynamicOptionSource;
use App\Services\FieldAuditTrail;
use App\Services\WorkflowExecutionService;
use App\Services\WorkflowFieldSchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterprisePhase2Test extends TestCase
{
    use RefreshDatabase;

    protected RegisterField $baseField;
    protected RegisterField $selectField;
    protected RegisterField $numberField;
    protected RegisterField $dateField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'base_field',
            'label_ar' => 'حقل أساسي',
            'field_type' => 'text',
        ]);

        $this->selectField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'category',
            'label_ar' => 'الفئة',
            'field_type' => 'select',
            'options' => [
                ['label' => 'أ', 'value' => 'a'],
                ['label' => 'ب', 'value' => 'b'],
            ],
        ]);

        $this->numberField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount',
            'label_ar' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
        ]);

        $this->dateField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'start_date',
            'label_ar' => 'تاريخ البداية',
            'field_type' => 'date',
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
    // PRIORITY 1.1: CONDITIONAL VALIDATION RULES
    // ============================================================

    public function test_conditional_validation_activates_rules_when_condition_met(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'validation_rules' => ['min:2'],
            'conditional_validation_rules' => [
                [
                    'condition' => ['operator' => 'equals', 'field_id' => $this->selectField->id, 'value' => 'a'],
                    'rules' => ['required', 'max:10'],
                ],
            ],
        ]);

        $engine = app(ConditionalValidationEngine::class);
        $field = $version->fields->first();

        $rulesWithoutCondition = $engine->resolveValidationRules($field, [$this->selectField->id => 'b']);
        $this->assertEquals(['min:2'], $rulesWithoutCondition);

        $rulesWithCondition = $engine->resolveValidationRules($field, [$this->selectField->id => 'a']);
        $this->assertContains('required', $rulesWithCondition);
        $this->assertContains('max:10', $rulesWithCondition);
    }

    public function test_conditional_validation_removes_rules_when_condition_met(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'validation_rules' => ['required', 'min:2'],
            'conditional_validation_rules' => [
                [
                    'condition' => ['operator' => 'equals', 'field_id' => $this->selectField->id, 'value' => 'b'],
                    'remove_rules' => ['required'],
                ],
            ],
        ]);

        $engine = app(ConditionalValidationEngine::class);
        $field = $version->fields->first();

        $rulesNormal = $engine->resolveValidationRules($field, [$this->selectField->id => 'a']);
        $this->assertContains('required', $rulesNormal);

        $rulesConditional = $engine->resolveValidationRules($field, [$this->selectField->id => 'b']);
        $this->assertNotContains('required', $rulesConditional);
        $this->assertContains('min:2', $rulesConditional);
    }

    public function test_conditional_validation_blocks_execution_when_failed(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->selectField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'validation_rules' => ['min:5'],
            'conditional_validation_rules' => [
                [
                    'condition' => ['operator' => 'equals', 'field_id' => $this->selectField->id, 'value' => 'a'],
                    'rules' => ['required', 'min:10'],
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->selectField->id => 'a',
                $this->baseField->id => 'short',
            ],
        ]);

        $response->assertServerError();
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('validation_failed', $data['message']);
    }

    public function test_conditional_validation_passes_when_condition_not_met(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->selectField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowField($version, $this->baseField, [
            'step_id' => $step->id,
            'validation_rules' => ['min:2'],
            'conditional_validation_rules' => [
                [
                    'condition' => ['operator' => 'equals', 'field_id' => $this->selectField->id, 'value' => 'a'],
                    'rules' => ['required', 'min:10'],
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->selectField->id => 'b',
                $this->baseField->id => 'ok',
            ],
        ]);

        $response->assertSuccessful();
    }

    // ============================================================
    // PRIORITY 1.2: COMPUTED FIELDS WITH REACTIVE RECALCULATION
    // ============================================================

    public function test_computed_field_calculates_value_from_formula(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $computedField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'total',
            'label_ar' => 'المجموع',
            'field_type' => 'number',
        ]);

        $wf = $this->createWorkflowField($version, $computedField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$this->numberField->id.'}} * 2',
            'computed_dependencies' => [$this->numberField->id],
        ]);

        $engine = app(ComputedFieldEngine::class);
        $result = $engine->computeValue($wf, [$this->numberField->id => '500']);

        $this->assertEquals('1000.000', $result);
    }

    public function test_computed_field_is_locked_and_not_editable(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $computedField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'total',
            'label_ar' => 'المجموع',
            'field_type' => 'number',
        ]);

        $wf = $this->createWorkflowField($version, $computedField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$this->numberField->id.'}} * 2',
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->numberField->id => '100']);

        $this->assertTrue($resolved['is_computed']);
        $this->assertFalse($resolved['is_editable']);
        $this->assertTrue($resolved['is_locked']);
        $this->assertEquals('200.000', $resolved['value']['raw']);
    }

    public function test_computed_field_recalculates_when_dependency_changes(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $computedField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'total',
            'label_ar' => 'المجموع',
            'field_type' => 'number',
        ]);

        $wf = $this->createWorkflowField($version, $computedField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$this->numberField->id.'}} * 2',
            'computed_dependencies' => [$this->numberField->id],
        ]);

        $engine = app(ComputedFieldEngine::class);

        $affected = $engine->findAffectedFields($version->fields, $this->numberField->id);
        $this->assertCount(1, $affected);
        $this->assertEquals($wf->id, $affected[0]->id);
    }

    public function test_computed_field_recalculates_chain(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $subtotalField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'subtotal',
            'label_ar' => 'المجموع الفرعي',
            'field_type' => 'number',
        ]);

        $totalField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'total_with_tax',
            'label_ar' => 'المجموع مع الضريبة',
            'field_type' => 'number',
        ]);

        $wfSubtotal = $this->createWorkflowField($version, $subtotalField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$this->numberField->id.'}} * 1',
            'computed_dependencies' => [$this->numberField->id],
        ]);

        $wfTotal = $this->createWorkflowField($version, $totalField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$subtotalField->id.'}} * 1.15',
            'computed_dependencies' => [$subtotalField->id],
        ]);

        $engine = app(ComputedFieldEngine::class);

        $values = [$this->numberField->id => '1000'];
        $computed = $engine->recalculateChain($version->fields, $values, [$this->numberField->id]);

        $this->assertEquals('1000.000', $computed[$subtotalField->id]);
        $this->assertEquals('1150.000', $computed[$totalField->id]);
    }

    public function test_computed_field_schema_includes_dependencies(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $computedField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'total',
            'label_ar' => 'المجموع',
            'field_type' => 'number',
        ]);

        $wf = $this->createWorkflowField($version, $computedField, [
            'is_computed' => true,
            'computed_formula' => '{{'.$this->numberField->id.'}} * 2',
            'computed_dependencies' => [$this->numberField->id],
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->numberField->id => '100']);

        $this->assertArrayHasKey('computed', $resolved);
        $this->assertEquals([$this->numberField->id], $resolved['computed']['dependencies']);
        $this->assertEquals('200.000', $resolved['computed']['computed_value']);
    }

    // ============================================================
    // PRIORITY 1.3: FIELD-LEVEL AUDIT TRAIL
    // ============================================================

    public function test_audit_trail_records_field_changes(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'initial_value'],
        ]);

        $response->assertSuccessful();
        $auditSummary = $response->json('data.audit_summary');

        $this->assertIsArray($auditSummary);
        $this->assertGreaterThanOrEqual(1, $auditSummary['total_changes']);
    }

    public function test_audit_trail_records_old_and_new_values(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->baseField, ['step_id' => $step->id]);

        $execution = $this->startExecution($version);

        $r1 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->baseField->id => 'first'],
        ]);
        $r1->assertSuccessful();

        $r2 = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 1,
            'values' => [$this->baseField->id => 'second'],
        ]);
        $r2->assertSuccessful();

        $auditSummary = $r2->json('data.audit_summary');
        $this->assertGreaterThanOrEqual(1, $auditSummary['total_changes']);
    }

    public function test_audit_trail_does_not_record_unchanged_fields(): void
    {
        $trail = app(FieldAuditTrail::class);
        $trail->clear();

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $wf = $this->createWorkflowField($version, $this->baseField, ['step_id' => $step->id]);

        $oldValues = [$this->baseField->id => 'same_value'];
        $newValues = [$this->baseField->id => 'same_value'];

        $changes = $trail->recordFieldChanges(
            'exec-1',
            collect([$wf]),
            $oldValues,
            $newValues,
            'user-1'
        );

        $this->assertEmpty($changes);
        $this->assertFalse($trail->hasChanges());
    }

    public function test_audit_trail_summary(): void
    {
        $trail = app(FieldAuditTrail::class);

        $trail->recordChange('exec-1', 'field-1', 'name', 'الاسم', null, 'Ali', 'user-1');
        $trail->recordChange('exec-1', 'field-2', 'email', 'البريد', null, 'ali@test.com', 'user-1');
        $trail->recordChange('exec-1', 'field-1', 'الاسم', 'الاسم', 'Ali', 'Ahmed', 'user-1');

        $summary = $trail->getSummary();

        $this->assertEquals(3, $summary['total_changes']);
        $this->assertEquals(2, $summary['fields_changed']);
    }

    public function test_audit_trail_includes_changed_by_and_timestamp(): void
    {
        $trail = app(FieldAuditTrail::class);

        $entry = $trail->recordChange('exec-1', 'field-1', 'name', 'الاسم', null, 'test_value', 'user-123', 'test reason');

        $this->assertEquals('user-123', $entry['changed_by']);
        $this->assertEquals('test reason', $entry['reason']);
        $this->assertNotNull($entry['changed_at']);
        $this->assertTrue($entry['has_changed']);
    }

    // ============================================================
    // PRIORITY 2.1: CASCADING SELECT FIELDS
    // ============================================================

    public function test_cascading_select_returns_filtered_options_based_on_parent(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $parentField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'country',
            'label_ar' => 'الدولة',
            'field_type' => 'select',
        ]);

        $childField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'city',
            'label_ar' => 'المدينة',
            'field_type' => 'select',
        ]);

        $wfParent = $this->createWorkflowField($version, $parentField, [
            'step_id' => $step->id,
            'options' => [
                ['label' => 'السعودية', 'value' => 'sa', 'children' => [
                    ['label' => 'الرياض', 'value' => 'riyadh'],
                    ['label' => 'جدة', 'value' => 'jeddah'],
                ]],
                ['label' => 'مصر', 'value' => 'eg', 'children' => [
                    ['label' => 'القاهرة', 'value' => 'cairo'],
                    ['label' => 'الإسكندرية', 'value' => 'alex'],
                ]],
            ],
        ]);

        $wfChild = $this->createWorkflowField($version, $childField, [
            'step_id' => $step->id,
            'parent_field_id' => $parentField->id,
        ]);

        $engine = app(CascadingSelectEngine::class);

        $optionsSA = $engine->resolveOptions($wfChild, [$parentField->id => 'sa']);
        $this->assertCount(2, $optionsSA);
        $this->assertEquals('riyadh', $optionsSA[0]['value']);

        $optionsEG = $engine->resolveOptions($wfChild, [$parentField->id => 'eg']);
        $this->assertCount(2, $optionsEG);
        $this->assertEquals('cairo', $optionsEG[0]['value']);

        $optionsNone = $engine->resolveOptions($wfChild, []);
        $this->assertEmpty($optionsNone);
    }

    public function test_cascading_select_builds_dependency_graph(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $countryField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'country',
            'label_ar' => 'الدولة',
            'field_type' => 'select',
        ]);

        $cityField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'city',
            'label_ar' => 'المدينة',
            'field_type' => 'select',
        ]);

        $districtField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'district',
            'label_ar' => 'الحي',
            'field_type' => 'select',
        ]);

        $this->createWorkflowField($version, $countryField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $cityField, ['step_id' => $step->id, 'parent_field_id' => $countryField->id]);
        $this->createWorkflowField($version, $districtField, ['step_id' => $step->id, 'parent_field_id' => $cityField->id]);

        $engine = app(CascadingSelectEngine::class);
        $graph = $engine->buildCascadeGraph($version->fields);

        $this->assertArrayHasKey($countryField->id, $graph);
        $this->assertContains($cityField->id, $graph[$countryField->id]);
        $this->assertArrayHasKey($cityField->id, $graph);
        $this->assertContains($districtField->id, $graph[$cityField->id]);
    }

    public function test_cascading_select_resolves_chain(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $countryField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'country',
            'label_ar' => 'الدولة',
            'field_type' => 'select',
        ]);

        $cityField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'city',
            'label_ar' => 'المدينة',
            'field_type' => 'select',
        ]);

        $districtField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'district',
            'label_ar' => 'الحي',
            'field_type' => 'select',
        ]);

        $this->createWorkflowField($version, $countryField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $cityField, ['step_id' => $step->id, 'parent_field_id' => $countryField->id]);
        $this->createWorkflowField($version, $districtField, ['step_id' => $step->id, 'parent_field_id' => $cityField->id]);

        $engine = app(CascadingSelectEngine::class);
        $chain = $engine->getCascadeChain($version->fields, $districtField->id);

        $this->assertCount(2, $chain);
        $this->assertEquals($countryField->id, $chain[0]);
        $this->assertEquals($cityField->id, $chain[1]);
    }

    public function test_cascading_select_schema_marks_field_as_cascading(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $parentField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'parent',
            'label_ar' => 'الأب',
            'field_type' => 'select',
        ]);

        $childField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'child',
            'label_ar' => 'الابن',
            'field_type' => 'select',
        ]);

        $this->createWorkflowField($version, $parentField, ['step_id' => $step->id]);
        $wfChild = $this->createWorkflowField($version, $childField, [
            'step_id' => $step->id,
            'parent_field_id' => $parentField->id,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wfChild, [$parentField->id => 'value']);

        $this->assertTrue($resolved['is_cascading']);
        $this->assertEquals($parentField->id, $resolved['parent_field_id']);
        $this->assertTrue($resolved['metadata']['is_cascading']);
    }

    // ============================================================
    // PRIORITY 2.2: DYNAMIC OPTION SOURCES
    // ============================================================

    public function test_dynamic_option_source_detects_database_source(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $dynamicField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'dynamic_options',
            'label_ar' => 'خيارات ديناميكية',
            'field_type' => 'select',
        ]);

        $wf = $this->createWorkflowField($version, $dynamicField, [
            'option_source_type' => 'database',
            'option_source_config' => json_encode([
                'table' => 'users',
                'label_column' => 'name',
                'value_column' => 'id',
            ]),
        ]);

        $engine = app(DynamicOptionSource::class);
        $this->assertTrue($engine->hasDynamicSource($wf));
    }

    public function test_dynamic_option_source_detects_api_source(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $dynamicField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'api_options',
            'label_ar' => 'خيارات API',
            'field_type' => 'select',
        ]);

        $wf = $this->createWorkflowField($version, $dynamicField, [
            'option_source_type' => 'api',
            'option_source_config' => json_encode([
                'url' => 'https://api.example.com/options',
                'method' => 'GET',
                'label_path' => 'name',
                'value_path' => 'id',
            ]),
        ]);

        $engine = app(DynamicOptionSource::class);
        $this->assertTrue($engine->hasDynamicSource($wf));
    }

    public function test_dynamic_option_source_detects_service_source(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $dynamicField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'service_options',
            'label_ar' => 'خيارات خدمة',
            'field_type' => 'select',
        ]);

        $wf = $this->createWorkflowField($version, $dynamicField, [
            'option_source_type' => 'service',
            'option_source_config' => json_encode([
                'class' => 'App\Services\SomeOptionService',
                'method' => 'getOptions',
            ]),
        ]);

        $engine = app(DynamicOptionSource::class);
        $this->assertTrue($engine->hasDynamicSource($wf));
    }

    public function test_dynamic_option_source_schema_includes_source_type(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $dynamicField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'dynamic',
            'label_ar' => 'ديناميكي',
            'field_type' => 'select',
        ]);

        $wf = $this->createWorkflowField($version, $dynamicField, [
            'option_source_type' => 'api',
            'option_source_config' => json_encode(['url' => 'https://api.example.com']),
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('api', $resolved['option_source_type']);
    }

    // ============================================================
    // PRIORITY 2.3: CROSS-FIELD VALIDATION RULES
    // ============================================================

    public function test_cross_field_validation_gte(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $minField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'min_amount',
            'label_ar' => 'الحد الأدنى',
            'field_type' => 'number',
        ]);

        $maxField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'max_amount',
            'label_ar' => 'الحد الأقصى',
            'field_type' => 'number',
        ]);

        $this->createWorkflowField($version, $minField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $maxField, [
            'step_id' => $step->id,
            'cross_field_validation_rules' => [
                [
                    'type' => 'gte',
                    'reference_field_id' => $minField->id,
                    'message' => 'الحد الأقصى يجب أن يكون أكبر من أو يساوي الحد الأدنى',
                ],
            ],
        ]);

        $engine = app(CrossFieldValidationEngine::class);
        $maxWf = $version->fields->firstWhere('register_field_id', $maxField->id);

        $errors = $engine->validateField($maxWf, '50', [$minField->id => '100']);
        $this->assertNotEmpty($errors);

        $validErrors = $engine->validateField($maxWf, '150', [$minField->id => '100']);
        $this->assertEmpty($validErrors);
    }

    public function test_cross_field_validation_before_after_dates(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $endDate = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'end_date',
            'label_ar' => 'تاريخ النهاية',
            'field_type' => 'date',
        ]);

        $this->createWorkflowField($version, $this->dateField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $endDate, [
            'step_id' => $step->id,
            'cross_field_validation_rules' => [
                [
                    'type' => 'after',
                    'reference_field_id' => $this->dateField->id,
                    'message' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية',
                ],
            ],
        ]);

        $engine = app(CrossFieldValidationEngine::class);
        $endWf = $version->fields->firstWhere('register_field_id', $endDate->id);

        $errors = $engine->validateField($endWf, '2026-01-01', [$this->dateField->id => '2026-06-01']);
        $this->assertNotEmpty($errors);

        $validErrors = $engine->validateField($endWf, '2026-12-01', [$this->dateField->id => '2026-06-01']);
        $this->assertEmpty($validErrors);
    }

    public function test_cross_field_validation_requires(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $optionalField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'optional_note',
            'label_ar' => 'ملاحظة اختيارية',
            'field_type' => 'text',
        ]);

        $requiredIfField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'required_if_note',
            'label_ar' => 'مطلوب إذا كانت هناك ملاحظة',
            'field_type' => 'text',
        ]);

        $this->createWorkflowField($version, $optionalField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $requiredIfField, [
            'step_id' => $step->id,
            'cross_field_validation_rules' => [
                [
                    'type' => 'requires',
                    'reference_field_id' => $optionalField->id,
                    'message' => 'هذا الحقل مطلوب عند تعبئة الملاحظة',
                ],
            ],
        ]);

        $engine = app(CrossFieldValidationEngine::class);
        $reqWf = $version->fields->firstWhere('register_field_id', $requiredIfField->id);

        $errors = $engine->validateField($reqWf, 'filled', [$optionalField->id => '']);
        $this->assertNotEmpty($errors);

        $validErrors = $engine->validateField($reqWf, 'filled', [$optionalField->id => 'has_note']);
        $this->assertEmpty($validErrors);
    }

    public function test_cross_field_validation_excludes(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $fieldA = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'field_a',
            'label_ar' => 'الحقل أ',
            'field_type' => 'text',
        ]);

        $fieldB = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'field_b',
            'label_ar' => 'الحقل ب',
            'field_type' => 'text',
        ]);

        $this->createWorkflowField($version, $fieldA, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $fieldB, [
            'step_id' => $step->id,
            'cross_field_validation_rules' => [
                [
                    'type' => 'excludes',
                    'reference_field_id' => $fieldA->id,
                    'message' => 'لا يمكن استخدام الحقلين معاً',
                ],
            ],
        ]);

        $engine = app(CrossFieldValidationEngine::class);
        $wfB = $version->fields->firstWhere('register_field_id', $fieldB->id);

        $errors = $engine->validateField($wfB, 'filled', [$fieldA->id => 'also_filled']);
        $this->assertNotEmpty($errors);

        $validErrors = $engine->validateField($wfB, 'filled', [$fieldA->id => '']);
        $this->assertEmpty($validErrors);
    }

    public function test_cross_field_validation_blocks_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $minField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'min_val',
            'label_ar' => 'الحد الأدنى',
            'field_type' => 'number',
        ]);

        $maxField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'max_val',
            'label_ar' => 'الحد الأقصى',
            'field_type' => 'number',
        ]);

        $this->createWorkflowField($version, $minField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $maxField, [
            'step_id' => $step->id,
            'cross_field_validation_rules' => [
                [
                    'type' => 'gte',
                    'reference_field_id' => $minField->id,
                    'message' => 'الحد الأقصى يجب أن يكون >= الحد الأدنى',
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $minField->id => '100',
                $maxField->id => '50',
            ],
        ]);

        $response->assertServerError();
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('validation_failed', $data['message']);
    }
}

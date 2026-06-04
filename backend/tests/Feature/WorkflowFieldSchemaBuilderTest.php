<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowField;
use App\Services\WorkflowFieldSchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowFieldSchemaBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterField $textBase;
    protected RegisterField $selectBase;
    protected RegisterField $multiSelectBase;
    protected RegisterField $checkboxBase;
    protected RegisterField $dateBase;
    protected RegisterField $numberBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->textBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'full_name',
            'label_ar' => 'الاسم الكامل',
            'field_type' => 'text',
            'is_required' => true,
        ]);

        $this->selectBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'customer_type',
            'label_ar' => 'نوع العميل',
            'field_type' => 'select',
            'options' => [
                ['label' => 'عادي', 'value' => 'regular'],
                ['label' => 'VIP', 'value' => 'vip'],
                ['label' => 'مميز', 'value' => 'premium'],
            ],
        ]);

        $this->multiSelectBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'services',
            'label_ar' => 'الخدمات',
            'field_type' => 'multi_select',
            'options' => [
                ['label' => 'خدمة أ', 'value' => 'service_a'],
                ['label' => 'خدمة ب', 'value' => 'service_b'],
                ['label' => 'خدمة ج', 'value' => 'service_c'],
            ],
        ]);

        $this->checkboxBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'agree_terms',
            'label_ar' => 'الموافقة على الشروط',
            'field_type' => 'checkbox',
        ]);

        $this->dateBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'birth_date',
            'label_ar' => 'تاريخ الميلاد',
            'field_type' => 'date',
        ]);

        $this->numberBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'amount',
            'label_ar' => 'المبلغ',
            'field_type' => 'number',
            'is_financial' => true,
        ]);
    }

    // ============================================================
    // 1. SCHEMA BUILDING TESTS
    // ============================================================

    public function test_builds_schema_for_workflow_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textBase, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->selectBase, ['step_id' => $step->id]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);

        $this->assertCount(2, $schema);
        $this->assertEquals('full_name', $schema[0]['name']);
        $this->assertEquals('customer_type', $schema[1]['name']);
    }

    public function test_schema_includes_all_field_types(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $fields = [$this->textBase, $this->selectBase, $this->multiSelectBase, $this->checkboxBase, $this->dateBase, $this->numberBase];
        foreach ($fields as $base) {
            $this->createWorkflowField($version, $base, ['step_id' => $step->id]);
        }

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);

        $types = array_column($schema, 'field_type');
        $this->assertContains('text', $types);
        $this->assertContains('select', $types);
        $this->assertContains('multi_select', $types);
        $this->assertContains('checkbox', $types);
        $this->assertContains('date', $types);
        $this->assertContains('number', $types);
    }

    public function test_schema_returns_valid_field_types_list(): void
    {
        $builder = app(WorkflowFieldSchemaBuilder::class);
        $types = $builder->getValidTypes();

        $this->assertContains('text', $types);
        $this->assertContains('number', $types);
        $this->assertContains('select', $types);
        $this->assertContains('multi_select', $types);
        $this->assertContains('checkbox', $types);
        $this->assertContains('date', $types);
        $this->assertContains('textarea', $types);
        $this->assertContains('decimal', $types);
        $this->assertContains('radio', $types);
        $this->assertContains('datetime', $types);
        $this->assertContains('email', $types);
        $this->assertContains('phone', $types);
        $this->assertContains('url', $types);
        $this->assertCount(13, $types);
    }

    // ============================================================
    // 2. FIELD TYPE OVERRIDE TESTS
    // ============================================================

    public function test_workflow_field_overrides_base_field_type(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'field_type' => 'number',
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('number', $resolved['field_type']);
    }

    public function test_workflow_field_uses_base_type_when_no_override(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->selectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('select', $resolved['field_type']);
    }

    // ============================================================
    // 3. OPTIONS OVERRIDE TESTS
    // ============================================================

    public function test_workflow_field_overrides_base_options(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->selectBase->id,
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

    public function test_workflow_field_uses_base_options_when_no_override(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->selectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertCount(3, $resolved['options']);
        $this->assertEquals('regular', $resolved['options'][0]['value']);
    }

    // ============================================================
    // 4. SELECT VALUE-TO-LABEL MAPPING TESTS
    // ============================================================

    public function test_select_field_maps_value_to_label(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->selectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->selectBase->id => 'vip']);

        $this->assertEquals('vip', $resolved['value']['raw']);
        $this->assertEquals('vip', $resolved['value']['typed']);
        $this->assertEquals('VIP', $resolved['value']['display']);
    }

    public function test_select_field_returns_raw_value_when_no_match(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->selectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->selectBase->id => 'unknown']);

        $this->assertEquals('unknown', $resolved['value']['display']);
    }

    public function test_multi_select_field_maps_values_to_labels(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->multiSelectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [
            $this->multiSelectBase->id => ['service_a', 'service_c'],
        ]);

        $this->assertEquals(['service_a', 'service_c'], $resolved['value']['raw']);
        $this->assertEquals(['خدمة أ', 'خدمة ج'], $resolved['value']['display']);
    }

    public function test_select_field_with_null_value_has_null_display(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->selectBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, []);

        $this->assertNull($resolved['value']['raw']);
        $this->assertNull($resolved['value']['display']);
    }

    // ============================================================
    // 5. VISIBILITY OVERRIDE TESTS
    // ============================================================

    public function test_workflow_field_overrides_base_visibility(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'is_visible' => false,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertFalse($resolved['is_visible']);
    }

    public function test_conditional_visibility_in_schema(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'is_visible' => true,
            'condition_logic' => [
                'operator' => 'equals',
                'field_id' => $this->selectBase->id,
                'value' => 'vip',
            ],
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);

        $hidden = $builder->resolveField($wf, [$this->selectBase->id => 'regular']);
        $this->assertFalse($hidden['is_visible']);

        $visible = $builder->resolveField($wf, [$this->selectBase->id => 'vip']);
        $this->assertTrue($visible['is_visible']);
    }

    // ============================================================
    // 6. LOCK / EDITABLE OVERRIDE TESTS
    // ============================================================

    public function test_workflow_field_overrides_base_lock(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'is_locked' => true,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertTrue($resolved['is_locked']);
        $this->assertFalse($resolved['is_editable']);
    }

    public function test_workflow_field_overrides_base_editable(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'is_editable' => false,
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertFalse($resolved['is_editable']);
    }

    // ============================================================
    // 7. VALIDATION RULES OVERRIDE TESTS
    // ============================================================

    public function test_workflow_field_overrides_base_validation_rules(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->textBase->id,
            'validation_rules' => ['required', 'min:3', 'max:100'],
        ]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals(['required', 'min:3', 'max:100'], $resolved['validation_rules']);
    }

    public function test_workflow_field_uses_base_validation_rules_when_no_override(): void
    {
        $base = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'email_field',
            'label_ar' => 'البريد',
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
    // 8. FILTERING TESTS
    // ============================================================

    public function test_filter_visible_fields(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textBase, ['step_id' => $step->id, 'is_visible' => true]);
        $this->createWorkflowField($version, $this->numberBase, ['step_id' => $step->id, 'is_visible' => false]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);
        $visible = $builder->filterVisible($schema);

        $this->assertCount(1, $visible);
        $this->assertEquals('full_name', $visible[0]['name']);
    }

    public function test_filter_by_step(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step1 = $this->createWorkflowStep($version);
        $step2 = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textBase, ['step_id' => $step1->id]);
        $this->createWorkflowField($version, $this->numberBase, ['step_id' => $step2->id]);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $schema = $builder->buildForVersion($version->fields);

        $step1Fields = $builder->filterByStep($schema, $step1->id);
        $this->assertCount(1, $step1Fields);
        $this->assertEquals('full_name', $step1Fields[0]['name']);

        $step2Fields = $builder->filterByStep($schema, $step2->id);
        $this->assertCount(1, $step2Fields);
        $this->assertEquals('amount', $step2Fields[0]['name']);
    }

    // ============================================================
    // 9. TYPED VALUE PARSING TESTS
    // ============================================================

    public function test_parses_number_typed_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->numberBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->numberBase->id => '42.5']);

        $this->assertEquals('42.5', $resolved['value']['raw']);
        $this->assertEquals(42.5, $resolved['value']['typed']);
    }

    public function test_parses_checkbox_typed_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->checkboxBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolvedTrue = $builder->resolveField($wf, [$this->checkboxBase->id => '1']);
        $resolvedFalse = $builder->resolveField($wf, [$this->checkboxBase->id => '0']);

        $this->assertTrue($resolvedTrue['value']['typed']);
        $this->assertFalse($resolvedFalse['value']['typed']);
    }

    public function test_parses_date_typed_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->dateBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf, [$this->dateBase->id => '2026-06-02']);

        $this->assertEquals('2026-06-02', $resolved['value']['typed']);
    }

    // ============================================================
    // 10. BACKWARD COMPATIBILITY TESTS
    // ============================================================

    public function test_schema_works_without_overrides(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $wf = $this->createWorkflowField($version, $this->textBase);

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('text', $resolved['field_type']);
        $this->assertEquals('full_name', $resolved['name']);
        $this->assertEquals('الاسم الكامل', $resolved['label']);
        $this->assertTrue($resolved['is_required']);
        $this->assertTrue($resolved['is_visible']);
    }

    public function test_schema_handles_missing_register_field_gracefully(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $orphanBase = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'orphan_field',
            'label_ar' => 'حقل يتيم',
            'field_type' => 'text',
        ]);

        $wf = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => $orphanBase->id,
            'field_type' => 'text',
            'label_override' => 'Orphan Field',
        ]);

        $orphanBase->delete();

        $builder = app(WorkflowFieldSchemaBuilder::class);
        $wf->unsetRelation('registerField');
        $resolved = $builder->resolveField($wf);

        $this->assertEquals('text', $resolved['field_type']);
        $this->assertEquals('Orphan Field', $resolved['label']);
        $this->assertEquals('', $resolved['name']);
    }
}

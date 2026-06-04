<?php

namespace Tests\Feature;

use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

class CustomFieldLifecycleTest extends TestCase
{
    protected WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $workflow = $this->createWorkflow();
        $this->version = $this->createWorkflowVersion($workflow);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_custom_select_field_via_api()
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
            'custom_name' => 'payment_method',
            'custom_label' => 'طريقة الدفع',
            'field_type' => 'select',
            'options' => [
                ['label' => 'نقدي', 'value' => 'cash'],
                ['label' => 'POS', 'value' => 'pos'],
                ['label' => 'صك', 'value' => 'cheque'],
            ],
            'is_required' => true,
            'is_visible' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.custom_name', 'payment_method');
        $response->assertJsonPath('data.custom_label', 'طريقة الدفع');
        $response->assertJsonPath('data.field_type', 'select');
        $response->assertJsonPath('data.register_field_id', null);
        $response->assertJsonPath('data.is_required', true);
        $response->assertJsonPath('data.options.0.label', 'نقدي');
        $response->assertJsonPath('data.options.0.value', 'cash');

        $this->assertDatabaseHas('workflow_fields', [
            'custom_name' => 'payment_method',
            'register_field_id' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_custom_text_field()
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
            'custom_name' => 'internal_notes',
            'custom_label' => 'ملاحظات داخلية',
            'field_type' => 'textarea',
            'is_required' => false,
            'is_visible' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.custom_name', 'internal_notes');
        $response->assertJsonPath('data.field_type', 'textarea');
        $response->assertJsonPath('data.register_field_id', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_custom_checkbox_field()
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
            'custom_name' => 'is_urgent',
            'custom_label' => 'عاجل',
            'field_type' => 'checkbox',
            'default_value' => '0',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.custom_name', 'is_urgent');
        $response->assertJsonPath('data.field_type', 'checkbox');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_custom_multi_select_field()
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
            'custom_name' => 'required_documents',
            'custom_label' => 'المستندات المطلوبة',
            'field_type' => 'multi_select',
            'options' => [
                ['label' => 'جواز سفر', 'value' => 'passport'],
                ['label' => 'هوية', 'value' => 'id_card'],
                ['label' => 'رخصة', 'value' => 'license'],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.field_type', 'multi_select');
        $response->assertJsonPath('data.options.0.value', 'passport');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_creation_without_custom_name_or_register_field()
    {
        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
            'custom_label' => 'بدون اسم',
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_custom_field_type_override()
    {
        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'status_field',
            'custom_label' => 'الحالة',
            'field_type' => 'text',
        ]);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields/{$field->id}", [
            'field_type' => 'select',
            'options' => [
                ['label' => 'نشط', 'value' => 'active'],
                ['label' => 'غير نشط', 'value' => 'inactive'],
            ],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('workflow_fields', [
            'id' => $field->id,
            'field_type' => 'select',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_custom_field_options()
    {
        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'payment_method',
            'custom_label' => 'طريقة الدفع',
            'field_type' => 'select',
            'options' => [['label' => 'نقدي', 'value' => 'cash']],
        ]);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields/{$field->id}", [
            'options' => [
                ['label' => 'نقدي', 'value' => 'cash'],
                ['label' => 'POS', 'value' => 'pos'],
                ['label' => 'صك', 'value' => 'cheque'],
            ],
        ]);

        $response->assertSuccessful();
        $field->refresh();
        $this->assertCount(3, $field->options);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_executes_workflow_with_custom_select_field_and_rule()
    {
        $step = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 0,
        ]);

        $paymentField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'payment_method',
            'custom_label' => 'طريقة الدفع',
            'field_type' => 'select',
            'options' => [
                ['label' => 'نقدي', 'value' => 'cash'],
                ['label' => 'POS', 'value' => 'pos'],
                ['label' => 'صك', 'value' => 'cheque'],
            ],
            'is_required' => true,
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

        $paymentFieldId = 'custom_'.$paymentField->id;
        $serviceFeeFieldId = 'custom_'.$serviceFeeField->id;

        WorkflowRule::create([
            'workflow_version_id' => $this->version->id,
            'name' => 'POS service fee',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $paymentFieldId, 'operator' => 'equals', 'value' => 'pos'],
                ],
            ],
            'actions' => [
                [
                    'action' => 'set_value',
                    'target_field_id' => $serviceFeeFieldId,
                    'value' => '5000',
                ],
            ],
            'is_active' => true,
        ]);

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $result = $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $paymentFieldId => 'pos',
        ]);

        $this->assertEquals('5000', $result['modified_values'][$serviceFeeFieldId]);
        $this->assertNotEmpty($result['calculated_items']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_custom_field_changes_in_event_store()
    {
        $step = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 0,
        ]);

        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'test_field',
            'custom_label' => 'حقل اختبار',
            'field_type' => 'text',
            'is_visible' => true,
        ]);

        $fieldId = 'custom_'.$field->id;

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $fieldId => 'test_value',
        ]);

        $events = \App\Models\WorkflowExecutionEvent::where('execution_id', $execution->id)
            ->where('event_type', \App\Models\WorkflowExecutionEvent::STEP_SUBMITTED)
            ->get();

        $this->assertNotEmpty($events);

        $payload = $events->first()->event_payload;
        $this->assertEquals('test_value', $payload['values'][$fieldId]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_replays_execution_state_with_custom_fields()
    {
        $step = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 0,
        ]);

        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'replay_field',
            'custom_label' => 'حقل إعادة التشغيل',
            'field_type' => 'text',
            'is_visible' => true,
        ]);

        $fieldId = 'custom_'.$field->id;

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $this->app->make(WorkflowExecutionService::class)->submitStep($execution, 0, [
            $fieldId => 'replay_test',
        ]);

        $replayService = $this->app->make(WorkflowExecutionService::class);
        $replayedState = $replayService->replayExecutionState($execution->id);

        $this->assertEquals('replay_test', $replayedState['values_snapshot'][$fieldId]);
        $this->assertEquals('in_progress', $replayedState['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_distinguishes_custom_fields_from_register_fields()
    {
        $customField = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'custom_field',
            'custom_label' => 'حقل مخصص',
            'field_type' => 'text',
        ]);

        $this->assertNull($customField->register_field_id);
        $this->assertEquals('custom_field', $customField->name);
        $this->assertEquals('حقل مخصص', $customField->label);
        $this->assertEquals('text', $customField->field_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clones_custom_fields_with_all_properties()
    {
        $step = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 0,
        ]);

        WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'clone_test',
            'custom_label' => 'حقل للاستنساخ',
            'field_type' => 'select',
            'options' => [['label' => 'أ', 'value' => 'a']],
            'is_required' => true,
            'is_visible' => true,
            'is_financial' => false,
            'validation_rules' => ['required'],
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/clone", [
            'change_summary' => 'استنساخ للاختبار',
        ]);

        $response->assertStatus(201);
        $newVersionId = $response->json('data.id');

        $clonedField = WorkflowField::where('workflow_version_id', $newVersionId)->first();
        $this->assertNotNull($clonedField);
        $this->assertNull($clonedField->register_field_id);
        $this->assertEquals('clone_test', $clonedField->custom_name);
        $this->assertEquals('select', $clonedField->field_type);
        $this->assertCount(1, $clonedField->options);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_supports_all_custom_field_types()
    {
        $types = ['text', 'textarea', 'number', 'decimal', 'select', 'multi_select', 'checkbox', 'radio', 'date', 'datetime', 'email', 'phone', 'url'];

        foreach ($types as $type) {
            $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$this->version->workflow_id}/versions/{$this->version->id}/fields", [
                'custom_name' => "field_{$type}",
                'custom_label' => "حقل {$type}",
                'field_type' => $type,
            ]);

            $response->assertStatus(201);
            $response->assertJsonPath('data.field_type', $type);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_custom_field_with_validation_rules()
    {
        $step = WorkflowStep::create([
            'workflow_version_id' => $this->version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 0,
        ]);

        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'step_id' => $step->id,
            'custom_name' => 'validated_field',
            'custom_label' => 'حقل مع تحقق',
            'field_type' => 'text',
            'validation_rules' => ['required', 'min:3'],
            'is_visible' => true,
        ]);

        $fieldId = 'custom_'.$field->id;

        $this->version->update(['status' => 'active']);

        $execution = $this->app->make(WorkflowExecutionService::class)->start($this->version, $this->admin->id);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$fieldId => ''],
        ]);

        $response->assertStatus(500);
        $response->assertSee('validation_failed');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_custom_field_schema_correctly()
    {
        $field = WorkflowField::create([
            'workflow_version_id' => $this->version->id,
            'custom_name' => 'schema_test',
            'custom_label' => 'اختبار المخطط',
            'field_type' => 'select',
            'options' => [
                ['label' => 'خيار 1', 'value' => 'opt1'],
                ['label' => 'خيار 2', 'value' => 'opt2'],
            ],
            'is_required' => true,
            'is_visible' => true,
        ]);

        $schemaBuilder = $this->app->make(\App\Services\WorkflowFieldSchemaBuilder::class);
        $schema = $schemaBuilder->buildForVersion(collect([$field]));

        $this->assertCount(1, $schema);
        $this->assertEquals('custom_'.$field->id, $schema[0]['field_id']);
        $this->assertEquals($field->id, $schema[0]['workflow_field_id']);
        $this->assertEquals('select', $schema[0]['field_type']);
        $this->assertTrue($schema[0]['is_required']);
        $this->assertTrue($schema[0]['is_custom']);
        $this->assertCount(2, $schema[0]['options']);
    }
}

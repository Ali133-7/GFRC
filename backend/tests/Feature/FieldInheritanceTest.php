<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use App\Models\WorkflowField;
use App\Services\FieldInheritanceResolver;
use App\Services\WorkflowFieldSchemaBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_type_inherits_from_register_field(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'test_field',
            'label_ar' => 'حقل تجريبي',
            'field_type' => 'number',
        ]);

        $wfField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'step_id' => $step->id,
            'register_field_id' => $registerField->id,
            'field_type' => '', // empty = inherit
        ]);

        $resolver = app(FieldInheritanceResolver::class);
        $resolved = $resolver->resolveProperty($wfField, $registerField, 'field_type');

        $this->assertEquals('number', $resolved['value']);
        $this->assertEquals('register_field', $resolved['source']);
        $this->assertEquals('number', $wfField->field_type);
    }

    public function test_workflow_override_takes_priority_over_register(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'test_field',
            'label_ar' => 'حقل تجريبي',
            'field_type' => 'number',
        ]);

        $wfField = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'step_id' => $step->id,
            'register_field_id' => $registerField->id,
            'field_type' => 'select', // explicit override
            'options' => [
                ['label' => 'A', 'value' => 'a'],
                ['label' => 'B', 'value' => 'b'],
            ],
        ]);

        $resolver = app(FieldInheritanceResolver::class);
        $resolved = $resolver->resolveProperty($wfField, $registerField, 'field_type');

        $this->assertEquals('select', $resolved['value']);
        $this->assertEquals('workflow_override', $resolved['source']);
        $this->assertEquals('select', $wfField->field_type);
    }

    public function test_null_field_type_logs_warning_and_falls_back_to_text(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        // Simulate a custom field loaded from DB with null field_type
        // (newFromBuilder bypasses the NOT NULL constraint since we don't save)
        $wfField = (new WorkflowField)->newFromBuilder([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'workflow_version_id' => $version->id,
            'step_id' => $step->id,
            'register_field_id' => null,
            'custom_name' => 'orphan_field',
            'field_type' => null,
            'options' => null,
            'validation_rules' => null,
            'is_required' => null,
            'is_visible' => null,
            'is_editable' => null,
            'is_locked' => null,
            'is_financial' => null,
            'is_insured' => null,
            'insurance_value' => null,
            'priority' => null,
            'sort_order' => null,
            'default_value' => null,
            'placeholder' => null,
            'condition_logic' => null,
            'fee_code' => null,
            'calculation_formula' => null,
            'computed_formula' => null,
            'computed_dependencies' => null,
            'parent_field_id' => null,
            'option_source_type' => null,
            'option_source_config' => null,
            'cascade_config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wfField->syncOriginal();

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with('FieldInheritanceResolver: field_type resolved to null', \Mockery::on(function ($context) use ($wfField) {
                return $context['workflow_field_id'] === $wfField->id;
            }));

        $resolver = app(FieldInheritanceResolver::class);
        $resolved = $resolver->resolve($wfField);

        $this->assertEquals('text', $resolved['field_type']['value']);
        $this->assertEquals('system_fallback_forced', $resolved['field_type']['source']);
    }

    public function test_schema_builder_uses_resolver_not_direct_access(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'test_field',
            'label_ar' => 'حقل تجريبي',
            'field_type' => 'number',
        ]);

        WorkflowField::create([
            'workflow_version_id' => $version->id,
            'step_id' => $step->id,
            'register_field_id' => $registerField->id,
            'field_type' => '', // inherit from register
        ]);

        $schemaBuilder = app(WorkflowFieldSchemaBuilder::class);
        $fields = $version->fields;
        $schema = $schemaBuilder->buildForVersion($fields);

        $fieldSchema = collect($schema)->firstWhere('name', 'test_field');
        $this->assertNotNull($fieldSchema);
        $this->assertEquals('number', $fieldSchema['field_type'], 'Schema builder must use Resolver, not raw direct access');
    }
}

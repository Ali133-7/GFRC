<?php

namespace Tests\Unit;

use App\Models\RegisterField;
use App\Models\ValidationRule;
use App\Models\WorkflowField;
use App\Services\EnterpriseRuleEngine;
use App\Services\WorkflowExecutionService;
use Tests\TestCase;

class FieldKeyNormalizationTest extends TestCase
{
    /**
     * normalizeFieldKeys must ensure that when values arrive under a canonical key
     * (register_field_id or custom_<id>) the same value is also readable by the field's UUID.
     * Rule conditions are authored with UUID field_ids, so the engine lookup must succeed.
     */
    public function test_normalize_field_keys_syncs_alias_and_canonical(): void
    {
        $service = app(WorkflowExecutionService::class);
        $method = new \ReflectionMethod($service, 'normalizeFieldKeys');
        $method->setAccessible(true);

        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        // Field with register_field_id (canonical = register_field_id)
        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'select_type',
            'label_ar' => 'نوع العمل',
            'field_type' => 'select',
            'options' => json_encode([
                ['value' => 'premium', 'label_ar' => 'الممتاز'],
                ['value' => 'regular', 'label_ar' => 'العادي'],
            ]),
            'sort_order' => 1,
        ]);
        $field = $this->createWorkflowField($version, $registerField, [
            'step_id' => $step->id,
        ]);

        // Field without register_field_id (canonical = custom_<uuid>)
        $fieldNoReg = WorkflowField::create([
            'workflow_version_id' => $version->id,
            'register_field_id' => null,
            'step_id' => $step->id,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_required' => false,
            'field_type' => 'text',
            'label' => 'ملاحظات',
            'sort_order' => 2,
        ]);

        $fields = WorkflowField::where('workflow_version_id', $version->id)->get();

        // Values submitted with canonical keys only
        $values = [
            $registerField->id => 'premium',           // canonical key for field with register_field
            'custom_' . $fieldNoReg->id => 'note-123', // canonical key for field without register_field
        ];

        $normalized = $method->invoke($service, $values, $fields);

        // For field with register_field_id: both canonical and UUID should resolve
        $this->assertEquals('premium', $normalized[$registerField->id]);
        $this->assertEquals('premium', $normalized[$field->id]);

        // For field without register_field_id: both custom_<id> and UUID should resolve
        $this->assertEquals('note-123', $normalized['custom_' . $fieldNoReg->id]);
        $this->assertEquals('note-123', $normalized[$fieldNoReg->id]);
    }

    /**
     * When a rule condition references a field by UUID, and preview()
     * receives values keyed by canonical (register_field_id), normalizeFieldKeys
     * must translate the canonical key to UUID so the engine can match.
     */
    public function test_preview_with_canonical_key_matches_rule_with_uuid_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'work_type',
            'label_ar' => 'نوع العمل',
            'field_type' => 'select',
            'options' => json_encode([
                ['value' => 'premium', 'label_ar' => 'الممتاز'],
                ['value' => 'regular', 'label_ar' => 'العادي'],
            ]),
            'sort_order' => 1,
        ]);
        $field = $this->createWorkflowField($version, $registerField, [
            'step_id' => $step->id,
        ]);

        ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Select Premium Fee',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'c1',
                        'type' => 'simple',
                        'field_id' => $field->id, // UUID!
                        'operator' => 'equals',
                        'value' => 'premium',
                    ],
                ],
                'actions' => [
                    ['type' => 'set_value', 'field_id' => $this->financialField->id, 'value' => '100'],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        $service = app(WorkflowExecutionService::class);

        // Preview with CANONICAL key — WorkflowExecutionService::preview() normalizes before engine
        $result = $service->preview($version, [
            $registerField->id => 'premium',
        ]);

        $this->assertEquals(1, $result['matched_rules'] ?? 0, 'preview() should report 1 matched rule');
        $this->assertNotEmpty($result['actions'] ?? [], 'Actions should be populated from matched rule');
        $this->assertEquals('100', $result['modified_values'][$this->financialField->id] ?? null,
            'set_value action should have been applied to financial field');
    }

    /**
     * When a rule condition references a field by UUID, and the engine receives
     * values keyed by UUID (from snapshot), the rule must also match.
     */
    public function test_engine_with_uuid_key_matches_rule_with_uuid_condition(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $step = $this->createWorkflowStep($version);

        $registerField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'work_type',
            'label_ar' => 'نوع العمل',
            'field_type' => 'select',
            'options' => json_encode([
                ['value' => 'premium', 'label_ar' => 'الممتاز'],
            ]),
            'sort_order' => 1,
        ]);
        $field = $this->createWorkflowField($version, $registerField, [
            'step_id' => $step->id,
        ]);

        ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Select Premium Fee',
            'validation_type' => 'field_existence_check',
            'category' => 'validation',
            'response_type' => 'error',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'c1',
                        'type' => 'simple',
                        'field_id' => $field->id,
                        'operator' => 'equals',
                        'value' => 'premium',
                    ],
                ],
                'actions' => [
                    ['type' => 'set_value', 'field_id' => $this->financialField->id, 'value' => '100'],
                ],
                'else_actions' => [],
            ],
            'priority' => 100,
            'is_active' => true,
        ]);

        $engine = app(EnterpriseRuleEngine::class);

        // Execute with UUID key — what a snapshot might contain
        $result = $engine->execute(
            $version->id,
            [$field->id => 'premium'],
            ['preview' => true]
        );

        $this->assertGreaterThan(0, $result['total_rules_evaluated'] ?? 0, 'Engine should have evaluated rules');
        $this->assertCount(1, $result['results'] ?? [], 'Expected 1 rule result');

        $ruleResult = $result['results'][0];
        $this->assertEquals('Select Premium Fee', $ruleResult['rule_name']);
        $this->assertTrue($ruleResult['matched'], 'Rule should match when value is UUID key. Trace: ' . json_encode($ruleResult['condition_trace'] ?? [], JSON_UNESCAPED_UNICODE));
    }
}

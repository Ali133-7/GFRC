<?php

namespace Tests\Feature;

use App\Models\Register;
use App\Models\RegisterField;
use App\Models\ValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Models\WorkflowRule;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comprehensive test suite for ALL rule types in the Workflow Engine V2.
 * Tests cover: Simple, Case, Enterprise, Validation, and Routing rules.
 */
class ComprehensiveRuleTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function createWorkflowWithVersion(): array
    {
        $register = Register::create([
            'id' => (string) Str::uuid(),
            'code' => 'TEST-REG-' . Str::random(5),
            'name_ar' => 'سجل اختبار',
            'fiscal_year' => 2026,
            'is_active' => true,
        ]);

        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'code' => 'TEST-WF-' . Str::random(5),
            'name_ar' => 'سير عمل اختبار',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $version = WorkflowVersion::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'version' => 1,
            'status' => 'active',
            'published_at' => now(),
        ]);

        return compact('register', 'workflow', 'version');
    }

    protected function createStep(WorkflowVersion $version, int $sortOrder = 1): WorkflowStep
    {
        return WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => "الخطوة $sortOrder",
            'sort_order' => $sortOrder,
        ]);
    }

    protected function createTextField(WorkflowVersion $version, WorkflowStep $step, string $label, string $name = null): WorkflowField
    {
        $registerFieldId = null;
        if ($name) {
            $registerField = RegisterField::create([
                'id' => (string) Str::uuid(),
                'register_id' => $version->workflow->register_id,
                'name' => $name,
                'label_ar' => $label,
                'field_type' => 'text',
                'is_required' => false,
                'is_visible' => true,
                'is_editable' => true,
                'is_locked' => false,
                'is_financial' => false,
                'is_insured' => false,
                'sort_order' => 1,
            ]);
            $registerFieldId = $registerField->id;
        }

        return WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => $label,
            'is_visible' => true,
            'is_required' => false,
            'is_financial' => false,
            'sort_order' => 1,
        ]);
    }

    protected function createFinancialField(WorkflowVersion $version, WorkflowStep $step, string $label, string $name = null): WorkflowField
    {
        $registerFieldId = null;
        if ($name) {
            $registerField = RegisterField::create([
                'id' => (string) Str::uuid(),
                'register_id' => $version->workflow->register_id,
                'name' => $name,
                'label_ar' => $label,
                'field_type' => 'number',
                'is_required' => false,
                'is_visible' => true,
                'is_editable' => true,
                'is_locked' => false,
                'is_financial' => true,
                'is_insured' => false,
                'sort_order' => 1,
            ]);
            $registerFieldId = $registerField->id;
        }

        return WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => $label,
            'is_visible' => true,
            'is_required' => false,
            'is_financial' => true,
            'sort_order' => 1,
        ]);
    }

    protected function createSelectField(WorkflowVersion $version, WorkflowStep $step, string $label, array $options, string $name = null): WorkflowField
    {
        $registerFieldId = null;
        if ($name) {
            $registerField = RegisterField::create([
                'id' => (string) Str::uuid(),
                'register_id' => $version->workflow->register_id,
                'name' => $name,
                'label_ar' => $label,
                'field_type' => 'select',
                'options' => $options,
                'is_required' => false,
                'is_visible' => true,
                'is_editable' => true,
                'is_locked' => false,
                'is_financial' => false,
                'is_insured' => false,
                'sort_order' => 1,
            ]);
            $registerFieldId = $registerField->id;
        }

        return WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => $label,
            'is_visible' => true,
            'is_required' => false,
            'is_financial' => false,
            'field_type' => 'select',
            'options' => $options,
            'sort_order' => 1,
        ]);
    }

    // ==========================================
    // TEST 1: Simple Rule - set_value action
    // ==========================================

    public function test_simple_rule_set_value(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createTextField($data['version'], $step, 'حقل التشغيل', 'trigger_field');
        $targetField = $this->createTextField($data['version'], $step, 'حقل الهدف', 'target_field');

        // Create simple rule: IF trigger_field = 'yes' THEN set target_field = 'hello'
        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalTargetId = $targetField->register_field_id ?? 'custom_' . $targetField->id;

        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Simple set_value rule',
            'rule_type' => 'simple',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $canonicalTriggerId, 'operator' => 'equals', 'value' => 'yes'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'hello'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Execute
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'yes'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        $this->assertEquals('hello', $responseData['modified_values'][$canonicalTargetId] ?? null,
            'Simple rule set_value should set target field to "hello"');
    }

    // ==========================================
    // TEST 2: Simple Rule - set_fee action
    // ==========================================

    public function test_simple_rule_set_fee(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createTextField($data['version'], $step, 'حقل التشغيل', 'trigger_field');
        $feeField = $this->createFinancialField($data['version'], $step, 'حقل الرسم', 'fee_field');

        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalFeeId = $feeField->register_field_id ?? 'custom_' . $feeField->id;

        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Simple set_fee rule',
            'rule_type' => 'simple',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $canonicalTriggerId, 'operator' => 'equals', 'value' => 'yes'],
                ],
            ],
            'actions' => [
                ['action' => 'set_fee', 'target_field_id' => $canonicalFeeId, 'value' => '5000'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'yes'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        $this->assertEquals('5000.000', $responseData['modified_values'][$canonicalFeeId] ?? null,
            'Simple rule set_fee should set fee field to 5000.000');
    }

    // ==========================================
    // TEST 3: Case Rule - exact match
    // ==========================================

    public function test_case_rule_exact_match(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createSelectField($data['version'], $step, 'نوع الخدمة', [
            ['value' => 'standard', 'label' => 'عادي'],
            ['value' => 'premium', 'label' => 'ممتاز'],
        ], 'service_type');
        $targetField = $this->createTextField($data['version'], $step, 'حقل الهدف', 'target_field');

        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalTargetId = $targetField->register_field_id ?? 'custom_' . $targetField->id;

        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Case rule',
            'rule_type' => 'case_based',
            'trigger_field_id' => $canonicalTriggerId,
            'match_mode' => 'exact',
            'condition_logic' => ['operator' => 'and', 'conditions' => []],
            'actions' => [],
            'cases' => [
                [
                    'value' => 'standard',
                    'actions' => [
                        ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'standard_value'],
                    ],
                    'priority' => 100,
                ],
                [
                    'value' => 'premium',
                    'actions' => [
                        ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'premium_value'],
                    ],
                    'priority' => 200,
                ],
            ],
            'default_actions' => [
                ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'default_value'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Test premium case
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'premium'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        $this->assertEquals('premium_value', $responseData['modified_values'][$canonicalTargetId] ?? null,
            'Case rule should match premium case and set target to "premium_value"');
    }

    // ==========================================
    // TEST 4: Enterprise Rule - with rule_config
    // ==========================================

    public function test_enterprise_rule_with_conditions(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createTextField($data['version'], $step, 'حقل التشغيل', 'trigger_field');
        $targetField = $this->createTextField($data['version'], $step, 'حقل الهدف', 'target_field');

        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalTargetId = $targetField->register_field_id ?? 'custom_' . $targetField->id;

        ValidationRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Enterprise rule',
            'validation_type' => 'field_existence_check',
            'rule_config' => [
                'conditions' => [
                    [
                        'id' => 'c1',
                        'type' => 'simple',
                        'field_id' => $canonicalTriggerId,
                        'operator' => 'equals',
                        'value' => 'enterprise_trigger',
                    ],
                ],
                'actions' => [
                    [
                        'id' => 'a1',
                        'type' => 'set_value',
                        'field_id' => $canonicalTargetId,
                        'value' => 'enterprise_result',
                    ],
                ],
                'else_actions' => [],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'خطأ',
            'priority' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'enterprise_trigger'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        $this->assertEquals('enterprise_result', $responseData['modified_values'][$canonicalTargetId] ?? null,
            'Enterprise rule should set target to "enterprise_result"');
    }

    // ==========================================
    // TEST 5: Validation Rule - duplicate_check
    // ==========================================

    public function test_validation_rule_duplicate_check(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $fileNumberField = $this->createTextField($data['version'], $step, 'رقم الأضبارة', 'file_number');

        $canonicalFileId = $fileNumberField->register_field_id ?? 'custom_' . $fileNumberField->id;

        // Insert a record with file_number = "12345"
        DB::table('records')->insert([
            'id' => (string) Str::uuid(),
            'register_id' => $data['register']->id,
            'data' => json_encode(['file_number' => '12345']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create duplicate check rule
        ValidationRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'منع تكرار رقم الأضبارة',
            'validation_type' => 'duplicate_check',
            'target_register_id' => $data['register']->id,
            'target_fields' => [
                [
                    'workflow_field_id' => $canonicalFileId,
                    'register_field_name' => 'file_number',
                ],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'رقم الأضبارة مكرر!',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Test with duplicate value
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalFileId => '12345'], // Duplicate!
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'VALIDATION_BLOCKED']);
    }

    // ==========================================
    // TEST 6: Routing Rule - field_existence_check
    // ==========================================

    public function test_routing_rule_field_existence_check(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $lookupField = $this->createTextField($data['version'], $step, 'حقل البحث', 'lookup_field');

        $canonicalLookupId = $lookupField->register_field_id ?? 'custom_' . $lookupField->id;

        // Insert a record
        DB::table('records')->insert([
            'id' => (string) Str::uuid(),
            'register_id' => $data['register']->id,
            'data' => json_encode(['lookup_field' => 'existing_value']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create routing rule
        ValidationRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'قاعدة توجيه',
            'validation_type' => 'field_existence_check',
            'target_register_id' => $data['register']->id,
            'trigger_conditions' => [
                ['field_id' => $canonicalLookupId, 'operator' => 'not_empty', 'value' => ''],
            ],
            'lookup_config' => [
                'database_column' => 'lookup_field',
                'lookup_strategy' => 'exact',
            ],
            'route_config' => [
                'on_match' => [
                    'action' => 'warn',
                    'message_ar' => 'تم العثور على سجل سابق',
                ],
                'on_not_found' => [
                    'action' => 'continue_workflow',
                ],
            ],
            'response_type' => 'warning',
            'error_message_ar' => 'تحذير',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalLookupId => 'existing_value'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        // Debug: print response data
        \Log::info('Routing test response', [
            'routing_decisions' => $responseData['routing_decisions'] ?? [],
            'legacy_routing' => $responseData['legacy_routing'] ?? [],
            'enterprise_routing' => $responseData['enterprise_routing'] ?? [],
        ]);
        
        // Should have routing decisions (from legacy_routing or enterprise_routing)
        $routingDecisions = $responseData['routing_decisions'] ?? [];
        $legacyRouting = $responseData['legacy_routing'] ?? [];
        $enterpriseRouting = $responseData['enterprise_routing'] ?? [];
        
        // Check if any routing decision exists
        $allRouting = array_merge($routingDecisions, $legacyRouting, $enterpriseRouting);
        
        $this->assertNotEmpty($allRouting,
            'Routing rule should produce routing decisions when record is found. ' .
            'Response keys: ' . implode(', ', array_keys($responseData)));
    }

    // ==========================================
    // TEST 7: Rule with UUID field_id (backward compatibility)
    // ==========================================

    public function test_rule_with_uuid_field_id(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createTextField($data['version'], $step, 'حقل التشغيل');
        $targetField = $this->createTextField($data['version'], $step, 'حقل الهدف');

        // Use UUID instead of register_field_id
        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Rule with UUID field_id',
            'rule_type' => 'simple',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $triggerField->id, 'operator' => 'equals', 'value' => 'test'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $targetField->id, 'value' => 'uuid_result'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalTargetId = $targetField->register_field_id ?? 'custom_' . $targetField->id;

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'test'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        $this->assertEquals('uuid_result', $responseData['modified_values'][$canonicalTargetId] ?? null,
            'Rule with UUID field_id should still work correctly');
    }

    // ==========================================
    // TEST 8: Multiple rules execution order
    // ==========================================

    public function test_multiple_rules_execution_order(): void
    {
        $data = $this->createWorkflowWithVersion();
        $step = $this->createStep($data['version']);
        $triggerField = $this->createTextField($data['version'], $step, 'حقل التشغيل', 'trigger');
        $targetField = $this->createTextField($data['version'], $step, 'حقل الهدف', 'target');

        $canonicalTriggerId = $triggerField->register_field_id ?? 'custom_' . $triggerField->id;
        $canonicalTargetId = $targetField->register_field_id ?? 'custom_' . $targetField->id;

        // Rule 1: set target to "first"
        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Rule 1',
            'rule_type' => 'simple',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $canonicalTriggerId, 'operator' => 'equals', 'value' => 'trigger'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'first'],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Rule 2: set target to "second" (should override)
        WorkflowRule::create([
            'workflow_version_id' => $data['version']->id,
            'name' => 'Rule 2',
            'rule_type' => 'simple',
            'condition_logic' => [
                'operator' => 'and',
                'conditions' => [
                    ['field_id' => $canonicalTriggerId, 'operator' => 'equals', 'value' => 'trigger'],
                ],
            ],
            'actions' => [
                ['action' => 'set_value', 'target_field_id' => $canonicalTargetId, 'value' => 'second'],
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $data['version']->id,
            'register_id' => $data['register']->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [$canonicalTriggerId => 'trigger'],
            ]);

        $response->assertSuccessful();
        $responseData = $response->json('data');
        
        // Last rule should win (sort_order 2 > 1)
        $this->assertEquals('second', $responseData['modified_values'][$canonicalTargetId] ?? null,
            'Multiple rules should execute in order, last one wins');
    }
}

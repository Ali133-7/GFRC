<?php

namespace Tests\Feature;

use App\Models\RegisterField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Individual action type coverage for Priority 1 actions.
 *
 * These tests verify that every action type declared in
 * EnterpriseRuleEngine::executeActions() produces a fieldEffect
 * and that downstream consumers (buildFieldStates, applySetValueActions)
 * surface the change in the API response.
 */
class ActionTypeCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected RegisterField $sourceField;
    protected RegisterField $targetField;
    protected RegisterField $selectField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'source_value',
            'label_ar' => 'مصدر',
            'field_type' => 'text',
        ]);

        $this->targetField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'target_value',
            'label_ar' => 'هدف',
            'field_type' => 'text',
        ]);

        $this->selectField = RegisterField::create([
            'register_id' => $this->register->id,
            'name' => 'status',
            'label_ar' => 'الحالة',
            'field_type' => 'select',
            'options' => [
                ['label' => 'A', 'value' => 'a'],
                ['label' => 'B', 'value' => 'b'],
            ],
        ]);
    }

    // ============================================================
    // enable / disable
    // ============================================================

    public function test_enable_action_makes_field_visible(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        // Trigger field is visible and drives the rule
        $this->createWorkflowField($version, $this->sourceField, ['step_id' => $step->id]);

        // Target field starts hidden
        $this->createWorkflowField($version, $this->targetField, [
            'step_id' => $step->id,
            'is_visible' => false,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Enable Field',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->sourceField->id],
            'actions' => [
                ['action' => 'enable', 'target_field_id' => $this->targetField->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertTrue($fieldStates[$this->targetField->id]['is_visible'], 'enable action should make field visible');
    }

    public function test_disable_action_hides_field(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, [
            'step_id' => $step->id,
            'is_visible' => true,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Disable Field',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'disable', 'target_field_id' => $this->textField->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertFalse($fieldStates[$this->textField->id]['is_visible'], 'disable action should hide field');
    }

    // ============================================================
    // append_options / remove_options
    // ============================================================

    public function test_append_options_adds_to_existing(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->selectField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Append Options',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->selectField->id],
            'actions' => [
                [
                    'action' => 'append_options',
                    'target_field_id' => $this->selectField->id,
                    'options' => [
                        ['label' => 'C', 'value' => 'c'],
                        ['label' => 'D', 'value' => 'd'],
                    ],
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->selectField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertCount(4, $fieldStates[$this->selectField->id]['options'], 'append_options should add 2 options to existing 2');
        $this->assertEquals('d', $fieldStates[$this->selectField->id]['options'][3]['value']);
    }

    public function test_remove_options_filters_existing(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->selectField, [
            'step_id' => $step->id,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Remove Options',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->selectField->id],
            'actions' => [
                [
                    'action' => 'remove_options',
                    'target_field_id' => $this->selectField->id,
                    'options' => [
                        ['label' => 'A', 'value' => 'a'],
                    ],
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->selectField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $fieldStates = $response->json('data.field_states');
        $this->assertCount(1, $fieldStates[$this->selectField->id]['options'], 'remove_options should leave 1 option');
        $this->assertEquals('b', $fieldStates[$this->selectField->id]['options'][0]['value']);
    }

    // ============================================================
    // clear_value / copy_value
    // ============================================================

    public function test_clear_value_clears_field_value(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Clear Value',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'clear_value', 'target_field_id' => $this->textField->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertArrayHasKey($this->textField->id, $modifiedValues, 'clear_value should affect the target field');
        $this->assertNull($modifiedValues[$this->textField->id], 'clear_value should set value to null');
    }

    public function test_copy_value_copies_between_fields(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->sourceField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->targetField, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Copy Value',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->sourceField->id],
            'actions' => [
                [
                    'action' => 'copy_value',
                    'field_id' => $this->sourceField->id,
                    'target_field_id' => $this->targetField->id,
                ],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->sourceField->id => 'copied_text',
            ],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $this->assertEquals('copied_text', $modifiedValues[$this->targetField->id] ?? null, 'copy_value should copy source to target');
    }

    // ============================================================
    // generate_reference
    // ============================================================

    public function test_generate_reference_creates_receipt_number(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);

        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Generate Reference',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'generate_reference', 'target_field_id' => $this->textField->id],
            ],
        ]);

        $execution = $this->startExecution($version);
        $register = $execution->register;
        $beforeSequence = $register->current_sequence;

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $modifiedValues = $response->json('data.modified_values');
        $reference = $modifiedValues[$this->textField->id] ?? null;

        $this->assertNotNull($reference, 'generate_reference should produce a reference number');
        $this->assertStringStartsWith($register->code, $reference);
        $this->assertStringContainsString((string) $register->fiscal_year, $reference);

        // Sequence should have been consumed
        $register->refresh();
        $this->assertEquals($beforeSequence + 1, $register->current_sequence);

        // Reference should be stored in execution snapshot for reuse
        $execution->refresh();
        $this->assertEquals($reference, $execution->values_snapshot['__generated_reference__'] ?? null);
    }

    // ============================================================
    // pause_execution + resume_execution
    // ============================================================

    public function test_pause_execution_blocks_step_submission(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        $this->createWorkflowRule($version, [
            'name' => 'Pause',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'pause_execution'],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
        $execution->refresh();
        $this->assertEquals('paused', $execution->status);

        // Step submission should now be blocked
        $blocked = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'again'],
        ]);
        $blocked->assertStatus(422);
    }

    public function test_resume_execution_allows_step_submission(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // First rule pauses
        $this->createWorkflowRule($version, [
            'name' => 'Pause',
            'condition_logic' => ['operator' => 'equals', 'field_id' => $this->textField->id, 'value' => 'pause'],
            'actions' => [
                ['action' => 'pause_execution'],
            ],
        ]);

        // Second rule resumes
        $this->createWorkflowRule($version, [
            'name' => 'Resume',
            'condition_logic' => ['operator' => 'equals', 'field_id' => $this->textField->id, 'value' => 'resume'],
            'actions' => [
                ['action' => 'resume_execution'],
            ],
        ]);

        $execution = $this->startExecution($version);

        // Pause the execution
        $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'pause'],
        ])->assertSuccessful();

        $execution->refresh();
        $this->assertEquals('paused', $execution->status);

        // Resume the execution
        $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'resume'],
        ])->assertSuccessful();

        $execution->refresh();
        $this->assertEquals('in_progress', $execution->status);

        // Step submission should work again
        $ok = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'normal'],
        ]);
        $ok->assertSuccessful();
    }

    // ============================================================
    // execute_validation
    // ============================================================

    public function test_execute_validation_error_blocks_submission(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Create a validation rule that ALWAYS fails (sql returning 1, condition expects 0 → not met)
        // rule_config must be non-null so legacy ValidationEngine skips it (we test execute_validation action)
        $validationRule = \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Always Fail',
            'validation_type' => 'sql',
            'sql_query' => 'SELECT 1 as fail_count',
            'sql_condition' => 'fail_count = 0',
            'response_type' => 'error',
            'error_message_ar' => 'فشل التحقق الديناميكي',
            'error_message_en' => 'Dynamic validation failed',
            'rule_config' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Run Validation',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'execute_validation', 'target_field_id' => $this->textField->id, 'validation_rule_id' => $validationRule->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('execute_validation', $blocks[0]['action']);
        $this->assertEquals('failed', $blocks[0]['result']);
        $this->assertEquals('error', $blocks[0]['response_type']);
        $this->assertEquals('فشل التحقق الديناميكي', $blocks[0]['message_ar']);
    }

    public function test_execute_validation_warning_does_not_block(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Create a validation rule that ALWAYS fails but with warning response
        $validationRule = \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Always Fail Warning',
            'validation_type' => 'sql',
            'sql_query' => 'SELECT 1 as fail_count',
            'sql_condition' => 'fail_count = 0',
            'response_type' => 'warning',
            'error_message_ar' => 'تحذير: قيمة مكررة',
            'error_message_en' => 'Warning: duplicate value',
            'rule_config' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Run Validation Warning',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'execute_validation', 'target_field_id' => $this->textField->id, 'validation_rule_id' => $validationRule->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertSuccessful();
    }

    // ============================================================
    // Unified validation path — legacy + enterprise blocks merged
    // ============================================================

    public function test_legacy_validation_rule_blocks_submission(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Create a LEGACY validation rule (rule_config = null) that always fails
        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Legacy Blocker',
            'validation_type' => 'sql',
            'sql_query' => 'SELECT 1 as fail_count',
            'sql_condition' => 'fail_count = 0',
            'response_type' => 'error',
            'error_message_ar' => 'فشل التحقق القديم',
            'error_message_en' => 'Legacy validation failed',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertCount(1, $blocks);
        $this->assertEquals('legacy_validation', $blocks[0]['action']);
        $this->assertEquals('failed', $blocks[0]['result']);
        $this->assertEquals('error', $blocks[0]['response_type']);
        $this->assertEquals('فشل التحقق القديم', $blocks[0]['message_ar']);
    }

    public function test_both_legacy_and_enterprise_blocks_merged(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Legacy rule that fails
        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Legacy Blocker',
            'validation_type' => 'sql',
            'sql_query' => 'SELECT 1 as fail_count',
            'sql_condition' => 'fail_count = 0',
            'response_type' => 'error',
            'error_message_ar' => 'فشل التحقق القديم',
            'error_message_en' => 'Legacy validation failed',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Enterprise rule that also fails
        $enterpriseRule = \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Enterprise Blocker',
            'validation_type' => 'sql',
            'sql_query' => 'SELECT 1 as fail_count',
            'sql_condition' => 'fail_count = 0',
            'response_type' => 'error',
            'error_message_ar' => 'فشل التحقق المؤسسي',
            'error_message_en' => 'Enterprise validation failed',
            'rule_config' => [],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->createWorkflowRule($version, [
            'name' => 'Run Enterprise Validation',
            'condition_logic' => ['operator' => 'is_not_empty', 'field_id' => $this->textField->id],
            'actions' => [
                ['action' => 'execute_validation', 'target_field_id' => $this->textField->id, 'validation_rule_id' => $enterpriseRule->id],
            ],
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'trigger'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertCount(2, $blocks);

        // Legacy block should be first (runs before enterprise)
        $this->assertEquals('legacy_validation', $blocks[0]['action']);
        $this->assertEquals('فشل التحقق القديم', $blocks[0]['message_ar']);

        // Enterprise block should be second
        $this->assertEquals('execute_validation', $blocks[1]['action']);
        $this->assertEquals('فشل التحقق المؤسسي', $blocks[1]['message_ar']);
    }

    // ============================================================
    // not_exists
    // ============================================================

    public function test_not_exists_passes_when_no_record_found(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // not_exists: value must NOT exist in records
        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Not Exists Check',
            'validation_type' => 'not_exists',
            'target_register_id' => $this->register->id,
            'target_fields' => [
                ['workflow_field_id' => $this->textField->id, 'register_field_name' => 'customer_name'],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'القيمة مسجلة مسبقاً',
            'error_message_en' => 'Value already exists',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = $this->startExecution($version);

        // No record with 'new_value' exists → should pass
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'new_value'],
        ]);

        $response->assertSuccessful();
    }

    public function test_not_exists_fails_when_record_exists(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Insert a record with 'duplicate_value'
        \Illuminate\Support\Facades\DB::table('records')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $this->register->id,
            'record_number' => 'REC-001',
            'data' => json_encode(['customer_name' => 'duplicate_value']),
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Not Exists Check',
            'validation_type' => 'not_exists',
            'target_register_id' => $this->register->id,
            'target_fields' => [
                ['workflow_field_id' => $this->textField->id, 'register_field_name' => 'customer_name'],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'القيمة مسجلة مسبقاً',
            'error_message_en' => 'Value already exists',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = $this->startExecution($version);

        // Record with 'duplicate_value' exists → should fail
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'duplicate_value'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('not_exists', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
    }

    public function test_not_exists_warning_does_not_block(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Insert a record
        \Illuminate\Support\Facades\DB::table('records')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $this->register->id,
            'record_number' => 'REC-002',
            'data' => json_encode(['customer_name' => 'existing_value']),
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Not Exists Warning',
            'validation_type' => 'not_exists',
            'target_register_id' => $this->register->id,
            'target_fields' => [
                ['workflow_field_id' => $this->textField->id, 'register_field_name' => 'customer_name'],
            ],
            'response_type' => 'warning',
            'error_message_ar' => 'تحذير: القيمة موجودة',
            'error_message_en' => 'Warning: value exists',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = $this->startExecution($version);

        // Warning should not block
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'existing_value'],
        ]);

        $response->assertSuccessful();
    }

    public function test_not_exists_error_blocks_submission(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

        // Insert a record
        \Illuminate\Support\Facades\DB::table('records')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $this->register->id,
            'record_number' => 'REC-003',
            'data' => json_encode(['customer_name' => 'blocked_value']),
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Not Exists Blocker',
            'validation_type' => 'not_exists',
            'target_register_id' => $this->register->id,
            'target_fields' => [
                ['workflow_field_id' => $this->textField->id, 'register_field_name' => 'customer_name'],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'القيمة محظورة — موجودة مسبقاً',
            'error_message_en' => 'Value is blocked — already exists',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $execution = $this->startExecution($version);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->textField->id => 'blocked_value'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertCount(1, $blocks);
        $this->assertEquals('not_exists', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
        $this->assertEquals('error', $blocks[0]['response_type']);
        $this->assertEquals('القيمة محظورة — موجودة مسبقاً', $blocks[0]['message_ar']);
    }

    // ============================================================
    // cross_register_check — field-to-field match across registers
    // ============================================================

    /**
     * Create a foreign register holding a single record, optionally granting the
     * admin user the per-register read permission that gates cross_register_check.
     *
     * @return \App\Models\Register the foreign register (code REG-CROSS)
     */
    private function setUpForeignRegister(array $recordData, bool $grantPermission = true): \App\Models\Register
    {
        $foreign = \App\Models\Register::create([
            'code' => 'REG-CROSS',
            'name_ar' => 'سجل خارجي',
            'name_en' => 'Foreign Register',
            'fiscal_year' => 2026,
            'current_sequence' => 0,
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\DB::table('records')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'register_id' => $foreign->id,
            'record_number' => 'X-001',
            'data' => json_encode($recordData),
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($grantPermission) {
            $perm = \App\Models\Permission::firstOrCreate(
                ['name' => "read-register-{$foreign->code}", 'guard_name' => 'api']
            );
            $this->admin->givePermissionTo($perm);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        return $foreign;
    }

    /**
     * Build a workflow with a step exposing the source (lookup) and target (compare)
     * fields, plus a cross_register_check rule against the foreign register.
     */
    private function setUpCrossRegisterRule(\App\Models\Register $foreign): \App\Models\WorkflowExecution
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->sourceField, ['step_id' => $step->id]);
        $this->createWorkflowField($version, $this->targetField, ['step_id' => $step->id]);

        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Cross Register Check',
            'validation_type' => 'cross_register_check',
            'target_register_id' => $foreign->id,
            'lookup_config' => [
                'match_field' => 'ref_no',
                'match_workflow_field_id' => $this->sourceField->id,
            ],
            'target_fields' => [
                ['workflow_field_id' => $this->targetField->id, 'register_field_name' => 'status', 'operator' => '='],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'عدم تطابق مع السجل المرجعي',
            'error_message_en' => 'Mismatch with reference record',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $this->startExecution($version);
    }

    public function test_cross_register_check_passes_when_fields_match(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'approved']);
        $execution = $this->setUpCrossRegisterRule($foreign);

        // Lookup finds R-100; its status 'approved' matches the submitted value → pass.
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->sourceField->id => 'R-100',
                $this->targetField->id => 'approved',
            ],
        ]);

        $response->assertSuccessful();
    }

    public function test_cross_register_check_fails_when_fields_mismatch(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'approved']);
        $execution = $this->setUpCrossRegisterRule($foreign);

        // Record found, but its status 'approved' != submitted 'rejected' → blocked.
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->sourceField->id => 'R-100',
                $this->targetField->id => 'rejected',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('cross_register_check', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
    }

    public function test_cross_register_check_fails_when_no_matching_record(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'approved']);
        $execution = $this->setUpCrossRegisterRule($foreign);

        // No record has ref_no 'R-999' → required cross-reference absent → blocked.
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->sourceField->id => 'R-999',
                $this->targetField->id => 'approved',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('cross_register_check', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
    }

    public function test_cross_register_check_blocked_without_permission(): void
    {
        // Data WOULD match, but the acting user lacks read-register-REG-CROSS → blocked.
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'approved'], grantPermission: false);
        $execution = $this->setUpCrossRegisterRule($foreign);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->sourceField->id => 'R-100',
                $this->targetField->id => 'approved',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('cross_register_check', $blocks[0]['validation_type']);
        // Security: the message is the rule's generic error, NOT a permission hint.
        $this->assertEquals('عدم تطابق مع السجل المرجعي', $blocks[0]['message_ar']);
    }

    // ============================================================
    // dynamic_search
    // ============================================================

    /**
     * Build a workflow with a dynamic_search rule against the given foreign register.
     */
    private function setUpDynamicSearchRule(
        \App\Models\Register $foreign,
        string $expectation,
        string $responseType = 'error'
    ): \App\Models\WorkflowExecution {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->sourceField, ['step_id' => $step->id]);

        \App\Models\ValidationRule::create([
            'workflow_version_id' => $version->id,
            'name' => 'Dynamic Search',
            'validation_type' => 'dynamic_search',
            'target_register_id' => $foreign->id,
            'lookup_config' => [
                'search_field' => 'ref_no',
                'search_workflow_field_id' => $this->sourceField->id,
            ],
            'expectation' => $expectation,
            'response_type' => $responseType,
            'error_message_ar' => 'فشل البحث الديناميكي',
            'error_message_en' => 'Dynamic search failed',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return $this->startExecution($version);
    }

    public function test_dynamic_search_passes_when_record_must_exist_and_found(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'active']);
        $execution = $this->setUpDynamicSearchRule($foreign, 'must_exist');

        // Record with ref_no 'R-100' exists → must_exist passes
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'R-100'],
        ]);

        $response->assertSuccessful();
    }

    public function test_dynamic_search_fails_when_record_must_exist_and_not_found(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'active']);
        $execution = $this->setUpDynamicSearchRule($foreign, 'must_exist');

        // No record with ref_no 'R-999' → must_exist fails
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'R-999'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('dynamic_search', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
    }

    public function test_dynamic_search_passes_when_record_must_not_exist_and_not_found(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'active']);
        $execution = $this->setUpDynamicSearchRule($foreign, 'must_not_exist');

        // No record with ref_no 'R-999' → must_not_exist passes
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'R-999'],
        ]);

        $response->assertSuccessful();
    }

    public function test_dynamic_search_fails_when_record_must_not_exist_and_found(): void
    {
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'active']);
        $execution = $this->setUpDynamicSearchRule($foreign, 'must_not_exist');

        // Record with ref_no 'R-100' exists → must_not_exist fails
        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'R-100'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('dynamic_search', $blocks[0]['validation_type']);
        $this->assertEquals('failed', $blocks[0]['result']);
    }

    public function test_dynamic_search_blocked_without_permission(): void
    {
        // Data WOULD match, but the acting user lacks read-register-REG-CROSS → blocked.
        $foreign = $this->setUpForeignRegister(['ref_no' => 'R-100', 'status' => 'active'], grantPermission: false);
        $execution = $this->setUpDynamicSearchRule($foreign, 'must_exist');

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [$this->sourceField->id => 'R-100'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'VALIDATION_BLOCKED');
        $blocks = $response->json('blocks');
        $this->assertNotEmpty($blocks);
        $this->assertEquals('dynamic_search', $blocks[0]['validation_type']);
        // Security: the message is the rule's generic error, NOT a permission hint.
        $this->assertEquals('فشل البحث الديناميكي', $blocks[0]['message_ar']);
    }

    // ============================================================
    // default case — UnimplementedActionException
    // ============================================================

    public function test_execute_validation_with_legacy_rule_throws_in_local(): void
    {
        $originalEnv = app()->environment();
        app()->instance('env', 'local');

        try {
            $workflow = $this->createWorkflow();
            $version = $this->createWorkflowVersion($workflow);
            $version->update(['status' => 'active']);
            $step = $this->createWorkflowStep($version);
            $this->createWorkflowField($version, $this->textField, ['step_id' => $step->id]);

            // Create a LEGACY validation rule (rule_config = null)
            $validationRule = \App\Models\ValidationRule::create([
                'workflow_version_id' => $version->id,
                'name' => 'Legacy Rule',
                'validation_type' => 'sql',
                'sql_query' => 'SELECT 1 as fail_count',
                'sql_condition' => 'fail_count = 0',
                'response_type' => 'error',
                'error_message_ar' => 'خطأ',
                'error_message_en' => 'Error',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            $engine = app(\App\Services\EnterpriseRuleEngine::class);
            $finalValues = [];
            $finalFieldStates = [];

            $this->assertTrue(app()->isLocal(), 'App environment should be local');

            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('rule_config is not null');

            $engine->executeActions(
                [[
                    'type' => 'execute_validation',
                    'field_id' => $this->textField->id,
                    'validation_rule_id' => $validationRule->id,
                ]],
                [],
                $finalValues,
                $finalFieldStates,
                [
                    'execution_id' => 'test-execution-id',
                    'validation_rules' => [(object) ['id' => 'wrong-id']], // non-empty but doesn't contain target rule
                ]
            );
        } finally {
            app()->instance('env', $originalEnv);
        }
    }

    public function test_unimplemented_action_throws_exception(): void
    {
        $this->expectException(\App\Exceptions\Workflow\UnimplementedActionException::class);

        $engine = app(\App\Services\EnterpriseRuleEngine::class);
        $finalValues = [];
        $finalFieldStates = [];
        $engine->executeActions(
            [['type' => 'nonexistent_action_xyz', 'field_id' => 'some-id']],
            [],
            $finalValues,
            $finalFieldStates,
            []
        );
    }

    // ============================================================
    // Helper
    // ============================================================

    private function startExecution(\App\Models\WorkflowVersion $version): \App\Models\WorkflowExecution
    {
        $service = app(\App\Services\WorkflowExecutionService::class);
        return $service->start($version, $this->admin->id, [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);
    }
}

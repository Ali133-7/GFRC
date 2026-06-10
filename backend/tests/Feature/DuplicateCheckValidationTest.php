<?php

namespace Tests\Feature;

use App\Models\Register;
use App\Models\ValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicateCheckValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the existing test setup from parent TestCase
    }

    /**
     * Test that duplicate_check validation rule works correctly.
     */
    public function test_duplicate_check_blocks_duplicate_values(): void
    {
        // Create register
        $register = Register::create([
            'id' => (string) Str::uuid(),
            'code' => 'TEST-REG',
            'name_ar' => 'سجل اختبار',
            'fiscal_year' => 2026,
            'is_active' => true,
        ]);

        // Create register field
        $registerFieldId = (string) Str::uuid();
        DB::table('register_fields')->insert([
            'id' => $registerFieldId,
            'register_id' => $register->id,
            'name' => 'file_number',
            'label_ar' => 'رقم الأضبارة',
            'field_type' => 'text',
            'is_required' => true,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_financial' => false,
            'is_insured' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create workflow
        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'code' => 'TEST-WF',
            'name_ar' => 'سير عمل اختبار',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Create version
        $version = WorkflowVersion::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'version' => 1,
            'status' => 'active',
            'published_at' => now(),
        ]);

        // Create step
        $step = WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 1,
        ]);

        // Create workflow field
        $workflowField = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => 'رقم الأضبارة',
            'is_visible' => true,
            'is_required' => true,
            'is_financial' => false,
            'sort_order' => 1,
        ]);

        // Create duplicate check validation rule
        $validationRule = ValidationRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'منع تكرار رقم الأضبارة',
            'validation_type' => 'duplicate_check',
            'target_register_id' => $register->id,
            'target_fields' => [
                [
                    'workflow_field_id' => $registerFieldId,
                    'register_field_name' => 'file_number',
                ],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'رقم الأضبارة مكرر!',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Insert a record with file_number = "12345"
        DB::table('records')->insert([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'data' => json_encode(['file_number' => '12345']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Start execution
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_id' => $register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        // Try to submit with duplicate file_number
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [
                    $registerFieldId => '12345', // Duplicate!
                ],
            ]);

        // Should fail with validation error
        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'VALIDATION_BLOCKED']);
    }

    /**
     * Test that duplicate_check allows unique values.
     */
    public function test_duplicate_check_allows_unique_values(): void
    {
        // Create register
        $register = Register::create([
            'id' => (string) Str::uuid(),
            'code' => 'TEST-REG-2',
            'name_ar' => 'سجل اختبار 2',
            'fiscal_year' => 2026,
            'is_active' => true,
        ]);

        // Create register field
        $registerFieldId = (string) Str::uuid();
        DB::table('register_fields')->insert([
            'id' => $registerFieldId,
            'register_id' => $register->id,
            'name' => 'file_number',
            'label_ar' => 'رقم الأضبارة',
            'field_type' => 'text',
            'is_required' => true,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_financial' => false,
            'is_insured' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create workflow
        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'code' => 'TEST-WF-2',
            'name_ar' => 'سير عمل اختبار 2',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Create version
        $version = WorkflowVersion::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'version' => 1,
            'status' => 'active',
            'published_at' => now(),
        ]);

        // Create step
        $step = WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 1,
        ]);

        // Create workflow field
        $workflowField = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => 'رقم الأضبارة',
            'is_visible' => true,
            'is_required' => true,
            'is_financial' => false,
            'sort_order' => 1,
        ]);

        // Create duplicate check validation rule
        $validationRule = ValidationRule::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'name' => 'منع تكرار رقم الأضبارة',
            'validation_type' => 'duplicate_check',
            'target_register_id' => $register->id,
            'target_fields' => [
                [
                    'workflow_field_id' => $registerFieldId,
                    'register_field_name' => 'file_number',
                ],
            ],
            'response_type' => 'error',
            'error_message_ar' => 'رقم الأضبارة مكرر!',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Insert a record with file_number = "12345"
        DB::table('records')->insert([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'data' => json_encode(['file_number' => '12345']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Start execution
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_id' => $register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        // Submit with unique file_number
        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [
                    $registerFieldId => '99999', // Unique!
                ],
            ]);

        // Should succeed
        $response->assertSuccessful();
    }

    /**
     * Test that completing workflow creates record in register.
     */
    public function test_complete_execution_creates_record_in_register(): void
    {
        // Create register
        $register = Register::create([
            'id' => (string) Str::uuid(),
            'code' => 'TEST-REG-3',
            'name_ar' => 'سجل اختبار 3',
            'fiscal_year' => 2026,
            'is_active' => true,
        ]);

        // Create register field
        $registerFieldId = (string) Str::uuid();
        DB::table('register_fields')->insert([
            'id' => $registerFieldId,
            'register_id' => $register->id,
            'name' => 'file_number',
            'label_ar' => 'رقم الأضبارة',
            'field_type' => 'text',
            'is_required' => true,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_financial' => false,
            'is_insured' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create workflow
        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'code' => 'TEST-WF-3',
            'name_ar' => 'سير عمل اختبار 3',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Create version
        $version = WorkflowVersion::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => $workflow->id,
            'version' => 1,
            'status' => 'active',
            'published_at' => now(),
        ]);

        // Create step
        $step = WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'title_ar' => 'الخطوة الأولى',
            'sort_order' => 1,
        ]);

        // Create workflow field
        $workflowField = WorkflowField::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_field_id' => $registerFieldId,
            'step_id' => $step->id,
            'label' => 'رقم الأضبارة',
            'is_visible' => true,
            'is_required' => true,
            'is_financial' => false,
            'sort_order' => 1,
        ]);

        // Start execution
        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_version_id' => $version->id,
            'register_id' => $register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        // Submit step
        $this->actingAs($this->admin)
            ->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
                'step_index' => 0,
                'values' => [
                    $registerFieldId => '54321',
                ],
            ])
            ->assertSuccessful();

        // Complete execution
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/workflow-executions/{$execution->id}/complete", [
                'notes' => 'test',
            ]);

        $response->assertSuccessful();

        // Verify record was created in register
        $record = DB::table('records')
            ->where('register_id', $register->id)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($record, 'Record should be created in register');

        $data = json_decode($record->data, true);
        $this->assertEquals('54321', $data['file_number'] ?? null);
    }
}

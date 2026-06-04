<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowIntegrationTest extends TestCase
{
    public function test_create_workflow(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/workflows', [
            'register_id' => $this->register->id,
            'code' => 'WF-TEST',
            'name_ar' => 'سير العمل',
            'name_en' => 'Test Workflow',
            'description' => 'A test workflow',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflows', ['name_en' => 'Test Workflow']);
    }

    public function test_create_workflow_version(): void
    {
        $workflow = $this->createWorkflow();

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$workflow->id}/versions", [
            'version' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflow_versions', [
            'workflow_id' => $workflow->id,
            'status' => 'draft',
        ]);
    }

    public function test_publish_workflow_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->financialField);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$workflow->id}/versions/{$version->id}/publish");

        $response->assertStatus(200);
        $this->assertDatabaseHas('workflow_versions', [
            'id' => $version->id,
            'status' => 'active',
        ]);
    }

    public function test_add_step_to_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$workflow->id}/versions/{$version->id}/steps", [
            'title_ar' => 'إدخال البيانات',
            'title_en' => 'Data Entry',
            'sort_order' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflow_steps', [
            'workflow_version_id' => $version->id,
            'title_en' => 'Data Entry',
        ]);
    }

    public function test_add_field_to_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$workflow->id}/versions/{$version->id}/fields", [
            'register_field_id' => $this->financialField->id,
            'is_visible' => true,
            'is_required' => true,
            'is_readonly' => false,
            'label' => 'Service Fee',
            'sort_order' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflow_fields', [
            'workflow_version_id' => $version->id,
            'register_field_id' => $this->financialField->id,
        ]);
    }

    public function test_add_rule_to_version(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflows/{$workflow->id}/versions/{$version->id}/rules", [
            'name' => 'High Value Rule',
            'condition_logic' => [
                'logic' => 'AND',
                'conditions' => [
                    ['field_id' => $this->financialField->id, 'operator' => 'gt', 'value' => '1000'],
                ],
            ],
            'actions' => [
                ['action' => 'set_required', 'target_field_id' => $this->financialField->id],
            ],
            'sort_order' => 1,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflow_rules', [
            'workflow_version_id' => $version->id,
            'name' => 'High Value Rule',
        ]);
    }

    public function test_start_workflow_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $this->createWorkflowStep($version);
        $this->createWorkflowField($version, $this->financialField);

        $response = $this->actingAsAdmin()->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $version->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workflow_executions', [
            'workflow_version_id' => $version->id,
            'status' => 'in_progress',
        ]);

        $executionId = $response->json('data.execution.id');
        $this->assertNotNull($executionId);
        $this->assertDatabaseHas('workflow_execution_events', [
            'execution_id' => $executionId,
            'event_type' => 'execution_started',
        ]);
    }

    public function test_submit_step(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $step = $this->createWorkflowStep($version);
        $field = $this->createWorkflowField($version, $this->financialField);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        // Create execution_started event
        WorkflowExecutionEvent::create([
            'execution_id' => $execution->id,
            'event_type' => 'execution_started',
            'sequence' => 0,
            'event_payload' => ['step_index' => 0],
            'hash' => hash('sha256', 'genesis'),
        ]);

        $response = $this->actingAsAdmin()->putJson("/api/v1/workflow-executions/{$execution->id}/step", [
            'step_index' => 0,
            'values' => [
                $this->financialField->id => '100',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('workflow_execution_events', [
            'execution_id' => $execution->id,
            'event_type' => 'step_submitted',
        ]);
    }

    public function test_complete_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);
        $this->createWorkflowStep($version);
        $field = $this->createWorkflowField($version, $this->financialField);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        WorkflowExecutionEvent::create([
            'execution_id' => $execution->id,
            'event_type' => 'execution_started',
            'sequence' => 0,
            'event_payload' => ['step_index' => 0],
            'hash' => hash('sha256', 'genesis'),
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/complete", [
            'notes' => 'Test completion',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('workflow_execution_events', [
            'execution_id' => $execution->id,
            'event_type' => 'execution_completed',
        ]);
        $this->assertDatabaseHas('receipts', [
            'workflow_execution_id' => $execution->id,
        ]);
    }

    public function test_cancel_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);
        $version->update(['status' => 'active']);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        WorkflowExecutionEvent::create([
            'execution_id' => $execution->id,
            'event_type' => 'execution_started',
            'sequence' => 0,
            'event_payload' => ['step_index' => 0],
            'hash' => hash('sha256', 'genesis'),
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/workflow-executions/{$execution->id}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('workflow_execution_events', [
            'execution_id' => $execution->id,
            'event_type' => 'execution_cancelled',
        ]);
    }
}

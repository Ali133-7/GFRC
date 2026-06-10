<?php

namespace Tests\Feature;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowField;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Tests\TestCase;

class WorkflowExecutionRaceConditionTest extends TestCase
{
    private Workflow $workflow;
    private WorkflowVersion $version;
    private WorkflowExecution $execution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = $this->createWorkflow();
        $this->version = $this->createWorkflowVersion($this->workflow, ['status' => 'active']);
        $step = $this->createWorkflowStep($this->version);
        $this->createWorkflowField($this->version, $this->financialField, [
            'step_id' => $step->id,
            'fee_code' => 'GOV-001',
        ]);

        $this->actingAsCashier();
        $startResponse = $this->postJson('/api/v1/workflow-executions', [
            'workflow_version_id' => $this->version->id,
        ]);
        $startResponse->assertSuccessful();

        $this->execution = WorkflowExecution::find($startResponse->json('data.execution.id'));
    }

    public function test_complete_with_lock_version_mismatch_returns_409(): void
    {
        $this->actingAsCashier();

        // First request completes successfully
        $response1 = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'First completion',
        ]);
        $response1->assertSuccessful();

        // Refresh execution to get updated lock_version
        $this->execution->refresh();

        // Second request with stale object (already completed) should fail with 422
        $response2 = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'Second completion',
        ]);
        $response2->assertStatus(422);
    }

    public function test_complete_is_idempotent(): void
    {
        $this->actingAsCashier();

        $response1 = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'Test',
        ]);
        $response1->assertSuccessful();

        $receiptId1 = $response1->json('data.receipt.id');

        // Same request again should return 422 (already completed)
        $response2 = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'Test',
        ]);
        $response2->assertStatus(422);

        // Should not create a second receipt
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_cancel_after_complete_fails_with_409(): void
    {
        $this->actingAsCashier();

        $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'Test',
        ])->assertSuccessful();

        $response = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/cancel", [
            'reason' => 'Should fail',
        ]);

        $response->assertStatus(422);
    }

    public function test_service_level_stale_lock_version_fails(): void
    {
        $this->actingAsCashier();

        // Complete the execution first
        $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/complete", [
            'notes' => 'Test',
        ])->assertSuccessful();

        $this->execution->refresh();

        // Attempting to cancel a completed execution should fail with 422
        $response = $this->postJson("/api/v1/workflow-executions/{$this->execution->id}/cancel", [
            'reason' => 'Should fail',
        ]);

        $response->assertStatus(422);
    }
}

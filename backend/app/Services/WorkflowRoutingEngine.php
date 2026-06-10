<?php

namespace App\Services;

use App\Models\WorkflowExecution;
use App\Models\WorkflowRoutingLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Workflow Routing Engine
 *
 * Supports:
 *   continue_current
 *   redirect_workflow(target_workflow_id)
 *   redirect_step(step_id)
 *   switch_mode
 *   pause / resume
 *
 * Preserves:
 *   values_snapshot
 *   execution_history
 *   audit_trail
 */
class WorkflowRoutingEngine
{
    /**
     * Redirect an execution to a different workflow.
     *
     * @throws \RuntimeException
     */
    public function redirectWorkflow(
        WorkflowExecution $sourceExecution,
        string $targetWorkflowId,
        ?string $targetStepId = null,
        ?string $triggerRuleId = null,
        string $reason = ''
    ): WorkflowExecution {
        return DB::transaction(function () use ($sourceExecution, $targetWorkflowId, $targetStepId, $triggerRuleId, $reason) {
            $sourceExecution->lockForUpdate()->find($sourceExecution->id);

            if (!$sourceExecution->isInProgress()) {
                throw new \RuntimeException('Cannot redirect: execution is not in progress');
            }

            $targetWorkflow = \App\Models\Workflow::lockForUpdate()->find($targetWorkflowId);
            if (!$targetWorkflow || !$targetWorkflow->is_active) {
                throw new \RuntimeException('Target workflow is not active');
            }

            $targetVersion = $targetWorkflow->activeVersion();
            if (!$targetVersion) {
                throw new \RuntimeException('Target workflow has no active version');
            }

            // Create new execution for target workflow
            $newExecution = WorkflowExecution::create([
                'workflow_version_id' => $targetVersion->id,
                'register_id' => $sourceExecution->register_id,
                'status' => 'in_progress',
                'mode' => $sourceExecution->mode,
                'lock_version' => 0,
                'current_step_index' => 0,
                'values_snapshot' => $sourceExecution->values_snapshot ?? [],
                'calculated_items' => $sourceExecution->calculated_items ?? [],
                'total_amount' => $sourceExecution->total_amount,
                'started_by' => $sourceExecution->started_by,
                'started_at' => now(),
                'preserved_values' => $sourceExecution->values_snapshot ?? [],
            ]);

            // Update source execution
            $sourceExecution->setRedirect($targetVersion->id, $targetStepId);
            $sourceExecution->update(['status' => 'redirected']);

            // Log routing
            WorkflowRoutingLog::create([
                'id' => (string) Str::uuid(),
                'execution_id' => $sourceExecution->id,
                'from_workflow_id' => $sourceExecution->version->workflow_id,
                'to_workflow_id' => $targetWorkflowId,
                'from_step_id' => $sourceExecution->current_step_index,
                'trigger_rule_id' => $triggerRuleId,
                'reason' => $reason,
                'values_snapshot' => $sourceExecution->values_snapshot ?? [],
                'created_by' => $sourceExecution->started_by,
            ]);

            Log::info('WorkflowRoutingEngine: redirected execution', [
                'source_execution_id' => $sourceExecution->id,
                'target_execution_id' => $newExecution->id,
                'from_workflow_id' => $sourceExecution->version->workflow_id,
                'to_workflow_id' => $targetWorkflowId,
            ]);

            return $newExecution;
        });
    }

    /**
     * Switch execution mode with full audit.
     */
    public function switchMode(WorkflowExecution $execution, string $newMode, string $reason = ''): void
    {
        DB::transaction(function () use ($execution, $newMode, $reason) {
            $execution->lockForUpdate()->find($execution->id);

            $oldMode = $execution->mode;
            $execution->switchMode($newMode, $reason);

            Log::info('WorkflowRoutingEngine: mode switch', [
                'execution_id' => $execution->id,
                'from_mode' => $oldMode,
                'to_mode' => $newMode,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Pause execution.
     */
    public function pause(WorkflowExecution $execution, string $reason = ''): void
    {
        DB::transaction(function () use ($execution, $reason) {
            $execution->lockForUpdate()->find($execution->id);
            $execution->pauseExecution($reason);
        });
    }

    /**
     * Resume execution.
     */
    public function resume(WorkflowExecution $execution): void
    {
        DB::transaction(function () use ($execution) {
            $execution->lockForUpdate()->find($execution->id);
            $execution->resumeExecution();
        });
    }
}

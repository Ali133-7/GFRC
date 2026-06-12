<?php

namespace App\Services;

/**
 * Execution State Manager for Real-Time Rule Execution
 * 
 * Tracks the execution status of real-time rule evaluation.
 * 
 * States:
 * - IDLE: No execution in progress
 * - EVALUATING: Rules are being evaluated
 * - CALCULATING: Financial calculations in progress
 * - READY: Execution complete, values updated
 * - ERROR: Execution failed
 */
class ExecutionStateManager
{
    public const STATE_IDLE = 'IDLE';
    public const STATE_EVALUATING = 'EVALUATING';
    public const STATE_CALCULATING = 'CALCULATING';
    public const STATE_READY = 'READY';
    public const STATE_ERROR = 'ERROR';
    
    protected array $states = [];
    protected array $errors = [];
    protected array $initialValues = [];
    
    /**
     * Get execution state for a workflow execution
     */
    public function getState(string $executionId): string
    {
        return $this->states[$executionId] ?? self::STATE_IDLE;
    }
    
    /**
     * Set execution state
     */
    public function setState(string $executionId, string $state): void
    {
        if (!in_array($state, [
            self::STATE_IDLE,
            self::STATE_EVALUATING,
            self::STATE_CALCULATING,
            self::STATE_READY,
            self::STATE_ERROR
        ])) {
            throw new \InvalidArgumentException("Invalid state: {$state}");
        }
        
        $this->states[$executionId] = $state;
    }
    
    /**
     * Mark execution as evaluating
     */
    public function startEvaluation(string $executionId): void
    {
        $this->setState($executionId, self::STATE_EVALUATING);
        unset($this->errors[$executionId]);
    }
    
    /**
     * Mark execution as calculating
     */
    public function startCalculation(string $executionId): void
    {
        $this->setState($executionId, self::STATE_CALCULATING);
    }
    
    /**
     * Mark execution as ready
     */
    public function markReady(string $executionId): void
    {
        $this->setState($executionId, self::STATE_READY);
    }
    
    /**
     * Mark execution as error
     */
    public function markError(string $executionId, string $error): void
    {
        $this->setState($executionId, self::STATE_ERROR);
        $this->errors[$executionId] = $error;
    }
    
    /**
     * Get error message for an execution
     */
    public function getError(string $executionId): ?string
    {
        return $this->errors[$executionId] ?? null;
    }
    
    /**
     * Check if execution is in progress
     */
    public function isExecuting(string $executionId): bool
    {
        $state = $this->getState($executionId);
        return in_array($state, [self::STATE_EVALUATING, self::STATE_CALCULATING]);
    }
    
    /**
     * Check if execution is ready (can proceed to next step)
     */
    public function isReady(string $executionId): bool
    {
        return $this->getState($executionId) === self::STATE_READY;
    }
    
    /**
     * Check if execution has errors
     */
    public function hasError(string $executionId): bool
    {
        return $this->getState($executionId) === self::STATE_ERROR;
    }
    
    /**
     * Reset execution state to idle
     */
    public function reset(string $executionId): void
    {
        unset($this->states[$executionId]);
        unset($this->errors[$executionId]);
        unset($this->initialValues[$executionId]);
    }
    
    /**
     * Get all states (for debugging)
     */
    public function getAllStates(): array
    {
        return $this->states;
    }
    
    /**
     * Get initial value for a calculated field
     */
    public function getInitialValue(string $executionId, string $fieldId): mixed
    {
        return $this->initialValues[$executionId][$fieldId] ?? null;
    }
    
    /**
     * Set initial value for a calculated field
     */
    public function setInitialValue(string $executionId, string $fieldId, mixed $value): void
    {
        $this->initialValues[$executionId][$fieldId] = $value;
    }
    
    /**
     * Persist state to database
     */
    public function persistState(string $executionId): void
    {
        $state = $this->getState($executionId);
        $error = $this->getError($executionId);
        
        \DB::table('workflow_executions')
            ->where('id', $executionId)
            ->update([
                'execution_status' => $state,
                'execution_error' => $error,
                'updated_at' => now(),
            ]);
    }
}

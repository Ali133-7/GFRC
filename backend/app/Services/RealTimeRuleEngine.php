<?php

namespace App\Services;

use App\Models\ValidationRule;
use App\Models\WorkflowRule;
use Illuminate\Support\Collection;

/**
 * Real-Time Rule Engine
 * 
 * Executes rules immediately when fields change, without waiting for
 * step submission or workflow progression.
 */
class RealTimeRuleEngine
{
    protected EnterpriseRuleEngine $enterpriseEngine;
    protected ValidationEngine $validationEngine;
    protected DependencyResolver $dependencyResolver;
    protected ExecutionStateManager $executionStateManager;
    protected FinancialRecalculator $financialRecalculator;
    
    public function __construct(
        EnterpriseRuleEngine $enterpriseEngine,
        ValidationEngine $validationEngine,
        DependencyResolver $dependencyResolver,
        ExecutionStateManager $executionStateManager,
        FinancialRecalculator $financialRecalculator
    ) {
        $this->enterpriseEngine = $enterpriseEngine;
        $this->validationEngine = $validationEngine;
        $this->dependencyResolver = $dependencyResolver;
        $this->executionStateManager = $executionStateManager;
        $this->financialRecalculator = $financialRecalculator;
    }
    
    /**
     * Execute real-time rule evaluation for a field change
     */
    public function execute(string $workflowVersionId, string $changedFieldId, array $values, string $executionId): array
    {
        try {
            // Set state to EVALUATING
            $this->executionStateManager->startEvaluation($executionId);
            
            // Load rules for this version
            $validationRules = ValidationRule::where('workflow_version_id', $workflowVersionId)
                ->where('is_active', true)
                ->get();
            
            $workflowRules = WorkflowRule::where('workflow_version_id', $workflowVersionId)
                ->where('is_active', true)
                ->get();
            
            \Log::info('[RealTimeRuleEngine] Loaded rules', [
                'workflow_version_id' => $workflowVersionId,
                'validation_rules_count' => $validationRules->count(),
                'workflow_rules_count' => $workflowRules->count(),
                'realtime_validation_count' => $validationRules->where('realtime_enabled', true)->count(),
                'realtime_workflow_count' => $workflowRules->where('realtime_enabled', true)->count(),
            ]);
            
            // Build dependency graph
            $this->dependencyResolver->buildGraph($validationRules, $workflowRules);
            
            // Get affected rules (only realtime_enabled rules)
            $affectedRuleIds = $this->dependencyResolver->getRealTimeAffectedRules($changedFieldId, [
                'validation_rules' => $validationRules,
                'workflow_rules' => $workflowRules,
            ]);
            
            // Filter to realtime_enabled rules
            $realtimeValidationRules = $validationRules->filter(function($rule) use ($affectedRuleIds) {
                return $rule->realtime_enabled && in_array($rule->id, $affectedRuleIds);
            });
            
            $realtimeWorkflowRules = $workflowRules->filter(function($rule) use ($affectedRuleIds) {
                return $rule->realtime_enabled && in_array($rule->id, $affectedRuleIds);
            });
            
            // CRITICAL: Separate user input values from calculated values
            // User input values: fields that the user directly edits
            // Calculated values: fields that are computed by rules (should not be used in formulas)
            // For now, we'll use all values as input, but exclude fields that are targets of calculate actions
            $calculatedFieldIds = [];
            foreach ($realtimeValidationRules as $rule) {
                $ruleConfig = $rule->rule_config ?? [];
                $actions = $ruleConfig['actions'] ?? [];
                foreach ($actions as $action) {
                    if ($action['type'] === 'calculate' && isset($action['field_id'])) {
                        $calculatedFieldIds[] = $action['field_id'];
                    }
                }
            }
            foreach ($realtimeWorkflowRules as $rule) {
                $actions = $rule->actions ?? [];
                foreach ($actions as $action) {
                    if ($action['type'] === 'calculate' && isset($action['field_id'])) {
                        $calculatedFieldIds[] = $action['field_id'];
                    }
                }
            }
            
            // Original input values = all values EXCEPT calculated fields
            // For calculated fields, we need to get their initial/default values from the execution
            $originalInputValues = [];
            foreach ($values as $fieldId => $value) {
                if (!in_array($fieldId, $calculatedFieldIds)) {
                    $originalInputValues[$fieldId] = $value;
                }
            }
            
            // For calculated fields, try to get their initial values from execution context
            // If not available, use 0 or the field's default value
            foreach ($calculatedFieldIds as $fieldId) {
                // Check if we have a stored initial value for this field
                $initialValue = $this->executionStateManager->getInitialValue($executionId, $fieldId);
                if ($initialValue !== null) {
                    $originalInputValues[$fieldId] = $initialValue;
                } else {
                    // First time seeing this field - store its current value as initial
                    // But for formulas, we want to use 0 or a base value
                    // For now, use 0 as the base for calculated fields
                    $originalInputValues[$fieldId] = 0;
                    
                    // Store the initial value for future requests
                    $this->executionStateManager->setInitialValue($executionId, $fieldId, 0);
                }
            }
            
            // Multi-pass execution until stable (max 10 iterations to prevent infinite loops)
            $maxIterations = 10;
            $iteration = 0;
            $hasChanges = true;
            $currentValues = $values;
            
            while ($hasChanges && $iteration < $maxIterations) {
                $iteration++;
                $previousValues = $currentValues;
                
                // Execute validation rules using original input values for conditions
                $validationResults = [];
                foreach ($realtimeValidationRules as $rule) {
                    $result = $this->validationEngine->runValidation($rule, $originalInputValues, []);
                    $validationResults[] = $result;
                }
                
                // Execute workflow rules (including case rules)
                $workflowResults = [];
                foreach ($realtimeWorkflowRules as $rule) {
                    // Build rule config with cases and else_actions
                    $ruleConfig = [
                        'conditions' => $rule->condition_logic ? [$rule->condition_logic] : [],
                        'actions' => $rule->actions ?? [],
                        'cases' => $rule->cases ?? [],
                        'else_actions' => $rule->default_actions ?? [],
                    ];
                    
                    // FIXED: Create wrapper arrays for reference parameters
                    $finalValues = $currentValues;
                    $finalFieldStates = [];
                    
                    $result = $this->enterpriseEngine->evaluateRule(
                        $rule->id,
                        $rule->name,
                        $rule->rule_type,
                        $ruleConfig['conditions'],
                        $ruleConfig['actions'],
                        $ruleConfig['else_actions'],
                        $ruleConfig['cases'],
                        $originalInputValues, // Use original values for formula calculation
                        $finalValues,
                        $finalFieldStates,
                        []
                    );
                    
                    $workflowResults[] = $result;
                    
                    // Apply field effects to current values for next iteration
                    if (isset($result['field_effects'])) {
                        foreach ($result['field_effects'] as $effect) {
                            if (isset($effect['resolved_amount']) && isset($effect['field_id'])) {
                                $currentValues[$effect['field_id']] = $effect['resolved_amount'];
                            } elseif (isset($effect['amount']) && isset($effect['field_id'])) {
                                $currentValues[$effect['field_id']] = $effect['amount'];
                            } elseif (isset($effect['value']) && isset($effect['field_id'])) {
                                $currentValues[$effect['field_id']] = $effect['value'];
                            }
                        }
                    }
                }
                
                // Check if values changed
                $hasChanges = json_encode($previousValues) !== json_encode($currentValues);
            }
            
            if ($iteration >= $maxIterations) {
                throw new \RuntimeException('Maximum execution iterations reached - possible infinite loop');
            }
            
            // Set state to CALCULATING
            $this->executionStateManager->startCalculation($executionId);
            
            // Recalculate financials with final values
            $financialResults = $this->financialRecalculator->recalculate($currentValues, $validationResults, $workflowResults);
            
            // Set state to READY
            $this->executionStateManager->markReady($executionId);
            
            return [
                'success' => true,
                'validation_results' => $validationResults,
                'workflow_results' => $workflowResults,
                'financial_results' => $financialResults,
                'affected_rule_count' => count($realtimeValidationRules) + count($realtimeWorkflowRules),
                'iterations' => $iteration,
            ];
            
        } catch (\Exception $e) {
            // Set state to ERROR
            $this->executionStateManager->markError($executionId, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'affected_rule_count' => 0,
            ];
        }
    }
    
    /**
     * Check if a rule change would create a cycle
     */
    public function wouldCreateCycle($rule, string $type): bool
    {
        $loopDetector = new LoopDetector($this->dependencyResolver);
        return $loopDetector->wouldCreateCycle($rule, $type);
    }
    
    /**
     * Get execution status for a workflow execution
     */
    public function getExecutionStatus(string $executionId): string
    {
        return $this->executionStateManager->getState($executionId);
    }
    
    /**
     * Check if next button should be enabled
     */
    public function isNextButtonEnabled(string $executionId): bool
    {
        $state = $this->executionStateManager->getState($executionId);
        return $state === ExecutionStateManager::STATE_READY || $state === ExecutionStateManager::STATE_IDLE;
    }
}

<?php

namespace App\Services;

use App\Models\ValidationRule;
use App\Models\WorkflowRule;
use Illuminate\Support\Collection;

/**
 * Loop Detector for Real-Time Rule Execution
 * 
 * Detects circular dependencies in rule execution to prevent infinite loops.
 */
class LoopDetector
{
    protected DependencyResolver $dependencyResolver;
    
    public function __construct(DependencyResolver $dependencyResolver)
    {
        $this->dependencyResolver = $dependencyResolver;
    }
    
    /**
     * Detect if adding/updating a rule would create a cycle
     */
    public function wouldCreateCycle($rule, string $type): bool
    {
        // Build temporary graph with the new/updated rule
        $allValidationRules = $type === 'validation' 
            ? collect([$rule]) 
            : ValidationRule::where('is_active', true)->get();
        
        $allWorkflowRules = $type === 'workflow'
            ? collect([$rule])
            : WorkflowRule::where('is_active', true)->get();
        
        $this->dependencyResolver->buildGraph($allValidationRules, $allWorkflowRules);
        
        // Extract fields from the rule
        $conditionFields = $this->extractConditionFields($rule, $type);
        
        // Check for cycles starting from each condition field
        foreach ($conditionFields as $fieldId) {
            if ($this->dependencyResolver->hasCycle($fieldId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect cycles in an existing set of rules
     */
    public function detectCyclesInWorkflow(Collection $validationRules, Collection $workflowRules): bool
    {
        $this->dependencyResolver->buildGraph($validationRules, $workflowRules);
        
        // Get all unique field IDs
        $allFields = array_unique(array_merge(
            array_keys($this->dependencyResolver->getGraphAsArray()['fieldToRules']),
            array_keys($this->dependencyResolver->getGraphAsArray()['fieldDependencies'])
        ));
        
        // Check for cycles starting from each field
        foreach ($allFields as $fieldId) {
            if ($this->dependencyResolver->hasCycle($fieldId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the cycle path (for error reporting)
     */
    public function getCyclePath(string $startFieldId): array
    {
        $path = [];
        $visited = [];
        $recursionStack = [];
        
        $this->findCyclePath($startFieldId, $path, $visited, $recursionStack);
        
        return $path;
    }
    
    /**
     * DFS to find the actual cycle path
     */
    protected function findCyclePath(string $fieldId, array &$path, array &$visited, array &$recursionStack): bool
    {
        $visited[$fieldId] = true;
        $recursionStack[$fieldId] = true;
        $path[] = $fieldId;
        
        if (isset($this->dependencyResolver->getGraphAsArray()['fieldDependencies'][$fieldId])) {
            foreach ($this->dependencyResolver->getGraphAsArray()['fieldDependencies'][$fieldId] as $dependentFieldId) {
                if (!isset($visited[$dependentFieldId])) {
                    if ($this->findCyclePath($dependentFieldId, $path, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$dependentFieldId])) {
                    $path[] = $dependentFieldId; // Complete the cycle
                    return true;
                }
            }
        }
        
        array_pop($path);
        unset($recursionStack[$fieldId]);
        return false;
    }
    
    /**
     * Extract condition fields from a rule
     */
    protected function extractConditionFields($rule, string $type): array
    {
        $fields = [];
        
        if ($type === 'validation') {
            if ($rule->trigger_field_id) {
                $fields[] = $rule->trigger_field_id;
            }
            
            if ($rule->trigger_conditions && is_array($rule->trigger_conditions)) {
                foreach ($rule->trigger_conditions as $condition) {
                    if (isset($condition['field_id'])) {
                        $fields[] = $condition['field_id'];
                    }
                }
            }
            
            if ($rule->rule_config && isset($rule->rule_config['conditions'])) {
                $fields = array_merge($fields, $this->extractFieldsFromConditions($rule->rule_config['conditions']));
            }
        } elseif ($type === 'workflow') {
            if ($rule->trigger_field_id) {
                $fields[] = $rule->trigger_field_id;
            }
            
            if ($rule->condition_logic && is_array($rule->condition_logic)) {
                $fields = array_merge($fields, $this->extractFieldsFromConditions([$rule->condition_logic]));
            }
        }
        
        return array_unique($fields);
    }
    
    /**
     * Recursively extract field IDs from condition structures
     */
    protected function extractFieldsFromConditions(array $conditions): array
    {
        $fields = [];
        
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            
            if (isset($condition['field_id'])) {
                $fields[] = $condition['field_id'];
            }
            
            if (isset($condition['conditions']) && is_array($condition['conditions'])) {
                $fields = array_merge($fields, $this->extractFieldsFromConditions($condition['conditions']));
            }
        }
        
        return $fields;
    }
}

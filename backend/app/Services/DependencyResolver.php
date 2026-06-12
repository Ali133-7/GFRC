<?php

namespace App\Services;

use App\Models\ValidationRule;
use App\Models\WorkflowRule;
use Illuminate\Support\Collection;

/**
 * Dependency Resolver for Real-Time Rule Execution
 * 
 * Builds and maintains a dependency graph to efficiently determine
 * which rules need to execute when a field changes.
 */
class DependencyResolver
{
    protected array $fieldToRules = [];
    protected array $ruleToFields = [];
    protected array $fieldDependencies = [];
    
    /**
     * Build dependency graph from rules
     */
    public function buildGraph(Collection $validationRules, Collection $workflowRules): void
    {
        $this->fieldToRules = [];
        $this->ruleToFields = [];
        $this->fieldDependencies = [];
        
        // Process validation rules
        foreach ($validationRules as $rule) {
            $this->processRule($rule, 'validation');
        }
        
        // Process workflow rules
        foreach ($workflowRules as $rule) {
            $this->processRule($rule, 'workflow');
        }
    }
    
    /**
     * Process a single rule and extract dependencies
     */
    protected function processRule($rule, string $type): void
    {
        $ruleId = $rule->id;
        
        // Extract condition fields (fields that this rule depends on)
        $conditionFields = $this->extractConditionFields($rule, $type);
        
        // Extract action fields (fields that this rule affects)
        $actionFields = $this->extractActionFields($rule, $type);
        
        // Map: field_id → rule_id (which rules depend on this field)
        foreach ($conditionFields as $fieldId) {
            if (!isset($this->fieldToRules[$fieldId])) {
                $this->fieldToRules[$fieldId] = [];
            }
            $this->fieldToRules[$fieldId][] = $ruleId;
        }
        
        // Map: rule_id → field_ids (which fields does this rule affect)
        $this->ruleToFields[$ruleId] = $actionFields;
        
        // Map: field_id → dependent field_ids (field dependencies)
        foreach ($conditionFields as $conditionField) {
            foreach ($actionFields as $actionField) {
                if (!isset($this->fieldDependencies[$conditionField])) {
                    $this->fieldDependencies[$conditionField] = [];
                }
                $this->fieldDependencies[$conditionField][] = $actionField;
            }
        }
    }
    
    /**
     * Extract condition fields from a rule
     */
    protected function extractConditionFields($rule, string $type): array
    {
        $fields = [];
        
        if ($type === 'validation') {
            // Validation rules: extract from trigger_conditions and rule_config
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
            // Workflow rules: extract from condition_logic
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
     * Extract action fields from a rule
     */
    protected function extractActionFields($rule, string $type): array
    {
        $fields = [];
        
        if ($type === 'validation') {
            // Validation rules: extract from target_fields and field_effects
            if ($rule->target_fields && is_array($rule->target_fields)) {
                foreach ($rule->target_fields as $fieldConfig) {
                    if (isset($fieldConfig['workflow_field_id'])) {
                        $fields[] = $fieldConfig['workflow_field_id'];
                    }
                }
            }
            
            if ($rule->field_effects && is_array($rule->field_effects)) {
                foreach ($rule->field_effects as $effect) {
                    if (isset($effect['field_id'])) {
                        $fields[] = $effect['field_id'];
                    }
                }
            }
            
            if ($rule->rule_config && isset($rule->rule_config['actions'])) {
                foreach ($rule->rule_config['actions'] as $action) {
                    if (isset($action['field_id'])) {
                        $fields[] = $action['field_id'];
                    }
                }
            }
        } elseif ($type === 'workflow') {
            // Workflow rules: extract from actions
            if ($rule->actions && is_array($rule->actions)) {
                foreach ($rule->actions as $action) {
                    if (isset($action['target_field_id'])) {
                        $fields[] = $action['target_field_id'];
                    }
                }
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
            
            // Simple condition
            if (isset($condition['field_id'])) {
                $fields[] = $condition['field_id'];
            }
            
            // Group condition
            if (isset($condition['conditions']) && is_array($condition['conditions'])) {
                $fields = array_merge($fields, $this->extractFieldsFromConditions($condition['conditions']));
            }
        }
        
        return $fields;
    }
    
    /**
     * Get all rules affected by a field change
     */
    public function getAffectedRules(string $fieldId, bool $realtimeOnly = false): array
    {
        $affectedRuleIds = [];
        $visited = [];
        $queue = [$fieldId];
        
        while (!empty($queue)) {
            $currentFieldId = array_shift($queue);
            
            if (isset($visited[$currentFieldId])) {
                continue;
            }
            
            $visited[$currentFieldId] = true;
            
            // Get rules that depend on this field
            if (isset($this->fieldToRules[$currentFieldId])) {
                foreach ($this->fieldToRules[$currentFieldId] as $ruleId) {
                    if (!in_array($ruleId, $affectedRuleIds)) {
                        $affectedRuleIds[] = $ruleId;
                        
                        // Add affected fields to queue for transitive dependencies
                        if (isset($this->ruleToFields[$ruleId])) {
                            foreach ($this->ruleToFields[$ruleId] as $affectedFieldId) {
                                if (!isset($visited[$affectedFieldId])) {
                                    $queue[] = $affectedFieldId;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $affectedRuleIds;
    }
    
    /**
     * Get real-time enabled rules affected by a field change
     */
    public function getRealTimeAffectedRules(string $fieldId, array $rulesContext = []): array
    {
        $allAffected = $this->getAffectedRules($fieldId);
        
        // If rules context is provided, filter by realtime_enabled
        if (!empty($rulesContext['validation_rules']) || !empty($rulesContext['workflow_rules'])) {
            $realtimeRuleIds = [];
            
            // Get realtime_enabled validation rule IDs
            if (!empty($rulesContext['validation_rules'])) {
                foreach ($rulesContext['validation_rules'] as $rule) {
                    if ($rule instanceof \App\Models\ValidationRule && $rule->realtime_enabled) {
                        $realtimeRuleIds[] = $rule->id;
                    }
                }
            }
            
            // Get realtime_enabled workflow rule IDs
            if (!empty($rulesContext['workflow_rules'])) {
                foreach ($rulesContext['workflow_rules'] as $rule) {
                    if ($rule instanceof \App\Models\WorkflowRule && $rule->realtime_enabled) {
                        $realtimeRuleIds[] = $rule->id;
                    }
                }
            }
            
            // Filter to only realtime_enabled rules
            return array_values(array_intersect($allAffected, $realtimeRuleIds));
        }
        
        // Fallback: return all affected (for backward compatibility)
        return $allAffected;
    }
    
    /**
     * Detect if there's a cycle in the dependency graph starting from a field
     */
    public function hasCycle(string $startFieldId): bool
    {
        $visited = [];
        $recursionStack = [];
        
        return $this->hasCycleDFS($startFieldId, $visited, $recursionStack);
    }
    
    /**
     * DFS-based cycle detection
     */
    protected function hasCycleDFS(string $fieldId, array &$visited, array &$recursionStack): bool
    {
        $visited[$fieldId] = true;
        $recursionStack[$fieldId] = true;
        
        // Get fields that depend on this field
        if (isset($this->fieldDependencies[$fieldId])) {
            foreach ($this->fieldDependencies[$fieldId] as $dependentFieldId) {
                if (!isset($visited[$dependentFieldId])) {
                    if ($this->hasCycleDFS($dependentFieldId, $visited, $recursionStack)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$dependentFieldId])) {
                    return true; // Cycle detected
                }
            }
        }
        
        unset($recursionStack[$fieldId]);
        return false;
    }
    
    /**
     * Get the dependency graph as an array (for debugging/visualization)
     */
    public function getGraphAsArray(): array
    {
        return [
            'fieldToRules' => $this->fieldToRules,
            'ruleToFields' => $this->ruleToFields,
            'fieldDependencies' => $this->fieldDependencies,
        ];
    }
}

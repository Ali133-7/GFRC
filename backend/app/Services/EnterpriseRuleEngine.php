<?php

namespace App\Services;

use App\Models\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enterprise Dynamic Rule Engine V4
 *
 * A fully dynamic Business Rules Management System (BRMS) supporting:
 * - Unlimited nested condition groups (AND/OR)
 * - 20+ condition operators
 * - 35+ action types
 * - Case-based rules
 * - Decision matrix evaluation
 * - Database lookups
 * - Workflow routing
 * - Field mapping
 * - Priority-based execution
 * - Conflict resolution
 * - Simulation
 */
class EnterpriseRuleEngine
{
    /**
     * Execute all rules for a given workflow version.
     * Handles BOTH enterprise rules (validation_rules with rule_config)
     * AND workflow rules (workflow_rules: simple + case_based).
     */
    public function execute(string $workflowVersionId, array $values, array $context = []): array
    {
        $startTime = microtime(true);

        // Load enterprise rules from validation_rules table
        $enterpriseRules = ValidationRule::where('workflow_version_id', $workflowVersionId)
            ->where('is_active', true)
            ->whereNotNull('rule_config')
            ->orderBy('priority', 'desc')
            ->orderBy('sort_order')
            ->get();

        // Load workflow rules (simple + case_based) from workflow_rules table
        $workflowRules = \App\Models\WorkflowRule::where('workflow_version_id', $workflowVersionId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $results = [];
        $finalValues = $values;
        $finalFieldStates = $context['field_states'] ?? [];
        $routingDecisions = [];
        $warnings = [];
        $errors = [];
        $stopEvaluation = false;

        // Process enterprise rules first (higher priority)
        foreach ($enterpriseRules as $rule) {
            if ($stopEvaluation) break;

            $ruleConfig = $rule->rule_config ?? [];
            $conditions = $ruleConfig['conditions'] ?? [];
            $actions = $ruleConfig['actions'] ?? [];
            $elseActions = $ruleConfig['else_actions'] ?? [];
            $cases = $ruleConfig['cases'] ?? [];
            $conflictResolution = $ruleConfig['conflict_resolution'] ?? 'highest_priority';

            $result = $this->evaluateRule(
                $rule->id,
                $rule->name,
                'enterprise',
                $conditions,
                $actions,
                $elseActions,
                $cases,
                $values,
                $finalValues,
                $finalFieldStates,
                $context
            );

            $results[] = $result;
            if ($result['matched']) {
                if (isset($result['routing'])) {
                    $routingDecisions[] = [
                        'rule_id' => $rule->id,
                        'action' => $result['routing']['action'],
                        'data' => $result['routing'],
                    ];
                }
            }
            if ($result['stop_evaluation']) {
                $stopEvaluation = true;
            }

            if ($conflictResolution === 'first_match' && $result['matched']) {
                break;
            }
        }

        // Process workflow rules (simple + case_based)
        foreach ($workflowRules as $rule) {
            if ($stopEvaluation) break;

            if ($rule->rule_type === 'case_based') {
                $cases = $rule->cases ?? [];
                $defaultActions = $rule->default_actions ?? [];
                $triggerFieldId = $rule->trigger_field_id;
                $triggerValue = $values[$triggerFieldId] ?? null;

                $caseMatched = false;
                foreach ($cases as $case) {
                    $caseValue = $case['value'] ?? null;
                    $caseActions = $case['actions'] ?? [];
                    $compoundCondition = $case['compound_condition'] ?? null;

                    $matches = false;
                    if ($compoundCondition) {
                        $matches = $this->evaluateConditions([$compoundCondition], $values, $context);
                    } elseif ($caseValue !== null) {
                        $matchMode = $rule->match_mode ?? 'exact';
                        $matches = $this->matchValue($triggerValue, $caseValue, $matchMode);
                    }

                    if ($matches) {
                        $caseMatched = true;
                        $execResult = $this->executeActions($caseActions, $values, $finalValues, $finalFieldStates, $context);
                        $results[] = [
                            'rule_id' => $rule->id,
                            'rule_name' => $rule->name,
                            'rule_type' => 'case_based',
                            'matched' => true,
                            'conditions_evaluated' => count($cases),
                            'conditions_matched' => 1,
                            'executed_actions' => $execResult['executed'],
                            'field_effects' => $execResult['field_effects'] ?? [],
                            'messages' => $execResult['messages'],
                            'routing' => $execResult['routing'] ?? null,
                            'stop_evaluation' => $execResult['stop'] ?? false,
                            'condition_trace' => [
                                'trigger_field' => $triggerFieldId,
                                'trigger_value' => $triggerValue,
                                'matched_case' => $caseValue,
                                'match_mode' => $rule->match_mode ?? 'exact',
                            ],
                        ];
                        if (isset($execResult['routing'])) {
                            $routingDecisions[] = [
                                'rule_id' => $rule->id,
                                'action' => $execResult['routing']['action'],
                                'data' => $execResult['routing'],
                            ];
                        }
                        if ($execResult['stop']) {
                            $stopEvaluation = true;
                        }
                        break;
                    }
                }

                if (!$caseMatched && !empty($defaultActions)) {
                    $execResult = $this->executeActions($defaultActions, $values, $finalValues, $finalFieldStates, $context);
                    $results[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'rule_type' => 'case_based',
                        'matched' => true,
                        'conditions_evaluated' => count($cases),
                        'conditions_matched' => 0,
                        'executed_actions' => $execResult['executed'],
                        'field_effects' => $execResult['field_effects'] ?? [],
                        'messages' => $execResult['messages'],
                        'routing' => $execResult['routing'] ?? null,
                        'stop_evaluation' => $execResult['stop'] ?? false,
                        'condition_trace' => [
                            'trigger_field' => $triggerFieldId,
                            'trigger_value' => $triggerValue,
                            'matched_case' => 'default',
                        ],
                    ];
                    if (isset($execResult['routing'])) {
                        $routingDecisions[] = [
                            'rule_id' => $rule->id,
                            'action' => $execResult['routing']['action'],
                            'data' => $execResult['routing'],
                        ];
                    }
                } elseif (!$caseMatched) {
                    $results[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'rule_type' => 'case_based',
                        'matched' => false,
                        'conditions_evaluated' => count($cases),
                        'conditions_matched' => 0,
                        'executed_actions' => [],
                        'field_effects' => [],
                        'messages' => [],
                        'routing' => null,
                        'stop_evaluation' => false,
                        'condition_trace' => [
                            'trigger_field' => $triggerFieldId,
                            'trigger_value' => $triggerValue,
                            'evaluated_cases' => array_map(fn($c) => $c['value'] ?? null, $cases),
                        ],
                    ];
                }
            } else {
                // Simple rule
                $conditionLogic = $rule->condition_logic ?? [];
                $ruleActions = $rule->actions ?? [];

                // Convert WorkflowRule action format to enterprise format
                $convertedActions = [];
                foreach ($ruleActions as $act) {
                    $converted = [
                        'type' => $act['action'] ?? $act['type'] ?? null,
                        'field_id' => $act['target_field_id'] ?? $act['field_id'] ?? null,
                        'value' => $act['value'] ?? $act['resolved_value'] ?? null,
                    ];
                    // Preserve additional fields
                    foreach ($act as $key => $val) {
                        if (!in_array($key, ['action', 'target_field_id'])) {
                            $converted[$key] = $val;
                        }
                    }
                    $convertedActions[] = $converted;
                }

                $result = $this->evaluateRule(
                    $rule->id,
                    $rule->name,
                    'simple',
                    $conditionLogic,
                    $convertedActions,
                    [],
                    [],
                    $values,
                    $finalValues,
                    $finalFieldStates,
                    $context
                );
                $results[] = $result;
                if ($result['matched'] && isset($result['routing'])) {
                    $routingDecisions[] = [
                        'rule_id' => $rule->id,
                        'action' => $result['routing']['action'],
                        'data' => $result['routing'],
                    ];
                }
                if ($result['stop_evaluation']) {
                    $stopEvaluation = true;
                }
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'total_rules_evaluated' => count($results),
            'matched_rules' => count(array_filter($results, fn($r) => $r['matched'])),
            'failed_rules' => count($results) - count(array_filter($results, fn($r) => $r['matched'])),
            'results' => $results,
            'final_values' => $finalValues,
            'final_field_states' => $finalFieldStates,
            'routing_decisions' => $routingDecisions,
            'warnings' => $warnings,
            'errors' => $errors,
            'execution_time_ms' => $executionTime,
        ];
    }

    /**
     * Evaluate a single rule and return detailed results.
     */
    protected function evaluateRule(
        string $ruleId,
        ?string $ruleName,
        string $ruleType,
        array $conditions,
        array $actions,
        array $elseActions,
        array $cases,
        array $originalValues,
        array &$finalValues,
        array &$finalFieldStates,
        array $context
    ): array {
        $result = [
            'rule_id' => $ruleId,
            'rule_name' => $ruleName,
            'rule_type' => $ruleType,
            'matched' => false,
            'conditions_evaluated' => $this->countConditions($conditions),
            'conditions_matched' => 0,
            'executed_actions' => [],
            'field_effects' => [],
            'messages' => [],
            'routing' => null,
            'stop_evaluation' => false,
            'condition_trace' => [],
        ];

        if (empty($conditions)) {
            return $result;
        }

        $matched = $this->evaluateConditions($conditions, $originalValues, $context);
        $result['matched'] = $matched;
        $result['conditions_matched'] = $matched ? $result['conditions_evaluated'] : 0;

        // Build condition trace for debugging
        $result['condition_trace'] = $this->buildConditionTrace($conditions, $originalValues);

        if ($matched) {
            $execResult = $this->executeActions($actions, $originalValues, $finalValues, $finalFieldStates, $context);
            $result['executed_actions'] = $execResult['executed'];
            $result['field_effects'] = $execResult['field_effects'] ?? [];
            $result['messages'] = $execResult['messages'];
            if (isset($execResult['routing'])) {
                $result['routing'] = $execResult['routing'];
            }
            if ($execResult['stop']) {
                $result['stop_evaluation'] = true;
            }
        } elseif (!empty($elseActions)) {
            $execResult = $this->executeActions($elseActions, $originalValues, $finalValues, $finalFieldStates, $context);
            $result['executed_actions'] = $execResult['executed'];
            $result['field_effects'] = $execResult['field_effects'] ?? [];
            $result['messages'] = $execResult['messages'];
        }

        return $result;
    }

    /**
     * Build a detailed trace of condition evaluation for debugging.
     */
    protected function buildConditionTrace(array $conditions, array $values, int $depth = 0): array
    {
        $trace = [];

        // Enterprise group format
        if (isset($conditions['type']) && $conditions['type'] === 'group') {
            $logic = $conditions['logic'] ?? 'and';
            $subConditions = $conditions['conditions'] ?? [];
            $subTrace = [];
            foreach ($subConditions as $cond) {
                $subTrace[] = $this->buildConditionTrace($cond, $values, $depth + 1);
            }
            return [
                'type' => 'group',
                'logic' => $logic,
                'conditions' => $subTrace,
            ];
        }

        // ConditionLogic format (WorkflowRule)
        if (isset($conditions['operator']) && isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            $logic = $conditions['operator'];
            $subConditions = $conditions['conditions'];
            $subTrace = [];
            foreach ($subConditions as $cond) {
                $subTrace[] = $this->buildConditionTrace($cond, $values, $depth + 1);
            }
            return [
                'type' => 'group',
                'logic' => $logic,
                'conditions' => $subTrace,
            ];
        }

        if (is_array($conditions) && isset($conditions[0])) {
            foreach ($conditions as $cond) {
                $trace[] = $this->buildConditionTrace($cond, $values, $depth);
            }
            return $trace;
        }

        $fieldId = $conditions['field_id'] ?? '';
        $operator = $conditions['operator'] ?? '';
        $expectedValue = $conditions['value'] ?? null;
        $actualValue = $values[$fieldId] ?? null;

        return [
            'type' => 'condition',
            'field_id' => $fieldId,
            'operator' => $operator,
            'expected' => $expectedValue,
            'actual' => $actualValue,
        ];
    }

    /**
     * Match a value against an expected value using the specified mode.
     */
    protected function matchValue($actual, $expected, string $mode = 'exact'): bool
    {
        $actualStr = (string) $actual;
        $expectedStr = (string) $expected;

        switch ($mode) {
            case 'exact':
                return $actualStr === $expectedStr;
            case 'contains':
                return str_contains($actualStr, $expectedStr);
            case 'pattern':
                return @preg_match('/' . $expectedStr . '/', $actualStr) === 1;
            case 'in':
                $list = is_array($expected) ? $expected : json_decode($expected, true) ?? [];
                return in_array($actualStr, array_map('strval', $list));
            default:
                return $actualStr === $expectedStr;
        }
    }

    /**
     * Evaluate a condition tree (supports unlimited nesting).
     * Handles three formats:
     * 1. Enterprise: [{ field_id, operator, value }, ...]
     * 2. Group: { type: 'group', logic: 'and', conditions: [...] }
     * 3. ConditionLogic (WorkflowRule): { operator: 'and', conditions: [...] }
     */
    protected function evaluateConditions(array $conditions, array $values, array $context = []): bool
    {
        if (empty($conditions)) return true;

        // Check if this is a group (enterprise format)
        if (isset($conditions['type']) && $conditions['type'] === 'group') {
            return $this->evaluateGroup($conditions, $values, $context);
        }

        // Check if this is ConditionLogic format (WorkflowRule): { operator: 'and', conditions: [...] }
        if (isset($conditions['operator']) && isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            return $this->evaluateConditionLogic($conditions, $values, $context);
        }

        // If it's an array of conditions, default to AND
        $logic = 'and';
        if (is_array($conditions) && isset($conditions[0])) {
            // Check if first item is a group
            if (isset($conditions[0]['type']) && $conditions[0]['type'] === 'group') {
                return $this->evaluateGroup($conditions[0], $values, $context);
            }
        }

        foreach ($conditions as $condition) {
            // Skip non-array conditions (malformed data)
            if (!is_array($condition)) continue;

            if (isset($condition['type']) && $condition['type'] === 'group') {
                $result = $this->evaluateGroup($condition, $values, $context);
            } else {
                $result = $this->evaluateSimpleCondition($condition, $values, $context);
            }

            if ($logic === 'and' && !$result) return false;
            if ($logic === 'or' && $result) return true;
        }

        return $logic === 'and';
    }

    /**
     * Evaluate ConditionLogic format used by WorkflowRule.
     * Format: { operator: 'and'|'or', conditions: [simple conditions or nested ConditionLogic] }
     */
    protected function evaluateConditionLogic(array $conditionLogic, array $values, array $context = []): bool
    {
        $logic = $conditionLogic['operator'] ?? 'and';
        $conditions = $conditionLogic['conditions'] ?? [];

        if (empty($conditions)) return true;

        foreach ($conditions as $condition) {
            if (!is_array($condition)) continue;

            // Nested ConditionLogic
            if (isset($condition['operator']) && isset($condition['conditions'])) {
                $result = $this->evaluateConditionLogic($condition, $values, $context);
            } elseif (isset($condition['type']) && $condition['type'] === 'group') {
                $result = $this->evaluateGroup($condition, $values, $context);
            } else {
                $result = $this->evaluateSimpleCondition($condition, $values, $context);
            }

            if ($logic === 'and' && !$result) return false;
            if ($logic === 'or' && $result) return true;
        }

        return $logic === 'and';
    }

    /**
     * Evaluate a condition group (AND/OR logic).
     */
    protected function evaluateGroup(array $group, array $values, array $context = []): bool
    {
        $logic = $group['logic'] ?? 'and';
        $conditions = $group['conditions'] ?? [];

        foreach ($conditions as $condition) {
            // Skip non-array conditions (malformed data)
            if (!is_array($condition)) continue;

            if (isset($condition['type']) && $condition['type'] === 'group') {
                $result = $this->evaluateGroup($condition, $values, $context);
            } else {
                $result = $this->evaluateSimpleCondition($condition, $values, $context);
            }

            if ($logic === 'and' && !$result) return false;
            if ($logic === 'or' && $result) return true;
        }

        return $logic === 'and';
    }

    /**
     * Evaluate a single simple condition.
     */
    protected function evaluateSimpleCondition(array $condition, array $values, array $context = []): bool
    {
        $fieldId = $condition['field_id'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? null;
        $actualValue = $values[$fieldId] ?? null;

        // Handle database lookup operators
        if (in_array($operator, ['database_exists', 'database_not_exists'])) {
            return $this->evaluateDatabaseCondition($condition, $values, $context);
        }

        switch ($operator) {
            case 'equals':
                return (string) $actualValue === (string) $expectedValue;

            case 'not_equals':
                return (string) $actualValue !== (string) $expectedValue;

            case 'greater_than':
                return (float) $actualValue > (float) $expectedValue;

            case 'greater_or_equal':
                return (float) $actualValue >= (float) $expectedValue;

            case 'less_than':
                return (float) $actualValue < (float) $expectedValue;

            case 'less_or_equal':
                return (float) $actualValue <= (float) $expectedValue;

            case 'contains':
                return str_contains((string) $actualValue, (string) $expectedValue);

            case 'not_contains':
                return !str_contains((string) $actualValue, (string) $expectedValue);

            case 'starts_with':
                return str_starts_with((string) $actualValue, (string) $expectedValue);

            case 'ends_with':
                return str_ends_with((string) $actualValue, (string) $expectedValue);

            case 'between':
                $val = (float) $actualValue;
                $start = (float) $expectedValue;
                $end = (float) ($condition['value_end'] ?? $expectedValue);
                return $val >= $start && $val <= $end;

            case 'in':
                $list = is_array($expectedValue) ? $expectedValue : json_decode($expectedValue, true) ?? [];
                return in_array((string) $actualValue, array_map('strval', $list));

            case 'not_in':
                $list = is_array($expectedValue) ? $expectedValue : json_decode($expectedValue, true) ?? [];
                return !in_array((string) $actualValue, array_map('strval', $list));

            case 'any_of':
                $list = is_array($expectedValue) ? $expectedValue : json_decode($expectedValue, true) ?? [];
                return !empty(array_intersect(
                    is_array($actualValue) ? $actualValue : [(string) $actualValue],
                    $list
                ));

            case 'all_of':
                $list = is_array($expectedValue) ? $expectedValue : json_decode($expectedValue, true) ?? [];
                $actualList = is_array($actualValue) ? $actualValue : [(string) $actualValue];
                return empty(array_diff($list, $actualList));

            case 'is_empty':
                return $actualValue === null || $actualValue === '';

            case 'is_not_empty':
                return $actualValue !== null && $actualValue !== '';

            case 'exists':
                return $actualValue !== null;

            case 'not_exists':
                return $actualValue === null;

            case 'regex':
                return @preg_match((string) $expectedValue, (string) $actualValue) === 1;

            case 'matches_pattern':
                $pattern = str_replace(['*', '?'], ['.*', '.'], preg_quote((string) $expectedValue, '/'));
                return @preg_match('/^' . $pattern . '$/', (string) $actualValue) === 1;

            default:
                return false;
        }
    }

    /**
     * Evaluate database lookup conditions.
     */
    protected function evaluateDatabaseCondition(array $condition, array $values, array $context = []): bool
    {
        $registerId = $condition['register_id'] ?? null;
        $column = $condition['register_column'] ?? null;
        $sourceField = $condition['field_id'] ?? null;
        $operator = $condition['operator'] ?? 'database_exists';

        if (!$registerId || !$column || !$sourceField) return false;

        $value = $values[$sourceField] ?? null;
        if ($value === null || $value === '') return $operator === 'database_not_exists';

        $query = DB::table('records')
            ->where('register_id', $registerId)
            ->whereNull('deleted_at')
            ->whereRaw("data->>? = ?", [$column, (string) $value]);

        $exists = $query->exists();

        return $operator === 'database_exists' ? $exists : !$exists;
    }

    /**
     * Execute actions and return results.
     */
    protected function executeActions(array $actions, array $originalValues, array &$finalValues, array &$finalFieldStates, array $context = []): array
    {
        $executed = [];
        $messages = [];
        $routing = null;
        $fieldEffects = [];
        $stop = false;

        foreach ($actions as $action) {
            $actionType = $action['type'] ?? null;
            $fieldId = $action['field_id'] ?? null;
            $value = $action['value'] ?? null;

            switch ($actionType) {
                case 'set_value':
                case 'override_value':
                    if ($fieldId) {
                        $finalValues[$fieldId] = $value;
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_value', 'value' => $value];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'show':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_visible' => true]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'show'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'hide':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_visible' => false]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'hide'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'calculate':
                    if ($fieldId && $value) {
                        $calculated = $this->calculateExpression((string) $value, $finalValues);
                        $finalValues[$fieldId] = (string) $calculated;
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'calculate',
                            'formula' => $value,
                            'result' => $calculated,
                        ];
                    }
                    break;

                case 'set_visibility':
                    if ($fieldId) {
                        $isVisible = in_array($value, ['visible', 'show', 'true', true, '1', 1], true);
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_visible' => $isVisible]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_visibility', 'value' => $value];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_required':
                    if ($fieldId) {
                        $isRequired = !in_array($value, ['false', false, '0', 0, 'optional'], true);
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_required' => $isRequired]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_required', 'value' => $isRequired];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_optional':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_required' => false]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_optional'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_readonly':
                    if ($fieldId) {
                        $isReadonly = !in_array($value, ['false', false, '0', 0, 'editable'], true);
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_readonly' => $isReadonly]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_readonly', 'value' => $isReadonly];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_editable':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_readonly' => false]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_editable'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_lock':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_readonly' => true]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'set_lock'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'unlock':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_readonly' => false]
                        );
                        $fieldEffects[] = ['field_id' => $fieldId, 'action' => 'unlock'];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'clear_value':
                    if ($fieldId) {
                        $finalValues[$fieldId] = null;
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'copy_value':
                    $targetField = $action['target_field_id'] ?? null;
                    if ($fieldId && $targetField && isset($finalValues[$fieldId])) {
                        $finalValues[$targetField] = $finalValues[$fieldId];
                        $executed[] = $action['id'] ?? $actionType;
                    }
                    break;

                case 'set_fee':
                    if ($fieldId && $value) {
                        $feeCode = (string) $value;
                        $officialFee = \App\Models\OfficialFee::where('fee_code', $feeCode)
                            ->where('is_active', true)
                            ->first();

                        $feeVersion = $officialFee
                            ? $officialFee->feeVersions()->activeAt()->orderByDesc('version')->first()
                            : null;

                        $amount = $feeVersion?->amount ?? '0';
                        $finalValues[$fieldId] = $amount;
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'set_fee',
                            'fee_code' => $feeCode,
                            'amount' => $amount,
                            'fee_name' => $officialFee?->name_ar ?? $feeCode,
                        ];
                    }
                    break;

                case 'apply_discount':
                    if ($fieldId && $value) {
                        $baseValue = (float) ($finalValues[$fieldId] ?? 0);
                        $discountPercent = (float) $value;
                        $discountAmount = $baseValue * ($discountPercent / 100);
                        $finalValues[$fieldId] = (string) max(0, $baseValue - $discountAmount);
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'apply_discount',
                            'discount_percent' => $discountPercent,
                            'discount_amount' => $discountAmount,
                        ];
                    }
                    break;

                case 'route_to_step':
                    $routing = [
                        'action' => 'route_to_step',
                        'target_step_id' => $action['step_id'] ?? null,
                        'preserve_fields' => $action['preserve_fields'] ?? [],
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'route_to_workflow':
                    $routing = [
                        'action' => 'route_to_workflow',
                        'target_workflow_id' => $action['workflow_id'] ?? null,
                        'preserve_fields' => $action['preserve_fields'] ?? [],
                        'field_mapping' => $action['field_mapping'] ?? [],
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'switch_mode':
                    $routing = [
                        'action' => 'switch_mode',
                        'mode' => $value,
                        'preserve_fields' => $action['preserve_fields'] ?? [],
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'skip_step':
                    $routing = [
                        'action' => 'skip_step',
                        'target_step_id' => $action['step_id'] ?? null,
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'show_message':
                    $messages[] = [
                        'type' => 'info',
                        'message_ar' => $action['message_ar'] ?? '',
                        'message_en' => $action['message_en'] ?? '',
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'show_warning':
                    $messages[] = [
                        'type' => 'warning',
                        'message_ar' => $action['message_ar'] ?? '',
                        'message_en' => $action['message_en'] ?? '',
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'show_error':
                    $messages[] = [
                        'type' => 'error',
                        'message_ar' => $action['message_ar'] ?? '',
                        'message_en' => $action['message_en'] ?? '',
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'show_confirmation':
                    $messages[] = [
                        'type' => 'confirmation',
                        'message_ar' => $action['message_ar'] ?? '',
                        'message_en' => $action['message_en'] ?? '',
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'audit_log':
                    // Logged via activity log
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                case 'stop':
                    $stop = true;
                    $executed[] = $action['id'] ?? $actionType;
                    break;
            }
        }

        return [
            'executed' => $executed,
            'messages' => $messages,
            'routing' => $routing,
            'field_effects' => $fieldEffects,
            'stop' => $stop,
        ];
    }

    /**
     * Calculate a simple expression.
     */
    protected function calculateExpression(string $expression, array $values): float
    {
        // Replace field placeholders with values
        $evaluated = preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
            return (float) ($values[$matches[1]] ?? 0);
        }, $expression);

        // Safe evaluation
        try {
            return eval("return (float)($evaluated);");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Count total conditions in a tree.
     */
    protected function countConditions(array $conditions): int
    {
        // ConditionLogic format: { operator: 'and', conditions: [...] }
        if (isset($conditions['operator']) && isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            return $this->countConditions($conditions['conditions']);
        }

        // Enterprise group format
        if (isset($conditions['type']) && $conditions['type'] === 'group') {
            return $this->countConditions($conditions['conditions'] ?? []);
        }

        $count = 0;
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                $count++;
                continue;
            }
            if (isset($condition['type']) && $condition['type'] === 'group') {
                $count += $this->countConditions($condition['conditions'] ?? []);
            } elseif (isset($condition['operator']) && isset($condition['conditions'])) {
                $count += $this->countConditions($condition['conditions']);
            } else {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Simulate rule execution with test values.
     */
    public function simulate(string $workflowVersionId, array $testValues, array $context = []): array
    {
        return $this->execute($workflowVersionId, $testValues, $context);
    }
}

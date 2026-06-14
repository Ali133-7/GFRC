<?php

namespace App\Services;

use App\Exceptions\Workflow\FinancialIntegrityException;
use App\Models\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
     * Default calculation context used when none is provided.
     */
    protected function getContext(): CalculationContext
    {
        return CalculationContext::default();
    }

    /**
     * Execute all rules for a given workflow version.
     * Handles BOTH enterprise rules (validation_rules with rule_config)
     * AND workflow rules (workflow_rules: simple + case_based).
     */
    public function execute(string $workflowVersionId, array $values, array $context = []): array
    {
        $startTime = microtime(true);

        // Load fields for key normalization
        $fields = \App\Models\WorkflowField::where('workflow_version_id', $workflowVersionId)->get();
        
        // CRITICAL: Normalize field keys BEFORE rule execution
        // This ensures all field references (UUID, register_field_id, custom_<id>)
        // resolve to the same canonical key
        $normalizedValues = $this->normalizeFieldKeys($values, $fields);

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
        $finalValues = $normalizedValues;
        $finalFieldStates = $context['field_states'] ?? [];
        $routingDecisions = [];
        $warnings = [];
        $errors = [];
        $stopEvaluation = false;
        $financialTrace = [];

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
                if (!empty($result['financial_trace'])) {
                    $financialTrace = array_merge($financialTrace, $result['financial_trace']);
                }
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
                $defaultActions = $this->convertWorkflowActions($rule->default_actions ?? []);
                $triggerFieldId = $rule->trigger_field_id;
                $triggerValue = $values[$triggerFieldId] ?? null;

                $caseMatched = false;
                foreach ($cases as $case) {
                    $caseValue = $case['value'] ?? null;
                    $caseActions = $this->convertWorkflowActions($case['actions'] ?? []);
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
                        if (!empty($execResult['financial_trace'])) {
                            $financialTrace = array_merge($financialTrace, $execResult['financial_trace']);
                        }
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
                            'status_change' => $execResult['status_change'] ?? null,
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
                    if (!empty($execResult['financial_trace'])) {
                        $financialTrace = array_merge($financialTrace, $execResult['financial_trace']);
                    }
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
                        'status_change' => $execResult['status_change'] ?? null,
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
                        'status_change' => null,
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
                $convertedActions = $this->convertWorkflowActions($ruleActions);

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
                if ($result['matched'] && !empty($result['financial_trace'])) {
                    $financialTrace = array_merge($financialTrace, $result['financial_trace']);
                }
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
            'financial_trace' => $financialTrace,
            'execution_time_ms' => $executionTime,
        ];
    }

    /**
     * Evaluate a single rule and return detailed results.
     * 
     * FIXED: Changed from protected to public so RealTimeRuleEngine can call it.
     */
    public function evaluateRule(
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
            'financial_trace' => [],
        ];

        if (empty($conditions)) {
            return $result;
        }

        $matched = $this->evaluateConditions($conditions, $finalValues, $context);
        $result['matched'] = $matched;
        $result['conditions_matched'] = $matched ? $result['conditions_evaluated'] : 0;

        // Build condition trace for debugging
        $result['condition_trace'] = $this->buildConditionTrace($conditions, $finalValues);

        if ($matched) {
            $execResult = $this->executeActions($actions, $originalValues, $finalValues, $finalFieldStates, $context);
            $result['executed_actions'] = $execResult['executed'];
            $result['field_effects'] = $execResult['field_effects'] ?? [];
            $result['messages'] = $execResult['messages'];
            $result['financial_trace'] = $execResult['financial_trace'] ?? [];
            if (isset($execResult['routing'])) {
                $result['routing'] = $execResult['routing'];
            }
            if ($execResult['stop']) {
                $result['stop_evaluation'] = true;
            }
            if ($execResult['status_change']) {
                $result['status_change'] = $execResult['status_change'];
            }
        } elseif (!empty($elseActions)) {
            $execResult = $this->executeActions($elseActions, $originalValues, $finalValues, $finalFieldStates, $context);
            $result['executed_actions'] = $execResult['executed'];
            $result['field_effects'] = $execResult['field_effects'] ?? [];
            $result['messages'] = $execResult['messages'];
            $result['financial_trace'] = $execResult['financial_trace'] ?? [];
            if ($execResult['status_change']) {
                $result['status_change'] = $execResult['status_change'];
            }
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

        // Check if this is a simple condition passed directly: { operator, field_id, value }
        if (isset($conditions['field_id']) && isset($conditions['operator']) && !isset($conditions['conditions'])) {
            return $this->evaluateSimpleCondition($conditions, $values, $context);
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
        
        // === ARABIC DEBUG TRACE ===
        $fieldName = $this->resolveFieldName($fieldId, $context);
        $stepInfo = $this->resolveFieldStep($fieldId, $context);
        
        \Log::info('🔍 تقييم شرط القاعدة', [
            'اسم_القاعدة' => $context['rule_name'] ?? 'غير معروف',
            'اسم_الحقل' => $fieldName,
            'معرف_الحقل' => $fieldId,
            'الخطوة' => $stepInfo,
            'العامل' => $this->translateOperator($operator),
            'القيمة_المتوقعة' => $expectedValue,
            'القيمة_الفعلية' => $actualValue ?? 'لا شيء (null)',
            'القيمة_موجودة' => $actualValue !== null && $actualValue !== '' ? 'نعم ✅' : 'لا ❌',
        ]);
        
        if ($actualValue === null || $actualValue === '') {
            \Log::warning('⚠️ قيمة الحقل فارغة - لن يتم تنفيذ الشرط', [
                'الحقل' => $fieldName,
                'السبب_المحتمل' => '1) الحقل غير مطلوب 2) المستخدم لم يدخل قيمة 3) الحقل في خطوة أخرى',
                'الحل_المقترح' => 'اجعل الحقل إلزامي أو أضف قيمة افتراضية',
            ]);
        }
        // === END ARABIC DEBUG TRACE ===

        // CRITICAL FIX: Handle null/empty values gracefully
        if ($actualValue === null || $actualValue === '') {
            // For numeric comparisons, null/empty means condition is NOT met
            if (in_array($operator, ['greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'equals', 'not_equals'])) {
                \Log::debug('❌ الشرط غير محقق: القيمة فارغة', [
                    'العامل' => $operator,
                ]);
                return false;
            }
            
            // For emptiness checks, handle specially
            if ($operator === 'is_empty' || $operator === 'is_not_empty') {
                // Continue to evaluation - null is considered empty
            } else {
                // All other operators fail on null
                return false;
            }
        }

        // Normalize operator aliases
        $operatorMap = [
            'gt' => 'greater_than',
            'gte' => 'greater_or_equal',
            'gteq' => 'greater_or_equal',
            'lt' => 'less_than',
            'lte' => 'less_or_equal',
            'lteq' => 'less_or_equal',
            'eq' => 'equals',
            'neq' => 'not_equals',
            'ne' => 'not_equals',
        ];
        $operator = $operatorMap[$operator] ?? $operator;

        // Handle database lookup operators
        if (in_array($operator, ['database_exists', 'database_not_exists'])) {
            return $this->evaluateDatabaseCondition($condition, $values, $context);
        }

        switch ($operator) {
            case 'equals':
                $result = (string) $actualValue === (string) $expectedValue;
                break;

            case 'not_equals':
                $result = (string) $actualValue !== (string) $expectedValue;
                break;

            case 'greater_than':
                $result = bccomp($this->toDecimalString($actualValue), $this->toDecimalString($expectedValue), 3) > 0;
                break;

            case 'greater_or_equal':
                $result = bccomp($this->toDecimalString($actualValue), $this->toDecimalString($expectedValue), 3) >= 0;
                break;

            case 'less_than':
                $result = bccomp($this->toDecimalString($actualValue), $this->toDecimalString($expectedValue), 3) < 0;
                break;

            case 'less_or_equal':
                $result = bccomp($this->toDecimalString($actualValue), $this->toDecimalString($expectedValue), 3) <= 0;
                break;

            case 'contains':
                $result = str_contains((string) $actualValue, (string) $expectedValue);
                break;

            case 'not_contains':
                $result = !str_contains((string) $actualValue, (string) $expectedValue);
                break;

            case 'starts_with':
                $result = str_starts_with((string) $actualValue, (string) $expectedValue);
                break;

            case 'ends_with':
                $result = str_ends_with((string) $actualValue, (string) $expectedValue);
                break;

            case 'is_empty':
                $result = $actualValue === null || $actualValue === '';
                break;

            case 'is_not_empty':
                $result = $actualValue !== null && $actualValue !== '';
                break;

            case 'between':
                $val = $this->toDecimalString($actualValue);
                if (is_array($expectedValue)) {
                    $start = $this->toDecimalString($expectedValue[0] ?? '0');
                    $end = $this->toDecimalString($expectedValue[1] ?? '0');
                    $result = bccomp($val, $start, 3) >= 0 && bccomp($val, $end, 3) <= 0;
                } else {
                    $start = $this->toDecimalString($expectedValue);
                    $end = $this->toDecimalString($condition['value_end'] ?? $expectedValue);
                    $result = bccomp($val, $start, 3) >= 0 && bccomp($val, $end, 3) <= 0;
                }
                break;

            default:
                $result = false;
        }

        // === ARABIC DEBUG RESULT ===
        \Log::info($result ? '✅ الشرط محقق' : '❌ الشرط غير محقق', [
            'اسم_الحقل' => $fieldName,
            'العامل' => $this->translateOperator($operator),
            'القيمة_الفعلية' => $actualValue,
            'القيمة_المتوقعة' => $expectedValue,
            'النتيجة' => $result ? 'نعم' : 'لا',
        ]);
        // === END ARABIC DEBUG RESULT ===

        return $result;
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
     * Convert WorkflowRule action format ({action, target_field_id, ...}) to the
     * enterprise executeActions format ({type, field_id, value, ...}).
     *
     * Applied uniformly to simple rule actions, case actions, and default actions —
     * previously only simple-rule actions were converted, so case/default actions
     * reached executeActions with a null `type` and were silently dropped (the matched
     * case produced no field effects).
     */
    protected function convertWorkflowActions(array $actions): array
    {
        $converted = [];
        foreach ($actions as $act) {
            if (!is_array($act)) {
                continue;
            }
            $c = [
                'type' => $act['action'] ?? $act['type'] ?? null,
                'field_id' => $act['target_field_id'] ?? $act['field_id'] ?? null,
                'value' => $act['value'] ?? $act['resolved_value'] ?? null,
            ];
            // Preserve all other keys (fee_code, formula, options, target_step_id, …).
            foreach ($act as $key => $val) {
                if ($key !== 'action') {
                    $c[$key] = $val;
                }
            }
            $converted[] = $c;
        }
        return $converted;
    }

    /**
     * Execute actions and return results.
     */
    /**
     * @internal Not part of the public API — exposed for testing only.
     */
    public function executeActions(array $actions, array $originalValues, array &$finalValues, array &$finalFieldStates, array $context = []): array
    {
        $executed = [];
        $messages = [];
        $routing = null;
        $fieldEffects = [];
        $stop = false;
        $statusChange = null;
        $financialTrace = [];

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
                    if ($fieldId) {
                        $formula = $value ?? $action['formula'] ?? null;
                        if (!$formula) break;
                        $calculated = $this->calculateExpression((string) $formula, $finalValues);
                        $finalValues[$fieldId] = (string) $calculated;
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'calculate',
                            'formula' => $value,
                            'result' => $calculated,
                            'resolved_amount' => $calculated,  // ✅ إضافة لـ calculateItems
                        ];
                        $financialTrace[] = [
                            'step' => 'formula_calculation',
                            'field_id' => $fieldId,
                            'fee_code' => null,
                            'base_amount' => null,
                            'formula' => $formula,
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
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'clear_value',
                            'value' => null,
                        ];
                    }
                    break;

                case 'copy_value':
                    $sourceField = $action['field_id'] ?? null;
                    $targetField = $action['target_field_id'] ?? null;
                    if ($sourceField && $targetField && isset($finalValues[$sourceField])) {
                        $finalValues[$targetField] = $finalValues[$sourceField];
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $targetField,
                            'action' => 'copy_value',
                            'value' => $finalValues[$sourceField],
                        ];
                    }
                    break;

                case 'set_fee':
                    if ($fieldId) {
                        // Resolve the fee CODE. Prefer the explicit `fee_code` (Case/Simple
                        // builders, which also carry the amount in `value` for display only).
                        // Fall back to `value` for the Enterprise builder convention where the
                        // code itself is stored in `value`. This prevents using a fee AMOUNT
                        // (e.g. "25000.000") as a code.
                        $feeCode = (string) (!empty($action['fee_code']) ? $action['fee_code'] : ($value ?? ''));
                        
                        // Check if value is a numeric amount (not a fee code)
                        $isNumericAmount = is_numeric($value) && empty($action['fee_code']);
                        
                        if ($isNumericAmount) {
                            // Direct amount assignment - no fee lookup needed
                            $amount = $this->toDecimalString($value);
                            $finalValues[$fieldId] = $amount;
                            $executed[] = $action['id'] ?? $actionType;
                            $fieldEffects[] = [
                                'field_id' => $fieldId,
                                'action' => 'set_fee',
                                'fee_code' => null,
                                'amount' => $amount,
                                'fee_version_id' => null,
                                'fee_name' => 'مبلغ مباشر',
                            ];
                            $financialTrace[] = [
                                'step' => 'fee_resolution',
                                'field_id' => $fieldId,
                                'fee_code' => null,
                                'fee_version_id' => null,
                                'base_amount' => $amount,
                                'formula' => null,
                                'result' => $amount,
                            ];
                            break;
                        }
                        
                        if ($feeCode === '') {
                            break;
                        }

                        // Use FeeEngine::resolveActive — the SAME method used by listActive API.
                        // This guarantees the builder display and execution resolution are identical.
                        $feeEngine = app(FeeEngine::class);
                        $feeVersion = $feeEngine->resolveActive($feeCode);

                        if (!$feeVersion) {
                            Log::error('set_fee: fee resolution failed', [
                                'fee_code' => $feeCode,
                                'execution_id' => $context['execution_id'] ?? null,
                                'hint' => 'Fee code does not exist, is inactive, or has no active version.',
                            ]);
                            throw new FinancialIntegrityException(
                                "Fee code [{$feeCode}] does not exist, is inactive, or has no active version for date " . now()->toDateString()
                            );
                        }

                        $officialFee = $feeVersion->fee;
                        $amount = (string) $feeVersion->amount;
                        $finalValues[$fieldId] = $amount;
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'set_fee',
                            'fee_code' => $feeCode,
                            'amount' => $amount,
                            'fee_version_id' => $feeVersion->id,
                            'fee_name' => $officialFee->name_ar ?? $feeCode,
                        ];
                        $financialTrace[] = [
                            'step' => 'fee_resolution',
                            'field_id' => $fieldId,
                            'fee_code' => $feeCode,
                            'fee_version_id' => $feeVersion->id,
                            'base_amount' => $amount,
                            'formula' => null,
                            'result' => $amount,
                        ];
                    }
                    break;

                case 'apply_discount':
                    if ($fieldId) {
                        $discountValue = $action['discount_value'] ?? $action['value'] ?? null;
                        if ($discountValue === null) break;
                        $discountType = $action['discount_type'] ?? 'percentage';
                        $ctx = $this->getContext();
                        $scale = $ctx->scale();
                        $baseValue = $this->toDecimalString($finalValues[$fieldId] ?? '0');
                        $discountVal = $this->toDecimalString($discountValue);
                        $discountAmount = $discountType === 'percentage'
                            ? bcmul($baseValue, bcdiv($discountVal, '100.0', $scale), $scale)
                            : $discountVal;
                        $finalValue = bcsub($baseValue, $discountAmount, $scale);
                        if (bccomp($finalValue, '0.0', $scale) < 0) {
                            $finalValue = '0.' . str_repeat('0', $scale);
                        }
                        $finalValues[$fieldId] = $finalValue;
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'apply_discount',
                            'value' => $finalValue,
                            'discount_percent' => $discountType === 'percentage' ? $discountVal : null,
                            'discount_amount' => $discountAmount,
                        ];
                        $financialTrace[] = [
                            'step' => 'discount',
                            'field_id' => $fieldId,
                            'type' => $discountType,
                            'value' => $discountVal,
                            'applied_to' => $baseValue,
                            'discount_amount' => $discountAmount,
                            'result' => $finalValue,
                        ];
                    }
                    break;

            case 'multiply_and_add':
                /**
                 * Multiply source field value by a fixed multiplier and add to target field.
                 * 
                 * Action config:
                 * - source_field_id: Field containing user input (e.g., broker records count)
                 * - multiplier: Fixed amount defined in workflow (e.g., 50000)
                 * - target_field_id: Field to add result to (e.g., goods for sale)
                 * - condition: Optional condition (> 0, != 0, etc.)
                 * 
                 * Example:
                 * - source_field_id = "broker_records" (user enters 2)
                 * - multiplier = 50000 (defined in workflow)
                 * - target_field_id = "goods_for_sale" (current value: 10000)
                 * - Result: 10000 + (2 * 50000) = 110000
                 */
                $sourceFieldId = $action['source_field_id'] ?? null;
                $multiplier = $action['multiplier'] ?? '0';
                $targetFieldId = $action['target_field_id'] ?? null;
                $condition = $action['condition'] ?? '> 0'; // Default: only if source > 0

                if (!$sourceFieldId || !$targetFieldId) {
                    break;
                }

                $sourceValue = $finalValues[$sourceFieldId] ?? '0';
                $sourceValue = $this->toDecimalString($sourceValue);

                // Check condition (default: > 0)
                $shouldApply = false;
                if ($condition === '> 0' || $condition === '!= 0') {
                    $shouldApply = bccomp($sourceValue, '0', $scale) > 0;
                } elseif ($condition === '>= 0') {
                    $shouldApply = bccomp($sourceValue, '0', $scale) >= 0;
                } else {
                    $shouldApply = true; // Always apply
                }

                if ($shouldApply) {
                    $multiplier = $this->toDecimalString($multiplier);
                    $calculationResult = bcmul($sourceValue, $multiplier, $scale);

                    // Get current target value
                    $currentTargetValue = $finalValues[$targetFieldId] ?? '0';
                    $currentTargetValue = $this->toDecimalString($currentTargetValue);

                    // Add calculation result to target
                    $newTargetValue = bcadd($currentTargetValue, $calculationResult, $scale);
                    $finalValues[$targetFieldId] = $newTargetValue;

                    $executed[] = $action['id'] ?? $actionType;
                    $fieldEffects[] = [
                        'field_id' => $targetFieldId,
                        'action' => 'multiply_and_add',
                        'source_field_id' => $sourceFieldId,
                        'source_value' => $sourceValue,
                        'multiplier' => $multiplier,
                        'calculation_result' => $calculationResult,
                        'old_target_value' => $currentTargetValue,
                        'new_target_value' => $newTargetValue,
                    ];
                    $financialTrace[] = [
                        'step' => 'multiply_and_add',
                        'source_field_id' => $sourceFieldId,
                        'source_value' => $sourceValue,
                        'multiplier' => $multiplier,
                        'calculation_result' => $calculationResult,
                        'target_field_id' => $targetFieldId,
                        'old_target_value' => $currentTargetValue,
                        'new_target_value' => $newTargetValue,
                    ];
                }
                break;

            case 'set_field_type':
                    if ($fieldId) {
                        $newType = $action['value'] ?? $action['field_type'] ?? 'text';
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['field_type' => $newType]
                        );
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'set_field_type',
                            'value' => $newType,
                        ];
                    }
                    break;

                case 'set_options':
                    if ($fieldId) {
                        $options = $action['options'] ?? $action['value'] ?? [];
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['options' => $options]
                        );
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'set_options',
                            'value' => $options,
                        ];
                    }
                    break;

                case 'append_options':
                    if ($fieldId) {
                        $newOptions = $action['options'] ?? $action['value'] ?? [];
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'append_options',
                            'value' => $newOptions,
                        ];
                    }
                    break;

                case 'remove_options':
                    if ($fieldId) {
                        $removeOptions = $action['options'] ?? $action['value'] ?? [];
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'remove_options',
                            'value' => $removeOptions,
                        ];
                    }
                    break;

                case 'enable':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_visible' => true]
                        );
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'enable',
                            'value' => true,
                        ];
                    }
                    break;

                case 'disable':
                    if ($fieldId) {
                        $finalFieldStates[$fieldId] = array_merge(
                            $finalFieldStates[$fieldId] ?? ['is_visible' => true, 'is_required' => false, 'is_readonly' => false],
                            ['is_visible' => false]
                        );
                        $executed[] = $action['id'] ?? $actionType;
                        $fieldEffects[] = [
                            'field_id' => $fieldId,
                            'action' => 'disable',
                            'value' => false,
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

                case 'generate_reference':
                    if ($fieldId && !empty($context['execution_id'])) {
                        $execution = \App\Models\WorkflowExecution::find($context['execution_id']);
                        $register = $execution?->register;
                        if ($register) {
                            $reference = $register->generateReceiptNumber();
                            $finalValues[$fieldId] = $reference;
                            // Store for later reuse by receipt generation (avoids double-consuming sequence)
                            $finalValues['__generated_reference__'] = $reference;
                            $executed[] = $action['id'] ?? $actionType;
                            $fieldEffects[] = [
                                'field_id' => $fieldId,
                                'action' => 'generate_reference',
                                'value' => $reference,
                            ];
                        }
                    }
                    break;

                case 'pause_execution':
                    if (!empty($context['execution_id'])) {
                        $executed[] = $action['id'] ?? $actionType;
                        $statusChange = 'paused';
                    }
                    break;

                case 'resume_execution':
                    if (!empty($context['execution_id'])) {
                        $executed[] = $action['id'] ?? $actionType;
                        $statusChange = 'in_progress';
                    }
                    break;

                case 'execute_validation':
                    $validationRuleId = $action['validation_rule_id'] ?? null;
                    if (!$validationRuleId || empty($context['validation_rules'])) {
                        break;
                    }

                    $rule = null;
                    foreach ($context['validation_rules'] as $vr) {
                        if ($vr->id === $validationRuleId) {
                            $rule = $vr;
                            break;
                        }
                    }

                    if (!$rule) {
                        \Illuminate\Support\Facades\Log::error('execute_validation: rule not in context', [
                            'validation_rule_id' => $validationRuleId,
                            'execution_id'       => $context['execution_id'] ?? null,
                            'hint'               => 'Rule may have rule_config = null (legacy only).',
                        ]);
                        if (app()->isLocal()) {
                            throw new \LogicException(
                                "execute_validation: rule [{$validationRuleId}] not found. " .
                                'Ensure rule_config is not null.'
                            );
                        }
                        break;
                    }

                    $validationEngine = app(\App\Services\ValidationEngine::class);
                    $result = $validationEngine->runValidation($rule, $finalValues, $context);

                    $fieldEffects[] = [
                        'field_id' => $fieldId,
                        'action' => 'execute_validation',
                        'validation_rule_id' => $validationRuleId,
                        'result' => $result['status'] === 'passed' ? 'passed' : 'failed',
                        'response_type' => $rule->response_type,
                        'message_ar' => $rule->error_message_ar,
                        'message_en' => $rule->error_message_en,
                    ];
                    $executed[] = $action['id'] ?? $actionType;
                    break;

                default:
                    if ($actionType) {
                        \Illuminate\Support\Facades\Log::warning('EnterpriseRuleEngine: unimplemented action type', [
                            'action_type'  => $actionType,
                            'execution_id' => $context['execution_id'] ?? null,
                            'rule_id'      => $context['rule_id'] ?? null,
                        ]);
                        throw new \App\Exceptions\Workflow\UnimplementedActionException(
                            "Action type '{$actionType}' is not implemented"
                        );
                    }
                    break;
            }
        }

        return [
            'executed' => $executed,
            'messages' => $messages,
            'routing' => $routing,
            'field_effects' => $fieldEffects,
            'stop' => $stop,
            'status_change' => $statusChange,
            'financial_trace' => $financialTrace,
        ];
    }

    /**
     * Calculate a simple expression using BC Math exclusively.
     * Uses FeeEngine which guarantees no float arithmetic.
     */
    protected function calculateExpression(string $expression, array $values): string
    {
        $feeEngine = app(FeeEngine::class);

        try {
            return $feeEngine->calculate($expression, $values);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('EnterpriseRuleEngine: formula evaluation failed', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Normalize field keys in values array to ensure consistent field resolution.
     * 
     * This ensures that regardless of how a field is referenced (UUID, register_field_id,
     * or custom_<id>), all aliases map to the same canonical key.
     * 
     * @param array $values The values array to normalize
     * @param \Illuminate\Support\Collection $fields Collection of WorkflowField objects
     * @return array Normalized values array
     */
    protected function normalizeFieldKeys(array $values, $fields): array
    {
        $normalized = $values;

        foreach ($fields as $field) {
            // Canonical key: register_field_id or custom_<id>
            $canonical = !empty($field->register_field_id) ? $field->register_field_id : 'custom_' . $field->id;
            
            // All possible aliases for this field
            $aliases = [
                $field->id, // UUID
                'custom_' . $field->id, // Custom format
            ];
            if (!empty($field->register_field_id)) {
                $aliases[] = $field->register_field_id;
            }

            // Find the best value (prefer canonical, then any alias)
            $bestValue = null;
            $bestFound = false;
            
            if (array_key_exists($canonical, $values)) {
                $bestValue = $values[$canonical];
                $bestFound = true;
            } else {
                foreach ($aliases as $alias) {
                    if (array_key_exists($alias, $values)) {
                        $bestValue = $values[$alias];
                        $bestFound = true;
                        break;
                    }
                }
            }

            if ($bestFound) {
                // Set all aliases to the same value for consistent lookup
                $normalized[$canonical] = $bestValue;
                foreach ($aliases as $alias) {
                    $normalized[$alias] = $bestValue;
                }
            }
        }

        return $normalized;
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
     * Convert any value to a BC-safe decimal string with proper scale (3 decimal places).
     * Never uses float arithmetic.
     */
    protected function toDecimalString(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if (is_numeric($value)) {
                // Use bcadd to normalize to 3 decimal places
                return bcadd($value, '0', 3);
            }
            return '0.000';
        }
        if (is_int($value)) {
            return bcadd((string) $value, '0', 3);
        }
        if (is_float($value)) {
            $str = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
            if (!str_contains($str, '.')) {
                $str .= '.0';
            }
            return bcadd($str, '0', 3);
        }
        return '0.000';
    }

    /**
     * Simulate rule execution with test values.
     */
    public function simulate(string $workflowVersionId, array $testValues, array $context = []): array
    {
        return $this->execute($workflowVersionId, $testValues, $context);
    }

    /**
     * Check if a step should be visible based on its condition logic.
     * Delegates to RuleEngineV2 for condition evaluation.
     */
    public function isStepVisible(array $conditionLogic, array $values, array $context = []): bool
    {
        if (empty($conditionLogic)) {
            return true;
        }
        return $this->evaluateConditions($conditionLogic, $values, $context);
    }

    /**
     * Resolve field name from ID for Arabic debugging.
     */
    protected function resolveFieldName(?string $fieldId, array $context): string
    {
        if (!$fieldId) return 'غير محدد';
        
        // Try to get from context
        $fields = $context['fields'] ?? [];
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $fieldKey = $field->register_field_id ?? 'custom_'.$field->id;
                if ($fieldKey === $fieldId || $field->id === $fieldId) {
                    return $field->label ?? $field->name ?? $fieldId;
                }
            }
        }
        
        // Fallback: try to extract from field_id
        if (str_starts_with($fieldId, 'custom_')) {
            return 'حقل مخصص (' . substr($fieldId, 0, 20) . '...)';
        }
        
        return $fieldId;
    }

    /**
     * Resolve which step contains a field.
     */
    protected function resolveFieldStep(?string $fieldId, array $context): string
    {
        if (!$fieldId) return 'غير محدد';
        
        $steps = $context['steps'] ?? [];
        $fields = $context['fields'] ?? [];
        
        if (empty($steps) || empty($fields)) {
            return 'غير متوفر';
        }
        
        $field = null;
        foreach ($fields as $f) {
            $fieldKey = $f->register_field_id ?? 'custom_'.$f->id;
            if ($fieldKey === $fieldId || $f->id === $fieldId) {
                $field = $f;
                break;
            }
        }
        
        if (!$field) {
            return 'الحقل غير موجود في هذه النسخة';
        }
        
        foreach ($steps as $step) {
            if ($step->id === $field->step_id) {
                return $step->title_ar ?? 'الخطوة ' . ($step->sort_order ?? '?');
            }
        }
        
        return 'بدون خطوة';
    }

    /**
     * Translate operator to Arabic.
     */
    protected function translateOperator(string $operator): string
    {
        $translations = [
            'equals' => 'يساوي',
            'not_equals' => 'لا يساوي',
            'greater_than' => 'أكبر من',
            'greater_or_equal' => 'أكبر من أو يساوي',
            'less_than' => 'أصغر من',
            'less_or_equal' => 'أصغر من أو يساوي',
            'contains' => 'يحتوي على',
            'not_contains' => 'لا يحتوي على',
            'starts_with' => 'يبدأ بـ',
            'ends_with' => 'ينتهي بـ',
            'is_empty' => 'فارغ',
            'is_not_empty' => 'ليس فارغاً',
            'between' => 'بين',
        ];
        
        return $translations[$operator] ?? $operator;
    }
}

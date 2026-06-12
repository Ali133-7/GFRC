<?php

namespace App\Services;

/**
 * @deprecated RuleEngineV2 is deprecated.
 * 
 * Use EnterpriseRuleEngine instead which handles ALL rule types:
 * - Enterprise rules (validation_rules with rule_config)
 * - Simple rules (workflow_rules)
 * - Case-based rules (workflow_rules)
 * 
 * This class is kept for backward compatibility only.
 * All new development should use EnterpriseRuleEngine.
 */
class RuleEngineV2
{
    protected FeeEngine $feeEngine;
    protected ?CalculationContext $context = null;
    protected ?ConditionalBranchingEngine $branchingEngine = null;

    public function __construct(FeeEngine $feeEngine)
    {
        $this->feeEngine = $feeEngine;
    }

    public function setContext(CalculationContext $context): void
    {
        $this->context = $context;
    }

    public function setBranchingEngine(ConditionalBranchingEngine $engine): void
    {
        $this->branchingEngine = $engine;
    }

    protected function getContext(): CalculationContext
    {
        return $this->context ?? CalculationContext::default();
    }

    /**
     * Evaluate all rules against the given values and context.
     *
     * @param array $rules Array of rule arrays with 'condition_logic', 'actions', 'is_active'
     * @param array $values Current field values keyed by field_id
     * @param array $context Additional context (e.g., step_index, register_id)
     * @return array ['actions' => [...], 'matched_rules' => [...]]
     */
    public function evaluate(array $rules, array $values, array $context = []): array
    {
        $matchedRules = [];
        $allActions = [];
        $workingValues = $values;

        foreach ($rules as $rule) {
            if (!($rule['is_active'] ?? true)) {
                continue;
            }

            $ruleType = $rule['rule_type'] ?? 'simple';

            if ($ruleType === 'case_based') {
                // Handle case-based rule
                $caseResult = $this->evaluateCaseRule($rule, $workingValues, $context);
                if ($caseResult['matched_case'] !== null || $caseResult['default_applied']) {
                    $matchedRules[] = [
                        'rule_id' => $rule['id'] ?? null,
                        'name' => $rule['name'] ?? null,
                        'condition_met' => true,
                        'rule_type' => 'case_based',
                        'matched_case' => $caseResult['matched_case'],
                        'default_applied' => $caseResult['default_applied'],
                    ];

                    foreach ($caseResult['actions'] as $action) {
                        $allActions[] = $action;
                        $this->applyActionToWorkingValues($action, $workingValues);
                    }
                }
            } else {
                // Handle simple rule (legacy)
                $conditionMet = $this->evaluateCondition($rule['condition_logic'] ?? [], $workingValues, $context);

                if ($conditionMet) {
                    $matchedRules[] = [
                        'rule_id' => $rule['id'] ?? null,
                        'name' => $rule['name'] ?? null,
                        'condition_met' => true,
                        'rule_type' => 'simple',
                    ];

                    foreach ($rule['actions'] ?? [] as $action) {
                        $resolvedAction = $this->resolveAction($action, $workingValues, $context);
                        $allActions[] = $resolvedAction;
                        $this->applyActionToWorkingValues($resolvedAction, $workingValues);
                    }
                }
            }
        }

        return [
            'actions' => $allActions,
            'matched_rules' => $matchedRules,
        ];
    }

    /**
     * Evaluate a case-based rule.
     */
    protected function evaluateCaseRule(array $rule, array $values, array $context): array
    {
        $triggerFieldId = $rule['trigger_field_id'] ?? null;
        $triggerValue = $triggerFieldId ? ($values[$triggerFieldId] ?? null) : null;
        $matchMode = $rule['match_mode'] ?? 'exact';
        $cases = $rule['cases'] ?? [];
        $executedActions = [];
        $matchedCase = null;

        // Sort cases by priority (descending)
        usort($cases, function ($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });

        foreach ($cases as $case) {
            $caseValue = $case['value'] ?? null;
            $compoundCondition = $case['compound_condition'] ?? null;

            $caseMatches = $this->caseMatches($triggerValue, $caseValue, $matchMode);

            if ($caseMatches && $compoundCondition) {
                $caseMatches = $this->evaluateCondition($compoundCondition, $values, $context);
            }

            if ($caseMatches) {
                $matchedCase = [
                    'value' => $caseValue,
                    'label' => $case['label'] ?? $caseValue,
                    'priority' => $case['priority'] ?? 0,
                ];

                foreach ($case['actions'] ?? [] as $action) {
                    $resolvedAction = $this->resolveAction($action, $values, $context);
                    $executedActions[] = $resolvedAction;
                }

                break;
            }
        }

        $defaultApplied = false;
        if ($matchedCase === null && isset($rule['default_actions']) && is_array($rule['default_actions'])) {
            $defaultApplied = true;
            foreach ($rule['default_actions'] as $action) {
                $resolvedAction = $this->resolveAction($action, $values, $context);
                $executedActions[] = $resolvedAction;
            }
        }

        return [
            'matched_case' => $matchedCase,
            'actions' => $executedActions,
            'default_applied' => $defaultApplied,
        ];
    }

    /**
     * Check if a trigger value matches a case value.
     */
    protected function caseMatches(mixed $triggerValue, mixed $caseValue, string $matchMode): bool
    {
        if ($caseValue === null) {
            return false;
        }

        switch ($matchMode) {
            case 'exact':
                if (is_array($caseValue)) {
                    return in_array((string) $triggerValue, array_map('strval', $caseValue), true);
                }
                return (string) $triggerValue === (string) $caseValue;

            case 'contains':
                $triggerStr = is_array($triggerValue) ? json_encode($triggerValue) : (string) $triggerValue;
                $caseStr = is_array($caseValue) ? json_encode($caseValue) : (string) $caseValue;
                return str_contains($triggerStr, $caseStr);

            case 'pattern':
                $pattern = (string) $caseValue;
                $value = (string) $triggerValue;
                $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';
                return (bool) preg_match($regex, $value);

            case 'in':
                $caseArray = is_array($caseValue) ? $caseValue : (is_string($caseValue) ? json_decode($caseValue, true) : []);
                if (!is_array($caseArray)) return false;
                return in_array((string) $triggerValue, array_map('strval', $caseArray), true);

            default:
                return (string) $triggerValue === (string) $caseValue;
        }
    }

    /**
     * Apply an action to working values.
     */
    protected function applyActionToWorkingValues(array $action, array &$workingValues): void
    {
        $act = $action['action'] ?? $action['type'] ?? '';
        $targetId = $action['target_field_id'] ?? $action['field_id'] ?? null;
        if (!$targetId) return;

        if ($act === 'set_value' || $act === 'override_value') {
            $workingValues[$targetId] = $action['resolved_value'] ?? $action['value'] ?? '';
        } elseif ($act === 'calculate' || $act === 'set_fee') {
            $workingValues[$targetId] = $action['resolved_amount'] ?? '0';
        } elseif ($act === 'apply_discount') {
            $workingValues[$targetId] = $action['resolved_amount'] ?? '0';
        } elseif ($act === 'set_field_type') {
            $workingValues['__field_type__'.$targetId] = $action['resolved_value'] ?? 'text';
        } elseif ($act === 'set_options') {
            $workingValues['__options__'.$targetId] = $action['resolved_value'] ?? [];
        }
    }

    /**
     * Evaluate a single condition (recursive for AND/OR).
     */
    public function evaluateCondition(array $condition, array $values, array $context = []): bool
    {
        $operator = $condition['operator'] ?? 'and';

        // Group operators
        if (in_array($operator, ['and', 'or'], true)) {
            $conditions = $condition['conditions'] ?? [];
            if (empty($conditions)) {
                return true;
            }

            $results = array_map(
                fn($c) => $this->evaluateCondition($c, $values, $context),
                $conditions
            );

            return $operator === 'and'
                ? !in_array(false, $results, true)
                : in_array(true, $results, true);
        }

        // Leaf condition
        $fieldId = $condition['field_id'] ?? null;
        $fieldValue = $fieldId !== null ? ($values[$fieldId] ?? null) : null;
        $compareValue = $condition['value'] ?? null;

        if ($fieldValue === null && !in_array($operator, ['is_empty', 'is_not_empty'], true)) {
            \Illuminate\Support\Facades\Log::warning('RuleEngineV2: null field value in condition', [
                'field_id' => $fieldId,
                'operator' => $operator,
            ]);
            throw new \App\Exceptions\Workflow\RuleEvaluationException("Null value for field {$fieldId} with operator {$operator}");
        }

        return $this->compareValues($fieldValue, $operator, $compareValue);
    }

    /**
     * Compare a field value against a condition.
     * Numeric comparisons use BC math (no float).
     */
    protected function compareValues(mixed $fieldValue, string $operator, mixed $compareValue): bool
    {
        if (in_array($operator, ['in', 'not_in', 'any_of', 'all_of'], true)) {
            $compareArray = is_array($compareValue) ? $compareValue : (is_string($compareValue) ? json_decode($compareValue, true) : []);
            if (!is_array($compareArray)) {
                $compareArray = [(string) $compareValue];
            }
            $compareArray = array_map('strval', $compareArray);

            if (in_array($operator, ['any_of', 'all_of'], true)) {
                $fieldArray = is_array($fieldValue) ? array_map('strval', $fieldValue) : [(string) $fieldValue];
                return match ($operator) {
                    'any_of' => !empty(array_intersect($fieldArray, $compareArray)),
                    'all_of' => empty(array_diff($compareArray, $fieldArray)),
                };
            }

            $fieldValueStr = (string) $fieldValue;
            return match ($operator) {
                'in' => in_array($fieldValueStr, $compareArray, true),
                'not_in' => !in_array($fieldValueStr, $compareArray, true),
            };
        }

        if ($operator === 'between') {
            $range = (array) $compareValue;
            $min = $range[0] ?? '0';
            $max = $range[1] ?? '0';
            return $this->bcCompare($fieldValue, $min) >= 0 && $this->bcCompare($fieldValue, $max) <= 0;
        }

        if (in_array($operator, ['contains', 'not_contains'], true)) {
            $fieldStr = is_array($fieldValue) ? json_encode($fieldValue) : (string) $fieldValue;
            $compareStr = is_array($compareValue) ? json_encode($compareValue) : (string) $compareValue;
            return match ($operator) {
                'contains' => str_contains($fieldStr, $compareStr),
                'not_contains' => !str_contains($fieldStr, $compareStr),
            };
        }

        $fieldValue = $this->normalizeValue($fieldValue);
        $compareValue = $this->normalizeValue($compareValue);

        return match ($operator) {
            'equals' => $this->bcCompareEqual($fieldValue, $compareValue),
            'not_equals' => !$this->bcCompareEqual($fieldValue, $compareValue),
            'starts_with' => str_starts_with((string) $fieldValue, (string) $compareValue),
            'ends_with' => str_ends_with((string) $fieldValue, (string) $compareValue),
            'gt' => $this->bcCompare($fieldValue, $compareValue) > 0,
            'gte' => $this->bcCompare($fieldValue, $compareValue) >= 0,
            'lt' => $this->bcCompare($fieldValue, $compareValue) < 0,
            'lte' => $this->bcCompare($fieldValue, $compareValue) <= 0,
            'is_empty' => empty($fieldValue) && $fieldValue !== '0' && $fieldValue !== 0,
            'is_not_empty' => !empty($fieldValue) || $fieldValue === '0' || $fieldValue === 0,
            default => false,
        };
    }

    /**
     * BC math comparison for numeric values.
     * Falls back to string comparison for non-numeric values.
     */
    protected function bcCompare(mixed $a, mixed $b): int
    {
        $aStr = (string) $a;
        $bStr = (string) $b;

        if (is_numeric($aStr) && is_numeric($bStr)) {
            return bccomp($aStr, $bStr, $this->getContext()->scale());
        }

        return strcmp($aStr, $bStr);
    }

    /**
     * BC math equality comparison.
     */
    protected function bcCompareEqual(mixed $a, mixed $b): bool
    {
        $aStr = (string) $a;
        $bStr = (string) $b;

        if (is_numeric($aStr) && is_numeric($bStr)) {
            return bccomp($aStr, $bStr, $this->getContext()->scale()) === 0;
        }

        return $aStr === $bStr;
    }

    /**
     * Normalize a value for comparison.
     */
    protected function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }

    /**
     * Resolve action values (e.g. fee lookups, calculations).
     */
    protected function resolveAction(array $action, array $values, array $context): array
    {
        $resolved = $action;
        $resolved['action'] = $action['action'] ?? $action['type'] ?? '';
        $ctx = $this->getContext();

        switch ($resolved['action']) {
            case 'set_fee':
                $feeCode = $action['fee_code'] ?? null;
                if ($feeCode) {
                    $feeVersion = $this->feeEngine->resolveActive($feeCode);
                    $amount = $feeVersion?->amount ?? '0';
                    $resolved['resolved_amount'] = $amount;
                    $resolved['fee_name'] = $feeVersion?->fee?->name_ar ?? $feeCode;
                    $resolved['fee_version_id'] = $feeVersion?->id;
                    $ctx->recordFeeSnapshot($feeCode, [
                        'fee_name' => $feeVersion?->fee?->name_ar ?? $feeCode,
                        'amount' => $amount,
                        'version' => $feeVersion?->version,
                        'effective_from' => $feeVersion?->effective_from?->toDateString(),
                    ]);
                }
                break;

            case 'calculate':
                $formula = $action['formula'] ?? '';
                if ($formula) {
                    $resolved['resolved_amount'] = $this->feeEngine->calculate($formula, $values);
                }
                break;

            case 'set_value':
                $resolved['resolved_value'] = $this->resolvePlaceholders($action['value'] ?? '', $values);
                break;

            case 'set_visibility':
                $resolved['resolved_value'] = (bool) ($action['value'] ?? true);
                break;

            case 'set_lock':
                $resolved['resolved_value'] = (bool) ($action['value'] ?? true);
                break;

            case 'set_editable':
                $resolved['resolved_value'] = (bool) ($action['value'] ?? true);
                break;

            case 'set_required':
                $resolved['resolved_value'] = (bool) ($action['value'] ?? true);
                break;

            case 'apply_discount':
                $discountValue = $action['value'] ?? $action['discount_value'] ?? '0';
                $baseFieldId = $action['base_field_id'] ?? $action['target_field_id'] ?? null;
                $baseValue = $baseFieldId ? ($values[$baseFieldId] ?? '0') : '0';
                $resolved['resolved_amount'] = $this->calculateDiscount($baseValue, $discountValue, $action);
                break;

            case 'override_value':
                $resolved['resolved_value'] = $this->resolvePlaceholders($action['value'] ?? $action['override_value'] ?? '', $values);
                break;

            case 'set_field_type':
                $resolved['resolved_value'] = $action['value'] ?? $action['field_type'] ?? 'text';
                break;

            case 'set_options':
                $resolved['resolved_value'] = $action['value'] ?? $action['options'] ?? [];
                break;
        }

        return $resolved;
    }

    protected function calculateDiscount(string $baseValue, mixed $discountValue, array $action): string
    {
        $ctx = $this->getContext();
        $discountType = $action['discount_type'] ?? 'fixed';

        if ($discountType === 'percentage') {
            $discountAmount = bcmul($baseValue, bcdiv((string) $discountValue, '100', $ctx->scale()), $ctx->scale());
            return bcsub($baseValue, $discountAmount, $ctx->scale());
        }

        return bcsub($baseValue, (string) $discountValue, $ctx->scale());
    }

    /**
     * Replace {{field_id}} placeholders in a string.
     */
    protected function resolvePlaceholders(string $value, array $values): string
    {
        return preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
            return (string) ($values[$matches[1]] ?? '');
        }, $value);
    }

    /**
     * Check if a step should be visible based on its condition logic.
     */
    public function isStepVisible(array $conditionLogic, array $values, array $context = []): bool
    {
        if (empty($conditionLogic)) {
            return true;
        }
        return $this->evaluateCondition($conditionLogic, $values, $context);
    }

    /**
     * Check if a field should be visible based on its condition logic.
     */
    public function isFieldVisible(array $conditionLogic, array $values, array $context = []): bool
    {
        if (empty($conditionLogic)) {
            return true;
        }
        return $this->evaluateCondition($conditionLogic, $values, $context);
    }
}

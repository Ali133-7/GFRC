<?php

namespace App\Services;

use App\Models\WorkflowRule;

class ConditionalBranchingEngine
{
    protected RuleEngineV2 $ruleEngine;
    protected FeeEngine $feeEngine;

    public function __construct(RuleEngineV2 $ruleEngine, FeeEngine $feeEngine)
    {
        $this->ruleEngine = $ruleEngine;
        $this->feeEngine = $feeEngine;
    }

    /**
     * Evaluate a case-based rule against given values.
     *
     * @param WorkflowRule $rule The case-based rule
     * @param array $values Current field values
     * @param array $context Additional context
     * @return array ['matched_case' => ..., 'actions' => [...], 'default_applied' => bool]
     */
    public function evaluateCaseRule(WorkflowRule $rule, array $values, array $context = []): array
    {
        if (!$rule->isCaseBased()) {
            return ['matched_case' => null, 'actions' => [], 'default_applied' => false];
        }

        $triggerFieldId = $rule->trigger_field_id;
        $triggerValue = $values[$triggerFieldId] ?? null;
        $matchMode = $rule->match_mode ?? 'exact';
        $cases = $rule->cases_sorted ?? [];
        $executedActions = [];
        $matchedCase = null;

        // Evaluate each case in priority order
        foreach ($cases as $case) {
            $caseValue = $case['value'] ?? null;
            $compoundCondition = $case['compound_condition'] ?? null;

            // Check if this case matches
            $caseMatches = $this->caseMatches($triggerValue, $caseValue, $matchMode);

            // If compound condition exists, it must also pass
            if ($caseMatches && $compoundCondition) {
                $caseMatches = $this->ruleEngine->evaluateCondition($compoundCondition, $values, $context);
            }

            if ($caseMatches) {
                $matchedCase = $case;

                // Execute all actions for this case
                foreach ($case['actions'] ?? [] as $action) {
                    $resolvedAction = $this->resolveAction($action, $values, $context);
                    $executedActions[] = $resolvedAction;
                }

                break; // First matching case wins (switch behavior)
            }
        }

        // If no case matched, apply default actions
        $defaultApplied = false;
        if ($matchedCase === null && is_array($rule->default_actions)) {
            $defaultApplied = true;
            foreach ($rule->default_actions as $action) {
                $resolvedAction = $this->resolveAction($action, $values, $context);
                $executedActions[] = $resolvedAction;
            }
        }

        return [
            'matched_case' => $matchedCase ? [
                'value' => $matchedCase['value'] ?? null,
                'label' => $matchedCase['label'] ?? $matchedCase['value'] ?? null,
                'priority' => $matchedCase['priority'] ?? 0,
            ] : null,
            'actions' => $executedActions,
            'default_applied' => $defaultApplied,
            'trigger_field' => $triggerFieldId,
            'trigger_value' => $triggerValue,
        ];
    }

    /**
     * Check if a trigger value matches a case value based on match mode.
     */
    protected function caseMatches(mixed $triggerValue, mixed $caseValue, string $matchMode): bool
    {
        if ($caseValue === null) {
            return false;
        }

        switch ($matchMode) {
            case 'exact':
                return $this->exactMatch($triggerValue, $caseValue);

            case 'contains':
                return $this->containsMatch($triggerValue, $caseValue);

            case 'pattern':
                return $this->patternMatch($triggerValue, $caseValue);

            case 'in':
                return $this->inMatch($triggerValue, $caseValue);

            default:
                return $this->exactMatch($triggerValue, $caseValue);
        }
    }

    protected function exactMatch(mixed $triggerValue, mixed $caseValue): bool
    {
        if (is_array($caseValue)) {
            return in_array((string) $triggerValue, array_map('strval', $caseValue), true);
        }
        return (string) $triggerValue === (string) $caseValue;
    }

    protected function containsMatch(mixed $triggerValue, mixed $caseValue): bool
    {
        $triggerStr = is_array($triggerValue) ? json_encode($triggerValue) : (string) $triggerValue;
        $caseStr = is_array($caseValue) ? json_encode($caseValue) : (string) $caseValue;
        return str_contains($triggerStr, $caseStr);
    }

    protected function patternMatch(mixed $triggerValue, mixed $caseValue): bool
    {
        $pattern = (string) $caseValue;
        $value = (string) $triggerValue;
        // Handle wildcards: * matches anything
        $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $value);
    }

    protected function inMatch(mixed $triggerValue, mixed $caseValue): bool
    {
        $caseArray = is_array($caseValue) ? $caseValue : (is_string($caseValue) ? json_decode($caseValue, true) : []);
        if (!is_array($caseArray)) {
            return false;
        }
        return in_array((string) $triggerValue, array_map('strval', $caseArray), true);
    }

    /**
     * Resolve action values (delegates to RuleEngineV2 for consistency).
     */
    protected function resolveAction(array $action, array $values, array $context): array
    {
        $resolved = $action;

        switch ($action['action'] ?? '') {
            case 'set_fee':
                $feeCode = $action['fee_code'] ?? null;
                if ($feeCode) {
                    $feeVersion = $this->feeEngine->resolve($feeCode);
                    $amount = $feeVersion?->amount ?? '0';
                    $resolved['resolved_amount'] = $amount;
                    $resolved['fee_name'] = $feeVersion?->fee?->name_ar ?? $feeCode;
                    $resolved['fee_version_id'] = $feeVersion?->id;
                }
                break;

            case 'calculate':
                $formula = $action['formula'] ?? '';
                if ($formula) {
                    $resolved['resolved_amount'] = $this->feeEngine->calculate($formula, $values);
                }
                break;

            case 'set_value':
            case 'override_value':
                $resolved['resolved_value'] = $this->resolvePlaceholders($action['value'] ?? '', $values);
                break;

            case 'set_visibility':
            case 'set_lock':
            case 'set_editable':
            case 'set_required':
                $resolved['resolved_value'] = (bool) ($action['value'] ?? true);
                break;

            case 'apply_discount':
                $discountValue = $action['value'] ?? $action['discount_value'] ?? '0';
                $baseFieldId = $action['base_field_id'] ?? $action['target_field_id'] ?? null;
                $baseValue = $baseFieldId ? ($values[$baseFieldId] ?? '0') : '0';
                $discountType = $action['discount_type'] ?? 'fixed';
                $scale = 3;

                if ($discountType === 'percentage') {
                    $discountAmount = bcmul($baseValue, bcdiv((string) $discountValue, '100', $scale), $scale);
                    $resolved['resolved_amount'] = bcsub($baseValue, $discountAmount, $scale);
                } else {
                    $resolved['resolved_amount'] = bcsub($baseValue, (string) $discountValue, $scale);
                }
                break;

            case 'set_field_type':
                $resolved['resolved_value'] = $action['value'] ?? $action['field_type'] ?? 'text';
                break;

            case 'set_options':
                $resolved['resolved_value'] = $action['value'] ?? $action['options'] ?? [];
                break;

            case 'skip_step':
                $resolved['resolved_value'] = $action['target_step_id'] ?? $action['value'] ?? null;
                break;
        }

        return $resolved;
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
     * Simulate a case-based rule with test values.
     * Returns detailed simulation results for UI preview.
     */
    public function simulate(WorkflowRule $rule, array $testValues, array $context = []): array
    {
        if (!$rule->isCaseBased()) {
            return ['error' => 'Rule is not case-based'];
        }

        $result = $this->evaluateCaseRule($rule, $testValues, $context);
        $cases = $rule->cases_sorted ?? [];

        // Build detailed simulation report
        $caseResults = [];
        foreach ($cases as $case) {
            $caseValue = $case['value'] ?? null;
            $compoundCondition = $case['compound_condition'] ?? null;
            $matches = $this->caseMatches($testValues[$rule->trigger_field_id] ?? null, $caseValue, $rule->match_mode ?? 'exact');

            if ($matches && $compoundCondition) {
                $matches = $this->ruleEngine->evaluateCondition($compoundCondition, $testValues, $context);
            }

            $caseResults[] = [
                'value' => $caseValue,
                'label' => $case['label'] ?? $caseValue,
                'priority' => $case['priority'] ?? 0,
                'matches' => $matches,
                'actions_count' => count($case['actions'] ?? []),
                'actions' => $case['actions'] ?? [],
            ];
        }

        return [
            'rule_name' => $rule->name,
            'rule_type' => $rule->rule_type,
            'trigger_field' => $rule->trigger_field_id,
            'trigger_value' => $testValues[$rule->trigger_field_id] ?? null,
            'match_mode' => $rule->match_mode ?? 'exact',
            'matched_case' => $result['matched_case'],
            'default_applied' => $result['default_applied'],
            'actions_executed' => array_map(fn($a) => $a['action'] ?? '', $result['actions']),
            'resolved_actions' => $result['actions'],
            'all_cases' => $caseResults,
            'fields_affected' => array_values(array_unique(array_map(
                fn($a) => $a['target_field_id'] ?? '',
                $result['actions']
            ))),
        ];
    }
}

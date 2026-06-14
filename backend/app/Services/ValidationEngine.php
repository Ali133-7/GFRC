<?php

namespace App\Services;

use App\Models\Register;
use App\Models\ValidationRule;
use Illuminate\Support\Facades\DB;

class ValidationEngine
{
    /**
     * Run all validation rules for a workflow version against given values.
     *
     * @param string $workflowVersionId
     * @param array $values Field values keyed by field_id
     * @param array $context Additional context
     * @return array ['passed' => bool, 'results' => [...]]
     */
    public function validate(string $workflowVersionId, array $values, array $context = []): array
    {
        $rules = ValidationRule::where('workflow_version_id', $workflowVersionId)
            ->where('is_active', true)
            ->whereNull('rule_config')
            ->orderBy('sort_order')
            ->get();

        \Log::info('ValidationEngine::validate - Starting validation', [
            'workflow_version_id' => $workflowVersionId,
            'rules_count' => $rules->count(),
            'values_keys' => array_keys($values),
        ]);

        $results = [];
        $hasError = false;
        $hasWarning = false;
        $needsConfirmation = false;

        foreach ($rules as $rule) {
            \Log::info('ValidationEngine::validate - Processing rule', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => $rule->validation_type,
            ]);

            $result = $this->runValidation($rule, $values, $context);
            $results[] = $result;

            \Log::info('ValidationEngine::validate - Rule result', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'status' => $result['status'],
            ]);

            if ($result['status'] === 'failed') {
                if ($rule->isError()) {
                    $hasError = true;
                } elseif ($rule->isWarning()) {
                    $hasWarning = true;
                } elseif ($rule->isConfirm()) {
                    $needsConfirmation = true;
                }
            }
        }

        return [
            'passed' => !$hasError,
            'has_warning' => $hasWarning,
            'needs_confirmation' => $needsConfirmation,
            'results' => $results,
        ];
    }

    /**
     * Run a single validation rule.
     */
    public function runValidation(ValidationRule $rule, array $values, array $context): array
    {
        try {
            $result = match ($rule->validation_type) {
                'duplicate_check' => $this->checkDuplicate($rule, $values),
                'exists' => $this->checkExists($rule, $values),
                'not_exists' => $this->checkNotExists($rule, $values),
                'cross_register_check' => $this->checkCrossRegister($rule, $values, $context),
                'dynamic_search' => $this->checkDynamicSearch($rule, $values, $context),
                'multi_field' => $this->checkMultiField($rule, $values),
                'register_search' => $this->checkRegisterSearch($rule, $values),
                'query_builder' => $this->checkQueryBuilder($rule, $values),
                'sql' => $this->checkSql($rule, $values),
                'field_existence_check' => $this->checkFieldExistence($rule, $values),
                default => false,
            };

            // For field_existence_check, return routing info
            if ($rule->validation_type === 'field_existence_check') {
                return $result;
            }

            if ($result) {
                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'validation_type' => $rule->validation_type,
                    'status' => 'failed',
                    'response_type' => $rule->response_type,
                    'message' => $rule->getErrorMessage(),
                    'confirm_message' => $rule->isConfirm() ? $rule->getConfirmMessage() : null,
                ];
            }

            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => $rule->validation_type,
                'status' => 'passed',
            ];
        } catch (\Exception $e) {
            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => $rule->validation_type,
                'status' => 'error',
                'message' => 'خطأ في التحقق: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Duplicate Check: Ensure value doesn't exist in target register.
     */
    protected function checkDuplicate(ValidationRule $rule, array $values): bool
    {
        if (!$rule->target_register_id || empty($rule->target_fields)) {
            \Log::warning('checkDuplicate: Missing target_register_id or target_fields', [
                'rule_id' => $rule->id,
                'target_register_id' => $rule->target_register_id,
                'target_fields' => $rule->target_fields,
            ]);
            return false;
        }

        $targetFields = $rule->target_fields;
        $conditions = [];

        \Log::info('checkDuplicate: Starting validation', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'target_register_id' => $rule->target_register_id,
            'target_fields_count' => count($targetFields),
            'values_keys' => array_keys($values),
        ]);

        foreach ($targetFields as $fieldConfig) {
            $workflowFieldId = $fieldConfig['workflow_field_id'] ?? null;
            $registerFieldName = $fieldConfig['register_field_name'] ?? null;
            $value = $values[$workflowFieldId] ?? null;

            \Log::info('checkDuplicate: Processing field', [
                'workflow_field_id' => $workflowFieldId,
                'register_field_name' => $registerFieldName,
                'value_found' => $value,
                'value_in_values' => isset($values[$workflowFieldId]),
            ]);

            if ($value === null || $registerFieldName === null) {
                \Log::warning('checkDuplicate: Missing value or register_field_name', [
                    'workflow_field_id' => $workflowFieldId,
                    'registerFieldName' => $registerFieldName,
                    'value' => $value,
                ]);
                return false; // Can't validate without all values
            }

            $conditions[] = [$registerFieldName, '=', (string) $value];
        }

        $query = DB::table('records')
            ->where('register_id', $rule->target_register_id)
            ->whereNull('deleted_at');

        foreach ($conditions as $cond) {
            $query->whereRaw("data->>? = ?", [$cond[0], $cond[2]]);
        }

        $count = $query->count();
        
        \Log::info('checkDuplicate: Query result', [
            'count' => $count,
            'conditions' => $conditions,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        return $count > 0;
    }

    /**
     * Exists Validation: Ensure value DOES exist in target register.
     */
    protected function checkExists(ValidationRule $rule, array $values): bool
    {
        if (!$rule->target_register_id || empty($rule->target_fields)) {
            return false;
        }

        $targetFields = $rule->target_fields;
        $conditions = [];

        foreach ($targetFields as $fieldConfig) {
            $workflowFieldId = $fieldConfig['workflow_field_id'] ?? null;
            $registerFieldName = $fieldConfig['register_field_name'] ?? null;
            $value = $values[$workflowFieldId] ?? null;

            if ($value === null || $registerFieldName === null) {
                return true; // Can't validate, assume exists
            }

            $conditions[] = [$registerFieldName, '=', (string) $value];
        }

        $query = DB::table('records')
            ->where('register_id', $rule->target_register_id)
            ->whereNull('deleted_at');

        foreach ($conditions as $cond) {
            $query->whereRaw("data->>? = ?", [$cond[0], $cond[2]]);
        }

        return $query->count() === 0; // Return true (failed) if NOT found
    }

    /**
     * Not Exists Validation: Ensure value does NOT exist in target register.
     *
     * Opposite semantic of checkExists — clearly separated for readability.
     */
    protected function checkNotExists(ValidationRule $rule, array $values): bool
    {
        if (!$rule->target_register_id || empty($rule->target_fields)) {
            return false;
        }

        $targetFields = $rule->target_fields;
        $conditions = [];

        foreach ($targetFields as $fieldConfig) {
            $workflowFieldId = $fieldConfig['workflow_field_id'] ?? null;
            $registerFieldName = $fieldConfig['register_field_name'] ?? null;
            $value = $values[$workflowFieldId] ?? null;

            if ($value === null || $registerFieldName === null) {
                return false; // Can't validate, assume not exists (pass)
            }

            $conditions[] = [$registerFieldName, '=', (string) $value];
        }

        $query = DB::table('records')
            ->where('register_id', $rule->target_register_id)
            ->whereNull('deleted_at');

        foreach ($conditions as $cond) {
            $query->whereRaw("data->>? = ?", [$cond[0], $cond[2]]);
        }

        return $query->count() > 0; // Return true (failed) if FOUND
    }

    /**
     * Cross Register Check: field-to-field match against a record in ANOTHER register.
     *
     * Distinct from exists/not_exists: this does not merely test presence. It locates a
     * specific record in a foreign register by a lookup key, then asserts that one or more
     * of the current values equal fields ON that matched record.
     *
     * Reading a foreign register is permission-gated (read-register-{code}); a caller without
     * that permission fails the check. By security policy the end user is never told WHY the
     * check failed — the rule's own error_message_ar is surfaced, identical to a data mismatch.
     *
     * Outcomes (true = failed / blocks per response_type):
     *   - target register missing or inactive   → failed
     *   - acting user lacks read permission      → failed
     *   - lookup value empty                     → failed (no record can be located)
     *   - no record matches the lookup key       → failed (required cross-reference absent)
     *   - a compared field does not match        → failed
     *   - record found AND every field matches   → passed
     */
    protected function checkCrossRegister(ValidationRule $rule, array $values, array $context): bool
    {
        $lookup = $rule->lookup_config ?? [];
        $matchField = $lookup['match_field'] ?? null;
        $matchWorkflowFieldId = $lookup['match_workflow_field_id'] ?? null;

        if (!$rule->target_register_id || !$matchField || !$matchWorkflowFieldId || empty($rule->target_fields)) {
            return true; // misconfigured — cannot assert the cross-reference, fail closed
        }

        $register = Register::find($rule->target_register_id);
        if (!$register || !$register->is_active) {
            return true;
        }

        // Permission gate: the acting user must be allowed to read this register.
        // Fail closed on a missing user, a missing permission row, or a denied permission.
        $user = $context['acting_user'] ?? null;
        try {
            $allowed = $user !== null && $user->hasPermissionTo("read-register-{$register->code}", 'api');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            $allowed = false;
        }
        if (!$allowed) {
            return true; // no reason leaked to the end user
        }

        // Locate the record in the foreign register by the lookup key.
        $lookupValue = $values[$matchWorkflowFieldId] ?? null;
        if ($lookupValue === null || $lookupValue === '') {
            return true;
        }

        if (!$this->isValidFieldName($matchField)) {
            throw new \InvalidArgumentException("Invalid match_field in cross_register_check: {$matchField}");
        }

        $record = DB::table('records')
            ->where('register_id', $register->id)
            ->whereNull('deleted_at')
            ->whereRaw("data->>? = ?", [$matchField, (string) $lookupValue])
            ->first();

        if (!$record) {
            return true; // no matching record — required cross-reference absent
        }

        $recordData = is_string($record->data) ? json_decode($record->data, true) : ($record->data ?? []);
        $recordData = is_array($recordData) ? $recordData : [];

        // Field-to-field comparison against the matched record.
        foreach ($rule->target_fields as $fieldConfig) {
            $workflowFieldId = $fieldConfig['workflow_field_id'] ?? null;
            $registerFieldName = $fieldConfig['register_field_name'] ?? null;
            $operator = $fieldConfig['operator'] ?? '=';

            if ($workflowFieldId === null || $registerFieldName === null) {
                return true; // incomplete mapping — fail closed
            }

            $currentValue = $values[$workflowFieldId] ?? null;
            $recordValue = $recordData[$registerFieldName] ?? null;

            if (!$this->compareValues($currentValue, $operator, $recordValue)) {
                return true; // mismatch — failed
            }
        }

        return false; // record found and every field matched — passed
    }

    /**
     * Dynamic Search: existence-based check against a single register field.
     *
     * Distinct case from exists/not_exists/cross_register_check for clarity
     * in a government system where readability equals functional correctness.
     *
     * lookup_config:
     *   - search_field: json key inside records.data
     *   - search_workflow_field_id: workflow field that provides the search value
     *
     * expectation:
     *   - must_exist:   record MUST be found → passed, not found → failed
     *   - must_not_exist: record MUST NOT be found → passed, found → failed
     *
     * Permission gate: read-register-{code} required; fail-closed.
     * Null/empty search value → failed (no search possible).
     */
    protected function checkDynamicSearch(ValidationRule $rule, array $values, array $context): bool
    {
        $lookup = $rule->lookup_config ?? [];
        $searchField = $lookup['search_field'] ?? null;
        $searchWorkflowFieldId = $lookup['search_workflow_field_id'] ?? null;
        $expectation = $rule->expectation ?? null;

        if (!$rule->target_register_id || !$searchField || !$searchWorkflowFieldId || !$expectation) {
            return true; // misconfigured — fail closed
        }

        $register = Register::find($rule->target_register_id);
        if (!$register || !$register->is_active) {
            return true; // register missing or inactive — fail closed
        }

        // Permission gate: the acting user must be allowed to read this register.
        // Fail closed on a missing user, a missing permission row, or a denied permission.
        $user = $context['acting_user'] ?? null;
        try {
            $allowed = $user !== null && $user->hasPermissionTo("read-register-{$register->code}", 'api');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            $allowed = false;
        }
        if (!$allowed) {
            return true; // no reason leaked to the end user
        }

        // Resolve search value from submitted values.
        $searchValue = $values[$searchWorkflowFieldId] ?? null;
        if ($searchValue === null || $searchValue === '') {
            return true; // null search value — cannot perform search, fail closed
        }

        if (!$this->isValidFieldName($searchField)) {
            throw new \InvalidArgumentException("Invalid search_field in dynamic_search: {$searchField}");
        }

        $recordExists = DB::table('records')
            ->where('register_id', $register->id)
            ->whereNull('deleted_at')
            ->whereRaw("data->>? = ?", [$searchField, (string) $searchValue])
            ->exists();

        return match ($expectation) {
            'must_exist' => !$recordExists,     // true (failed) when NOT found
            'must_not_exist' => $recordExists,  // true (failed) when found
            default => true,                     // unknown expectation — fail closed
        };
    }

    /**
     * Compare two scalar values with a whitelisted operator.
     * Equality operators compare as strings; ordering operators compare numerically.
     */
    protected function compareValues($left, string $operator, $right): bool
    {
        return match ($operator) {
            '=' => (string) $left === (string) $right,
            '!=' => (string) $left !== (string) $right,
            '>' => (float) $left > (float) $right,
            '>=' => (float) $left >= (float) $right,
            '<' => (float) $left < (float) $right,
            '<=' => (float) $left <= (float) $right,
            default => throw new \InvalidArgumentException("Unsupported operator in cross_register_check: {$operator}"),
        };
    }

    /**
     * Multi Field Validation: Check multiple fields together.
     */
    protected function checkMultiField(ValidationRule $rule, array $values): bool
    {
        return $this->checkDuplicate($rule, $values); // Same logic as duplicate but with multiple fields
    }

    /**
     * Register Search Validation: Search register for matching record.
     */
    protected function checkRegisterSearch(ValidationRule $rule, array $values): bool
    {
        return $this->checkDuplicate($rule, $values);
    }

    /**
     * Query Builder Validation: Execute built query conditions.
     */
    protected function checkQueryBuilder(ValidationRule $rule, array $values): bool
    {
        if (!$rule->target_register_id || empty($rule->query_conditions)) {
            return false;
        }

        $conditions = $rule->query_conditions;
        $query = DB::table('records')
            ->where('register_id', $rule->target_register_id)
            ->whereNull('deleted_at');

        $this->applyQueryConditions($query, $conditions, $values);

        $count = $query->count();

        // For query builder, match found means validation failed (duplicate exists)
        return $count > 0;
    }

    /**
     * Recursively apply query builder conditions.
     */
    protected function applyQueryConditions($query, array $conditions, array $values): void
    {
        $operator = $conditions['operator'] ?? 'and';
        $nestedConditions = $conditions['conditions'] ?? [];

        if (empty($nestedConditions)) {
            return;
        }

        if (in_array($operator, ['and', 'or'], true)) {
            $query->where(function ($q) use ($operator, $nestedConditions, $values) {
                foreach ($nestedConditions as $condition) {
                    if (isset($condition['operator']) && in_array($condition['operator'], ['and', 'or'], true)) {
                        // Nested group
                        $q->where(function ($subQ) use ($condition, $values) {
                            $this->applyQueryConditions($subQ, $condition, $values);
                        }, null, null, $operator === 'or' ? 'or' : 'and');
                    } else {
                        // Leaf condition
                        $field = $condition['field'] ?? null;
                        $op = $condition['op'] ?? '=';
                        $value = $condition['value'] ?? null;

                        // Resolve placeholders
                        if (is_string($value) && preg_match('/\{\{([\w-]+)\}\}/', $value, $matches)) {
                            $value = $values[$matches[1]] ?? $value;
                        }

                        if (!$this->isValidFieldName($field)) {
                            throw new \InvalidArgumentException("Invalid field name in query condition: {$field}");
                        }

                        match ($op) {
                            '=' => $q->whereRaw("data->>? = ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            '!=' => $q->whereRaw("data->>? != ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            '>' => $q->whereRaw("data->>? > ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            '>=' => $q->whereRaw("data->>? >= ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            '<' => $q->whereRaw("data->>? < ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            '<=' => $q->whereRaw("data->>? <= ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                            'like' => $q->whereRaw("data->>? like ?", [$field, "%{$value}%"], $operator === 'or' ? 'or' : 'and'),
                            'in' => $q->whereIn($field, is_array($value) ? $value : json_decode($value, true) ?? [], $operator === 'or' ? 'or' : 'and'),
                            default => $q->whereRaw("data->>? = ?", [$field, $value], $operator === 'or' ? 'or' : 'and'),
                        };
                    }
                }
            });
        }
    }

    /**
     * SQL Validation: DISABLED for security.
     * 
     * Raw SQL execution poses critical injection risk.
     * Use query_builder validation type instead.
     */
    protected function checkSql(ValidationRule $rule, array $values): bool
    {
        // SECURITY: Raw SQL validation is disabled due to injection risk.
        // Treat the rule as failed so it blocks submission rather than silently passing.
        \Log::warning('SQL validation rule attempted but is disabled for security', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
        ]);

        return true;
    }

    /**
     * @deprecated Use WorkflowRoutingEngine instead.
     *             Mixes validation with routing — will be removed in Phase 7 refactor.
     *
     * Field Existence Check: Field-aware lookup with workflow routing.
     *
     * Returns routing decision instead of simple pass/fail.
     */
    protected function checkFieldExistence(ValidationRule $rule, array $values): array
    {
        try {
            \Log::info('[checkFieldExistence] Starting', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'values_keys' => array_keys($values),
            ]);
            
            // Check both trigger_conditions column AND rule_config['conditions']
            $triggerConditions = $rule->trigger_conditions ?? [];
            
            // Support rule_config['conditions'] format (used by workflow builder)
            if (empty($triggerConditions)) {
                $ruleConfig = $rule->rule_config ?? [];
                if (!empty($ruleConfig['conditions']) && is_array($ruleConfig['conditions'])) {
                    $triggerConditions = $ruleConfig['conditions'];
                    \Log::info('[checkFieldExistence] Using rule_config conditions', [
                        'conditions_count' => count($triggerConditions),
                    ]);
                }
            }

              // Support legacy single trigger_field_id
              if (empty($triggerConditions) && $rule->trigger_field_id) {
                  $triggerConditions = [[
                      'field_id' => $rule->trigger_field_id,
                      'operator' => 'exact',
                      'value' => '',
                  ]];
              }
              
              // Get rule actions
              $ruleActions = $rule->rule_config['actions'] ?? $rule->actions ?? [];
              
              // If no trigger conditions, execute actions unconditionally
              if (empty($triggerConditions)) {
                  if (!empty($ruleActions)) {
                      // Execute actions using EnterpriseRuleEngine to get proper field_effects
                      $engine = new EnterpriseRuleEngine();
                      $finalValues = $values;
                      $finalFieldStates = [];
                      // Use original values for calculation, not previously calculated values
                      $actionResults = $engine->executeActions($ruleActions, $values, $finalValues, $finalFieldStates);
                      
                      return [
                          'rule_id' => $rule->id,
                          'rule_name' => $rule->name,
                          'validation_type' => $rule->validation_type,
                          'status' => 'passed',
                          'field_effects' => $actionResults['field_effects'] ?? [],
                          'executed_actions' => $actionResults['executed'] ?? [],
                      ];
                  }
                  
                  // No conditions and no actions - skip
                  return [
                      'rule_id' => $rule->id,
                      'rule_name' => $rule->name,
                      'validation_type' => $rule->validation_type,
                      'status' => 'skipped',
                      'reason' => 'no_trigger_conditions',
                  ];
              }
  
              // Evaluate all trigger conditions - ALL must match
            $allConditionsMet = true;
            $matchedConditions = [];

            \Log::info('[checkFieldExistence] Evaluating conditions', [
                'conditions_count' => count($triggerConditions),
            ]);

            foreach ($triggerConditions as $condition) {
                $fieldId = $condition['field_id'] ?? null;
                $operator = $condition['operator'] ?? 'exact';
                $expectedValue = $condition['value'] ?? null;
                $actualValue = $values[$fieldId] ?? null;

                if (!$fieldId) continue;

                $conditionMet = false;

                switch ($operator) {
                    case 'empty':
                        $conditionMet = ($actualValue === null || $actualValue === '');
                        break;
                    case 'not_empty':
                        $conditionMet = ($actualValue !== null && $actualValue !== '');
                        break;
                    case 'exact':
                        $conditionMet = ((string) $actualValue === (string) $expectedValue);
                        break;
                    case 'not_equals':
                        $conditionMet = ((string) $actualValue !== (string) $expectedValue);
                        break;
                    case 'contains':
                        $conditionMet = str_contains((string) $actualValue, (string) $expectedValue);
                        break;
                    case 'starts_with':
                        $conditionMet = str_starts_with((string) $actualValue, (string) $expectedValue);
                        break;
                    case 'ends_with':
                        $conditionMet = str_ends_with((string) $actualValue, (string) $expectedValue);
                        break;
                    case 'greater_than':
                        $conditionMet = (is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue > (float) $expectedValue));
                        break;
                    case 'less_than':
                        $conditionMet = (is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue < (float) $expectedValue));
                        break;
                    case 'greater_than_or_equal':
                    case 'greater_or_equal':
                        $conditionMet = (is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue >= (float) $expectedValue));
                        break;
                    case 'less_than_or_equal':
                    case 'less_or_equal':
                        $conditionMet = (is_numeric($actualValue) && is_numeric($expectedValue) && ((float) $actualValue <= (float) $expectedValue));
                        break;
                }

                \Log::info('[checkFieldExistence] Condition evaluated', [
                    'field_id' => $fieldId,
                    'operator' => $operator,
                    'expected' => $expectedValue,
                    'actual' => $actualValue,
                    'met' => $conditionMet,
                ]);

                if (!$conditionMet) {
                    $allConditionsMet = false;
                    break;
                }

                $matchedConditions[] = [
                    'field_id' => $fieldId,
                    'value' => $actualValue,
                ];
            }
            
            \Log::info('[checkFieldExistence] All conditions met?', [
                'allConditionsMet' => $allConditionsMet,
            ]);

            if (!$allConditionsMet) {
                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'validation_type' => 'field_existence_check',
                    'status' => 'skipped',
                    'reason' => 'trigger_conditions_not_met',
                ];
            }

            // Conditions met - check if rule has actions to execute
            if (!empty($ruleActions)) {
                \Log::info('[checkFieldExistence] Executing actions', [
                    'actions_count' => count($ruleActions),
                    'actions' => $ruleActions,
                ]);
                
                // Execute actions using EnterpriseRuleEngine to get proper field_effects
                $engine = new EnterpriseRuleEngine();
                $finalValues = $values;
                $finalFieldStates = [];
                // Use original values for calculation, not previously calculated values
                $actionResults = $engine->executeActions($ruleActions, $values, $finalValues, $finalFieldStates);
                
                \Log::info('[checkFieldExistence] Actions executed', [
                    'executed' => $actionResults['executed'] ?? [],
                    'field_effects' => $actionResults['field_effects'] ?? [],
                ]);
                
                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'validation_type' => $rule->validation_type,
                    'status' => 'passed',
                    'field_effects' => $actionResults['field_effects'] ?? [],
                    'executed_actions' => $actionResults['executed'] ?? [],
                ];
            }

            // No actions - continue with legacy database lookup behavior
            $routeConfig = $rule->route_config ?? [];
            $lookupConfig = $rule->lookup_config ?? [];
            $databaseColumn = $lookupConfig['database_column'] ?? null;

            if (!$rule->target_register_id || !$databaseColumn) {
                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'validation_type' => 'field_existence_check',
                    'status' => 'error',
                    'message' => 'إعدادات البحث غير مكتملة',
                ];
            }

            // Build lookup query based on strategy
            $strategy = $lookupConfig['lookup_strategy'] ?? 'exact';
            $query = DB::table('records')
                ->where('register_id', $rule->target_register_id)
                ->whereNull('deleted_at');

            // Use the first matched condition's value for the lookup
            $primaryCondition = $matchedConditions[0] ?? null;
            $triggerValue = $primaryCondition ? $primaryCondition['value'] : null;

            if ($triggerValue !== null) {
                // Use SQLite-compatible JSON extraction
                // SQLite uses json_extract(data, '$.key') or data->'$.key'
                $jsonPath = '$.' . $databaseColumn;
                
                switch ($strategy) {
                    case 'exact':
                        $query->whereRaw("json_extract(data, ?) = ?", [$jsonPath, (string) $triggerValue]);
                        break;
                    case 'contains':
                        $query->whereRaw("json_extract(data, ?) like ?", [$jsonPath, '%' . (string) $triggerValue . '%']);
                        break;
                    case 'starts_with':
                        $query->whereRaw("json_extract(data, ?) like ?", [$jsonPath, (string) $triggerValue . '%']);
                        break;
                    case 'ends_with':
                        $query->whereRaw("json_extract(data, ?) like ?", [$jsonPath, '%' . (string) $triggerValue]);
                        break;
                }
            }

            // Additional conditions from target_fields
            if (!empty($rule->target_fields)) {
                foreach ($rule->target_fields as $fieldConfig) {
                    $wfFieldId = $fieldConfig['workflow_field_id'] ?? null;
                    $dbColumn = $fieldConfig['register_field_name'] ?? null;
                    $val = $values[$wfFieldId] ?? null;
                    if ($val !== null && $dbColumn !== null) {
                        $query->whereRaw("data->>? = ?", [$dbColumn, (string) $val]);
                    }
                }
            }

            $existingRecord = $query->first();

            if ($existingRecord) {
                // Record found → route decision
                $onMatch = $routeConfig['on_match'] ?? [];
                $action = $onMatch['action'] ?? 'warn';

                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'validation_type' => 'field_existence_check',
                    'status' => 'found',
                    'decision' => $action,
                    'existing_record' => [
                        'id' => $existingRecord->id,
                        'register_id' => $existingRecord->register_id,
                        'created_at' => $existingRecord->created_at ?? null,
                    ],
                    'message' => $onMatch['message_ar'] ?? 'تم العثور على سجل سابق مرتبط بهذه القيمة',
                    'actions' => $onMatch['actions'] ?? ['view_existing', 'continue_update', 'start_renewal'],
                    'target_workflow_id' => $onMatch['target_workflow_id'] ?? null,
                    'target_step_id' => $onMatch['target_step_id'] ?? null,
                    'field_effects' => $rule->field_effects ?? [],
                    'existing_record_data' => is_string($existingRecord->data) ? json_decode($existingRecord->data, true) : ($existingRecord->data ?? []),
                ];
            }

            // Record not found → continue
            $onNotFound = $routeConfig['on_not_found'] ?? [];

            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => 'field_existence_check',
                'status' => 'not_found',
                'decision' => 'continue_workflow',
                'message' => $onNotFound['message_ar'] ?? null,
                'field_effects' => [],
            ];
        } catch (\Exception $e) {
            \Log::error('checkFieldExistence failed', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => 'field_existence_check',
                'status' => 'error',
                'message' => 'خطأ في التحقق: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Simulate validation with test values (for UI preview).
     */
    public function simulate(string $workflowVersionId, array $testValues, array $context = []): array
    {
        $rules = ValidationRule::where('workflow_version_id', $workflowVersionId)
            ->where('is_active', true)
            ->whereNull('rule_config')
            ->orderBy('sort_order')
            ->get();

        $results = [];
        foreach ($rules as $rule) {
            $result = $this->runValidation($rule, $testValues, $context);
            $results[] = array_merge($result, [
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'validation_type' => $rule->validation_type,
                    'response_type' => $rule->response_type,
                ],
            ]);
        }

        return [
            'workflow_version_id' => $workflowVersionId,
            'test_values' => $testValues,
            'total_rules' => $rules->count(),
            'passed_count' => count(array_filter($results, fn($r) => $r['status'] === 'passed')),
            'failed_count' => count(array_filter($results, fn($r) => $r['status'] === 'failed')),
            'results' => $results,
        ];
    }

    /**
     * Validate that a field name contains only safe characters.
     */
    protected function isValidFieldName(string $field): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $field);
    }
}

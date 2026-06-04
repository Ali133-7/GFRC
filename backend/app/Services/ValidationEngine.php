<?php

namespace App\Services;

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

        $results = [];
        $hasError = false;
        $hasWarning = false;
        $needsConfirmation = false;

        foreach ($rules as $rule) {
            $result = $this->runValidation($rule, $values, $context);
            $results[] = $result;

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
            return false;
        }

        $targetFields = $rule->target_fields;
        $conditions = [];

        foreach ($targetFields as $fieldConfig) {
            $workflowFieldId = $fieldConfig['workflow_field_id'] ?? null;
            $registerFieldName = $fieldConfig['register_field_name'] ?? null;
            $value = $values[$workflowFieldId] ?? null;

            if ($value === null || $registerFieldName === null) {
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

        return $query->count() > 0;
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

                        $jsonField = "data->>{$field}";

                        match ($op) {
                            '=' => $q->whereRaw("$jsonField = ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            '!=' => $q->whereRaw("$jsonField != ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            '>' => $q->whereRaw("$jsonField > ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            '>=' => $q->whereRaw("$jsonField >= ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            '<' => $q->whereRaw("$jsonField < ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            '<=' => $q->whereRaw("$jsonField <= ?", [$value], $operator === 'or' ? 'or' : 'and'),
                            'like' => $q->whereRaw("$jsonField like ?", ["%{$value}%"], $operator === 'or' ? 'or' : 'and'),
                            'in' => $q->whereIn($field, is_array($value) ? $value : json_decode($value, true) ?? [], $operator === 'or' ? 'or' : 'and'),
                            default => $q->whereRaw("$jsonField = ?", [$value], $operator === 'or' ? 'or' : 'and'),
                        };
                    }
                }
            });
        }
    }

    /**
     * SQL Validation: Execute raw SQL query.
     */
    protected function checkSql(ValidationRule $rule, array $values): bool
    {
        if (empty($rule->sql_query) || empty($rule->sql_condition)) {
            return false;
        }

        // Resolve placeholders in SQL
        $sql = preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
            $val = $values[$matches[1]] ?? '';
            return DB::getPdo()->quote((string) $val);
        }, $rule->sql_query);

        try {
            $result = DB::selectOne($sql);

            // Parse condition: "count = 0" or "total > 5"
            $conditionParts = explode('=', $rule->sql_condition, 2);
            if (count($conditionParts) !== 2) {
                $conditionParts = preg_split('/(>=|<=|!=|>|<)/', $rule->sql_condition, 2, PREG_SPLIT_DELIM_CAPTURE);
            }

            $field = trim($conditionParts[0]);
            $expectedValue = trim($conditionParts[1] ?? '');

            // Get actual value from result
            $actualValue = null;
            foreach ((array) $result as $key => $val) {
                if (stripos($key, $field) !== false || stripos($field, $key) !== false) {
                    $actualValue = $val;
                    break;
                }
            }

            if ($actualValue === null) {
                return false;
            }

            // Check if condition is met
            $conditionMet = match (true) {
                str_contains($rule->sql_condition, '>=') => $actualValue >= (float) $expectedValue,
                str_contains($rule->sql_condition, '<=') => $actualValue <= (float) $expectedValue,
                str_contains($rule->sql_condition, '!=') => $actualValue != (float) $expectedValue,
                str_contains($rule->sql_condition, '>') => $actualValue > (float) $expectedValue,
                str_contains($rule->sql_condition, '<') => $actualValue < (float) $expectedValue,
                default => $actualValue == (float) $expectedValue,
            };

            // Validation fails if condition is NOT met
            return !$conditionMet;
        } catch (\Exception $e) {
            return false; // On error, don't fail validation
        }
    }

    /**
     * Field Existence Check: Field-aware lookup with workflow routing.
     *
     * Returns routing decision instead of simple pass/fail.
     */
    protected function checkFieldExistence(ValidationRule $rule, array $values): array
    {
        $triggerConditions = $rule->trigger_conditions ?? [];

        // Support legacy single trigger_field_id
        if (empty($triggerConditions) && $rule->trigger_field_id) {
            $triggerConditions = [[
                'field_id' => $rule->trigger_field_id,
                'operator' => 'exact',
                'value' => '',
            ]];
        }

        if (empty($triggerConditions)) {
            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => 'field_existence_check',
                'status' => 'skipped',
                'reason' => 'no_trigger_conditions',
            ];
        }

        // Evaluate all trigger conditions - ALL must match
        $allConditionsMet = true;
        $matchedConditions = [];

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
            }

            if (!$conditionMet) {
                $allConditionsMet = false;
                break;
            }

            $matchedConditions[] = [
                'field_id' => $fieldId,
                'value' => $actualValue,
            ];
        }

        if (!$allConditionsMet) {
            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'validation_type' => 'field_existence_check',
                'status' => 'skipped',
                'reason' => 'trigger_conditions_not_met',
            ];
        }

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
            $jsonColumn = "data->>{$databaseColumn}";

            switch ($strategy) {
                case 'exact':
                    $query->whereRaw("$jsonColumn = ?", [(string) $triggerValue]);
                    break;
                case 'contains':
                    $query->whereRaw("$jsonColumn like ?", ['%' . (string) $triggerValue . '%']);
                    break;
                case 'starts_with':
                    $query->whereRaw("$jsonColumn like ?", [(string) $triggerValue . '%']);
                    break;
                case 'ends_with':
                    $query->whereRaw("$jsonColumn like ?", ['%' . (string) $triggerValue]);
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
}

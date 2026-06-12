<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class ConditionalValidationEngine
{
    protected RuleEngineV2 $ruleEngine;

    public function __construct(RuleEngineV2 $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    public function resolveValidationRules(WorkflowField $field, array $values, array $context = []): array
    {
        $baseRules = $field->resolved_validation_rules;
        $conditionalRules = $field->conditional_validation_rules ?? [];

        if (empty($conditionalRules)) {
            return $baseRules;
        }

        $activeRules = $baseRules;

        foreach ($conditionalRules as $rule) {
            $condition = $rule['condition'] ?? [];
            $rulesToAdd = $rule['rules'] ?? [];
            $rulesToRemove = $rule['remove_rules'] ?? [];

            if (empty($condition)) {
                continue;
            }

            $conditionMet = $this->ruleEngine->evaluateCondition($condition, $values, $context);

            if ($conditionMet) {
                $activeRules = array_merge($activeRules, $rulesToAdd);
                $activeRules = array_values(array_diff($activeRules, $rulesToRemove));
            }
        }

        return array_values(array_unique($activeRules));
    }

    public function validateField(WorkflowField $field, mixed $value, array $values, array $context = []): array
    {
        $errors = [];
        $rules = $this->resolveValidationRules($field, $values, $context);
        $fieldType = $field->field_type;
        $fieldName = $field->name;
        $fieldLabel = $field->label;

        foreach ($rules as $rule) {
            $parsed = $this->parseRule($rule);
            $error = $this->applyRule($parsed, $value, $fieldType, $fieldName, $fieldLabel, $values);
            if ($error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    public function validateAll(Collection $fields, array $values, array $context = []): array
    {
        $allErrors = [];

        foreach ($fields as $field) {
            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            $value = $values[$fieldId] ?? null;

            $errors = $this->validateField($field, $value, $values, $context);
            if (!empty($errors)) {
                $allErrors[$fieldId] = $errors;
            }
        }

        return $allErrors;
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            $parts = explode(':', $rule, 2);
            return ['type' => $parts[0], 'param' => $parts[1]];
        }
        return ['type' => $rule, 'param' => null];
    }

    protected function applyRule(array $parsed, mixed $value, string $fieldType, string $name, string $label, array $values): ?string
    {
        $type = $parsed['type'];
        $param = $parsed['param'];

        return match ($type) {
            'required' => $this->validateRequired($value, $label),
            'min' => $this->validateMin($value, $param, $fieldType, $label),
            'max' => $this->validateMax($value, $param, $fieldType, $label),
            'numeric' => $this->validateNumeric($value, $label),
            'email' => $this->validateEmail($value, $label),
            'date' => $this->validateDate($value, $label),
            'in' => $this->validateIn($value, $param, $label),
            'regex' => $this->validateRegex($value, $param, $label),
            'confirmed' => $this->validateConfirmed($value, $name, $values, $label),
            'gte_field' => $this->validateGteField($value, $param, $values, $label),
            'lte_field' => $this->validateLteField($value, $param, $values, $label),
            'equals_field' => $this->validateEqualsField($value, $param, $values, $label),
            'different_field' => $this->validateDifferentField($value, $param, $values, $label),
            default => null,
        };
    }

    protected function validateRequired(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "حقل {$label} مطلوب";
        }
        return null;
    }

    protected function validateMin(mixed $value, ?string $param, string $fieldType, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $min = (int) $param;

        if (in_array($fieldType, ['number', 'decimal'], true) && is_numeric($value)) {
            if ((float) $value < $min) {
                return "حقل {$label} يجب أن يكون {$min} على الأقل";
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) < $min) {
                return "حقل {$label} يجب أن يكون {$min} حرف على الأقل";
            }
        }
        return null;
    }

    protected function validateMax(mixed $value, ?string $param, string $fieldType, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $max = (int) $param;

        if (in_array($fieldType, ['number', 'decimal'], true) && is_numeric($value)) {
            if ((float) $value > $max) {
                return "حقل {$label} يجب أن يكون {$max} كحد أقصى";
            }
        } elseif (is_string($value)) {
            if (mb_strlen($value) > $max) {
                return "حقل {$label} يجب أن يكون {$max} حرف كحد أقصى";
            }
        }
        return null;
    }

    protected function validateNumeric(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return "حقل {$label} يجب أن يكون رقماً";
        }
        return null;
    }

    protected function validateEmail(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            return "حقل {$label} يجب أن يكون بريداً إلكترونياً صحيحاً";
        }
        return null;
    }

    protected function validateDate(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strtotime((string) $value) === false) {
            return "حقل {$label} يجب أن يكون تاريخاً صحيحاً";
        }
        return null;
    }

    protected function validateIn(mixed $value, ?string $param, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $allowed = explode(',', (string) $param);
        if (!in_array((string) $value, $allowed, true)) {
            return "حقل {$label} يجب أن يكون أحد: {$param}";
        }
        return null;
    }

    protected function validateRegex(mixed $value, ?string $param, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($param === null || $param === '') {
            return null;
        }

        // SECURITY: Validate regex syntax before use to prevent ReDoS
        // Only allow safe regex patterns (no nested quantifiers, no backtracking)
        if (!$this->isSafeRegex($param)) {
            \Log::warning('Unsafe regex pattern blocked', [
                'pattern' => $param,
                'label' => $label,
            ]);
            return "حقل {$label} يحتوي على نمط غير آمن";
        }

        // Add delimiters and use error suppression to catch invalid patterns
        $pattern = @preg_match("/{$param}/", (string) $value);
        if ($pattern === false || $pattern === 0) {
            return "حقل {$label} لا يتطابق مع النمط المطلوب";
        }
        return null;
    }

    /**
     * Check if a regex pattern is safe (no ReDoS risk).
     */
    protected function isSafeRegex(string $pattern): bool
    {
        // Block dangerous patterns
        $dangerousPatterns = [
            '/\(\?:.*?\+\)\+/',  // Nested quantifiers
            '/\(\?:.*?\*\)\*/',  // Nested quantifiers
            '/\(\?:.*?\?\)\?/',  // Nested quantifiers
            '/\(\+.*?\+\)/',     // Nested + quantifiers
            '/\(\*.*?\*\)/',     // Nested * quantifiers
            '/\(\?{2,}\)/',      // Multiple quantifiers
            '/\+{2,}/',          // Multiple + quantifiers
            '/\*{2,}/',          // Multiple * quantifiers
            '/\(\?<!.*?\(\?:/',  // Lookbehind with non-capturing group
            '/\(\?=\s*\(\?:/',   // Lookahead with non-capturing group
        ];

        foreach ($dangerousPatterns as $dangerousPattern) {
            if (@preg_match($dangerousPattern, $pattern)) {
                return false;
            }
        }

        // Validate regex syntax
        $testResult = @preg_match("/{$pattern}/", '');
        if ($testResult === false) {
            return false;
        }

        return true;
    }

    protected function validateConfirmed(mixed $value, string $name, array $values, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $confirmKey = $name.'_confirmation';
        $confirmValue = $values[$confirmKey] ?? null;
        if ((string) $value !== (string) $confirmValue) {
            return "حقل {$label} غير متطابق";
        }
        return null;
    }

    protected function validateGteField(mixed $value, ?string $param, array $values, string $label): ?string
    {
        if ($value === null || $value === '' || $param === null) {
            return null;
        }
        $otherValue = $values[$param] ?? null;
        if ($otherValue === null || $otherValue === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $otherValue, 3) < 0) {
            return "حقل {$label} يجب أن يكون أكبر من أو يساوي الحقل المرجعي";
        }
        return null;
    }

    protected function validateLteField(mixed $value, ?string $param, array $values, string $label): ?string
    {
        if ($value === null || $value === '' || $param === null) {
            return null;
        }
        $otherValue = $values[$param] ?? null;
        if ($otherValue === null || $otherValue === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $otherValue, 3) > 0) {
            return "حقل {$label} يجب أن يكون أصغر من أو يساوي الحقل المرجعي";
        }
        return null;
    }

    protected function validateEqualsField(mixed $value, ?string $param, array $values, string $label): ?string
    {
        if ($value === null || $value === '' || $param === null) {
            return null;
        }
        $otherValue = $values[$param] ?? null;
        if ((string) $value !== (string) $otherValue) {
            return "حقل {$label} يجب أن يساوي الحقل المرجعي";
        }
        return null;
    }

    protected function validateDifferentField(mixed $value, ?string $param, array $values, string $label): ?string
    {
        if ($value === null || $value === '' || $param === null) {
            return null;
        }
        $otherValue = $values[$param] ?? null;
        if ((string) $value === (string) $otherValue) {
            return "حقل {$label} يجب أن يختلف عن الحقل المرجعي";
        }
        return null;
    }
}

<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class CrossFieldValidationEngine
{
    protected RuleEngineV2 $ruleEngine;

    public function __construct(RuleEngineV2 $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    public function validateField(WorkflowField $field, mixed $value, array $values, array $context = []): array
    {
        $errors = [];
        $rules = $field->cross_field_validation_rules ?? [];

        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? [];
            $validationType = $rule['type'] ?? '';
            $referenceField = $rule['reference_field_id'] ?? '';
            $message = $rule['message'] ?? $this->getDefaultMessage($validationType, $field->label);

            if (!empty($condition)) {
                $conditionMet = $this->ruleEngine->evaluateCondition($condition, $values, $context);
                if (!$conditionMet) {
                    continue;
                }
            }

            $referenceValue = $values[$referenceField] ?? null;

            $error = $this->applyCrossFieldRule($validationType, $value, $referenceValue, $referenceField, $message);
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
                if (!isset($allErrors[$fieldId])) {
                    $allErrors[$fieldId] = [];
                }
                $allErrors[$fieldId] = array_merge($allErrors[$fieldId], $errors);
            }
        }

        return $allErrors;
    }

    protected function applyCrossFieldRule(string $type, mixed $value, mixed $referenceValue, string $referenceField, string $message): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'gte' => $this->validateGte($value, $referenceValue, $message),
            'gt' => $this->validateGt($value, $referenceValue, $message),
            'lte' => $this->validateLte($value, $referenceValue, $message),
            'lt' => $this->validateLt($value, $referenceValue, $message),
            'equals' => $this->validateEquals($value, $referenceValue, $message),
            'not_equals' => $this->validateNotEquals($value, $referenceValue, $message),
            'before' => $this->validateBefore($value, $referenceValue, $message),
            'after' => $this->validateAfter($value, $referenceValue, $message),
            'requires' => $this->validateRequires($value, $referenceValue, $message),
            'excludes' => $this->validateExcludes($value, $referenceValue, $message),
            default => null,
        };
    }

    protected function validateGte(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $ref, 3) < 0) {
            return $message;
        }
        return null;
    }

    protected function validateGt(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $ref, 3) <= 0) {
            return $message;
        }
        return null;
    }

    protected function validateLte(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $ref, 3) > 0) {
            return $message;
        }
        return null;
    }

    protected function validateLt(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if (bccomp((string) $value, (string) $ref, 3) >= 0) {
            return $message;
        }
        return null;
    }

    protected function validateEquals(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if ((string) $value !== (string) $ref) {
            return $message;
        }
        return null;
    }

    protected function validateNotEquals(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        if ((string) $value === (string) $ref) {
            return $message;
        }
        return null;
    }

    protected function validateBefore(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        $valueTime = strtotime((string) $value);
        $refTime = strtotime((string) $ref);
        if ($valueTime === false || $refTime === false) {
            return null;
        }
        if ($valueTime >= $refTime) {
            return $message;
        }
        return null;
    }

    protected function validateAfter(mixed $value, mixed $ref, string $message): ?string
    {
        if ($ref === null || $ref === '') {
            return null;
        }
        $valueTime = strtotime((string) $value);
        $refTime = strtotime((string) $ref);
        if ($valueTime === false || $refTime === false) {
            return null;
        }
        if ($valueTime <= $refTime) {
            return $message;
        }
        return null;
    }

    protected function validateRequires(mixed $value, mixed $ref, string $message): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($ref === null || $ref === '') {
            return $message;
        }
        return null;
    }

    protected function validateExcludes(mixed $value, mixed $ref, string $message): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($ref !== null && $ref !== '') {
            return $message;
        }
        return null;
    }

    protected function getDefaultMessage(string $type, string $label): string
    {
        return match ($type) {
            'gte' => "حقل {$label} يجب أن يكون أكبر من أو يساوي الحقل المرجعي",
            'gt' => "حقل {$label} يجب أن يكون أكبر من الحقل المرجعي",
            'lte' => "حقل {$label} يجب أن يكون أصغر من أو يساوي الحقل المرجعي",
            'lt' => "حقل {$label} يجب أن يكون أصغر من الحقل المرجعي",
            'equals' => "حقل {$label} يجب أن يساوي الحقل المرجعي",
            'not_equals' => "حقل {$label} يجب أن يختلف عن الحقل المرجعي",
            'before' => "حقل {$label} يجب أن يكون قبل الحقل المرجعي",
            'after' => "حقل {$label} يجب أن يكون بعد الحقل المرجعي",
            'requires' => "حقل {$label} يتطلب تعبئة الحقل المرجعي",
            'excludes' => "حقل {$label} لا يمكن استخدامه مع الحقل المرجعي",
            default => "حقل {$label} غير صالح",
        };
    }
}

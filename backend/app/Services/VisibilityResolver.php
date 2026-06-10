<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class VisibilityResolver
{
    protected RuleEngineV2 $ruleEngine;

    public function __construct(RuleEngineV2 $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    public function resolveFields(Collection $fields, array $values, array $context = []): array
    {
        $visible = [];
        $hidden = [];

        foreach ($fields as $field) {
            if (!$this->isFieldVisible($field, $values, $context)) {
                $hidden[] = $field->register_field_id;
                continue;
            }

            $visible[] = $this->normalizeField($field, $values, $context);
        }

        return [
            'visible' => $visible,
            'hidden' => $hidden,
        ];
    }

    public function isFieldVisible(WorkflowField $field, array $values, array $context = []): bool
    {
        if ($field->is_visible === false) {
            return false;
        }

        $conditionLogic = $field->condition_logic ?? [];
        if (empty($conditionLogic)) {
            return true;
        }

        return $this->ruleEngine->isFieldVisible($conditionLogic, $values, $context);
    }

    public function filterVisibleFields(Collection $fields, array $values, array $context = []): Collection
    {
        return $fields->filter(fn($field) => $this->isFieldVisible($field, $values, $context));
    }

    public function normalizeField(WorkflowField $field, array $values, array $context = []): array
    {
        $rawValue = $values[$field->register_field_id] ?? null;
        $typedValue = $this->parseTypedValue($rawValue, $field->field_type);

        return [
            'field_id' => $field->register_field_id,
            'workflow_field_id' => $field->id,
            'step_id' => $field->step_id,
            'field_type' => $field->field_type,
            'label' => $field->label,
            'name' => $field->registerField?->name ?? '',
            'placeholder' => $field->placeholder,
            'default_value' => $field->default_value,
            'is_required' => $field->is_required,
            'is_visible' => true,
            'is_editable' => $field->is_editable && !$field->is_locked,
            'is_locked' => $field->is_locked,
            'is_readonly' => $field->is_readonly || $field->is_locked,
            'is_financial' => $field->is_financial,
            'is_insured' => $field->is_insured,
            'insurance_value' => $field->insurance_value,
            'priority' => $field->priority,
            'options' => $field->options ?? $field->registerField?->options ?? [],
            'validation_rules' => $field->validation_rules ?? $field->registerField?->validation_rules ?? [],
            'sort_order' => $field->sort_order,
            'fee_code' => $field->fee_code,
            'calculation_formula' => $field->calculation_formula,
            'raw_value' => $rawValue,
            'typed_value' => $typedValue,
            'metadata' => [
                'is_visible' => true,
                'is_locked' => $field->is_locked,
                'is_insured' => $field->is_insured,
                'is_editable' => $field->is_editable && !$field->is_locked,
            ],
        ];
    }

    protected function parseTypedValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'number', 'decimal' => is_numeric($value) ? (float) $value : null,
            'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'date' => $this->parseDate($value),
            'select', 'radio', 'text', 'textarea' => (string) $value,
            default => (string) $value,
        };
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    public function applyFieldControlActions(array $fieldStates, array $actions): array
    {
        foreach ($actions as $action) {
            $targetId = $action['target_field_id'] ?? null;
            if (!$targetId) {
                continue;
            }

            if (!isset($fieldStates[$targetId])) {
                $fieldStates[$targetId] = [
                    'is_visible' => true,
                    'is_editable' => true,
                    'is_locked' => false,
                    'is_required' => false,
                    'is_readonly' => false,
                ];
            }

            switch ($action['action'] ?? '') {
                case 'set_value':
                    break;

                case 'set_visibility':
                    $val = $action['value'] ?? $action['resolved_value'] ?? 'visible';
                    $fieldStates[$targetId]['is_visible'] = in_array($val, ['visible', 'show', 'true', true, '1', 1], true);
                    break;

                case 'hide':
                    $fieldStates[$targetId]['is_visible'] = false;
                    break;

                case 'show':
                    $fieldStates[$targetId]['is_visible'] = true;
                    break;

                case 'enable':
                    $fieldStates[$targetId]['is_visible'] = true;
                    break;

                case 'disable':
                    $fieldStates[$targetId]['is_visible'] = false;
                    break;

                case 'set_lock':
                    $fieldStates[$targetId]['is_locked'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0], true);
                    if ($fieldStates[$targetId]['is_locked']) {
                        $fieldStates[$targetId]['is_editable'] = false;
                        $fieldStates[$targetId]['is_readonly'] = true;
                    }
                    break;

                case 'set_editable':
                    $fieldStates[$targetId]['is_editable'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0], true);
                    break;

                case 'set_required':
                    $fieldStates[$targetId]['is_required'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0, 'optional'], true);
                    break;

                case 'set_readonly':
                    $fieldStates[$targetId]['is_readonly'] = !in_array($action['value'] ?? $action['resolved_value'] ?? true, ['false', false, '0', 0, 'editable'], true);
                    break;
            }
        }

        return $fieldStates;
    }
}

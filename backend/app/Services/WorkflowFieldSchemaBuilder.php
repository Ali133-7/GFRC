<?php

namespace App\Services;

use App\Models\RegisterField;
use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class WorkflowFieldSchemaBuilder
{
    public const VALID_TYPES = [
        'text',
        'textarea',
        'number',
        'decimal',
        'select',
        'multi_select',
        'checkbox',
        'radio',
        'date',
        'datetime',
        'email',
        'phone',
        'url',
    ];

    protected RuleEngineV2 $ruleEngine;
    protected ConditionalValidationEngine $validationEngine;
    protected ComputedFieldEngine $computedEngine;
    protected CascadingSelectEngine $cascadeEngine;
    protected DynamicOptionSource $optionSource;
    protected CrossFieldValidationEngine $crossFieldValidation;
    protected FieldInheritanceResolver $inheritanceResolver;

    public function __construct(
        RuleEngineV2 $ruleEngine,
        ConditionalValidationEngine $validationEngine,
        ComputedFieldEngine $computedEngine,
        CascadingSelectEngine $cascadeEngine,
        DynamicOptionSource $optionSource,
        CrossFieldValidationEngine $crossFieldValidation,
        FieldInheritanceResolver $inheritanceResolver
    ) {
        $this->ruleEngine = $ruleEngine;
        $this->validationEngine = $validationEngine;
        $this->computedEngine = $computedEngine;
        $this->cascadeEngine = $cascadeEngine;
        $this->optionSource = $optionSource;
        $this->crossFieldValidation = $crossFieldValidation;
        $this->inheritanceResolver = $inheritanceResolver;
    }

    public function buildForVersion(Collection $workflowFields, array $values = [], array $context = []): array
    {
        $schema = [];

        foreach ($workflowFields as $wf) {
            $resolved = $this->resolveField($wf, $values, $context);
            $schema[] = $resolved;
        }

        return $this->sortByOrder($schema);
    }

    public function resolveField(WorkflowField $wf, array $values = [], array $context = []): array
    {
        $base = $wf->registerField;
        $isCustom = $wf->register_field_id === null;
        $fieldId = $isCustom ? 'custom_'.$wf->id : $wf->register_field_id;

        // Resolve inheritable properties through FieldInheritanceResolver
        $fieldType = (string) ($this->inheritanceResolver->resolveProperty($wf, $base, 'field_type')['value'] ?? 'text');

        $validationRules = $this->validationEngine->resolveValidationRules($wf, $values, $context);
        $isVisible = $this->resolveVisibility($wf, $values, $context);
        $isLocked = (bool) ($this->inheritanceResolver->resolveProperty($wf, $base, 'is_locked')['value'] ?? false);
        $isEditable = (bool) ($this->inheritanceResolver->resolveProperty($wf, $base, 'is_editable')['value'] ?? true);
        $isRequired = (bool) ($this->inheritanceResolver->resolveProperty($wf, $base, 'is_required')['value'] ?? false);
        $isInsured = (bool) ($this->inheritanceResolver->resolveProperty($wf, $base, 'is_insured')['value'] ?? false);
        $isFinancial = (bool) ($this->inheritanceResolver->resolveProperty($wf, $base, 'is_financial')['value'] ?? false);
        $isComputed = $wf->is_computed || !empty($wf->computed_formula);

        if ($isComputed) {
            $isEditable = false;
            $isLocked = true;
        }

        if ($this->cascadeEngine->isCascading($wf)) {
            $options = $this->cascadeEngine->resolveOptions($wf, $values, $context);
        } elseif ($this->optionSource->hasDynamicSource($wf)) {
            $options = $this->optionSource->resolveOptions($wf, $context);
        } else {
            $options = $this->resolveOptions($wf, $base);
        }

        $rawValue = $values[$fieldId] ?? null;

        if ($isComputed) {
            $rawValue = $this->computedEngine->computeValue($wf, $values, $context) ?? $rawValue;
        }

        $typedValue = $this->parseTypedValue($rawValue, $fieldType, $options);
        $displayValue = $this->resolveDisplayValue($rawValue, $fieldType, $options);

        $schema = [
            'field_id' => $fieldId,
            'workflow_field_id' => $wf->id,
            'step_id' => $wf->step_id,
            'name' => $wf->name,
            'label' => $wf->label,
            'field_type' => $fieldType,
            'placeholder' => $wf->placeholder ?? $base?->name ?? $wf->custom_name ?? '',
            'default_value' => $this->resolveOverride($wf->default_value, $base?->default_value),
            'is_required' => $isRequired,
            'is_visible' => $isVisible,
            'is_editable' => $isEditable && !$isLocked,
            'is_locked' => $isLocked,
            'is_readonly' => ($wf->is_readonly ?? false) || $isLocked,
            'is_financial' => $isFinancial,
            'is_computed' => $isComputed,
            'is_insured' => $isInsured,
            'insurance_value' => $wf->insurance_value,
            'priority' => $wf->priority ?? 0,
            'sort_order' => $wf->sort_order ?? 0,
            'options' => $options,
            'validation_rules' => $validationRules,
            'conditional_validation_rules' => $wf->conditional_validation_rules,
            'cross_field_validation_rules' => $wf->cross_field_validation_rules,
            'condition_logic' => $wf->condition_logic,
            'fee_code' => $wf->fee_code,
            'calculation_formula' => $wf->calculation_formula,
            'computed_formula' => $wf->computed_formula,
            'computed_dependencies' => $this->computedEngine->getDependencies($wf),
            'is_cascading' => $this->cascadeEngine->isCascading($wf),
            'parent_field_id' => $this->cascadeEngine->getParentFieldId($wf),
            'option_source_type' => $wf->option_source_type,
            'is_custom' => $isCustom,
            'value' => [
                'raw' => $rawValue,
                'typed' => $typedValue,
                'display' => $displayValue,
            ],
            'metadata' => [
                'is_visible' => $isVisible,
                'is_locked' => $isLocked,
                'is_editable' => $isEditable && !$isLocked,
                'is_insured' => $isInsured,
                'is_financial' => $isFinancial,
                'is_computed' => $isComputed,
                'field_type' => $fieldType,
                'is_custom' => $isCustom,
                'is_cascading' => $this->cascadeEngine->isCascading($wf),
            ],
        ];

        if ($isComputed) {
            $schema['computed'] = $this->computedEngine->buildComputedFieldSchema($wf, $values, $context);
        }

        return $schema;
    }

    public function resolveDisplayValue(mixed $rawValue, string $fieldType, array $options): mixed
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if ($fieldType === 'select' && !empty($options)) {
            return $this->mapSelectValueToLabel($rawValue, $options);
        }

        if ($fieldType === 'multi_select' && !empty($options)) {
            return $this->mapMultiSelectValuesToLabels($rawValue, $options);
        }

        if ($fieldType === 'checkbox') {
            return filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return (string) $rawValue;
    }

    protected function mapSelectValueToLabel(string $value, array $options): ?string
    {
        foreach ($options as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option['label'] ?? $value;
            }
        }
        return $value;
    }

    protected function mapMultiSelectValuesToLabels(mixed $rawValue, array $options): array
    {
        $values = is_array($rawValue) ? $rawValue : json_decode((string) $rawValue, true);
        if (!is_array($values)) {
            return [];
        }

        $labels = [];
        foreach ($values as $value) {
            $labels[] = $this->mapSelectValueToLabel($value, $options);
        }
        return array_filter($labels);
    }

    protected function resolveOptions(WorkflowField $wf, ?RegisterField $base): array
    {
        $fieldType = (string) ($this->inheritanceResolver->resolveProperty($wf, $base, 'field_type')['value'] ?? 'text');

        if (in_array($fieldType, ['select', 'multi_select'], true)) {
            $resolvedOptions = $this->inheritanceResolver->resolveProperty($wf, $base, 'options');
            $options = $resolvedOptions['value'] ?? [];
            if (is_array($options) && !empty($options)) {
                return $options;
            }
            return [];
        }

        return [];
    }

    protected function resolveVisibility(WorkflowField $wf, array $values, array $context): bool
    {
        if ($wf->is_visible === false) {
            return false;
        }

        $conditionLogic = $wf->condition_logic ?? [];
        if (empty($conditionLogic)) {
            return true;
        }

        return $this->ruleEngine->isFieldVisible($conditionLogic, $values, $context);
    }

    protected function parseTypedValue(mixed $value, string $type, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : null,
            'decimal' => is_numeric($value) ? (string) $value : null,
            'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'date' => $this->parseDate($value),
            'datetime' => $this->parseDateTime($value),
            'select' => (string) $value,
            'radio' => (string) $value,
            'multi_select' => is_array($value) ? $value : json_decode((string) $value, true),
            'text', 'textarea', 'email', 'phone', 'url' => (string) $value,
            default => (string) $value,
        };
    }

    protected function parseDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    protected function resolveBoolOverride(?bool $override, bool $base): bool
    {
        return $override ?? $base;
    }

    protected function resolveOverride(mixed $override, mixed $base, mixed $default = null): mixed
    {
        if ($override !== null) {
            return $override;
        }
        if ($base !== null) {
            return $base;
        }
        return $default;
    }

    protected function sortByOrder(array $schema): array
    {
        usort($schema, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        return $schema;
    }

    public function filterVisible(array $schema): array
    {
        return array_values(array_filter($schema, fn($field) => $field['is_visible'] ?? true));
    }

    public function filterByStep(array $schema, ?string $stepId): array
    {
        if ($stepId === null) {
            return $schema;
        }
        return array_values(array_filter($schema, fn($field) => ($field['step_id'] ?? null) === $stepId));
    }

    public function validateFieldType(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }

    public function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }
}

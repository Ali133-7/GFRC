<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class ComputedFieldEngine
{
    protected FeeEngine $feeEngine;
    protected CalculationContext $ctx;

    public function __construct(FeeEngine $feeEngine, CalculationContext $ctx = null)
    {
        $this->feeEngine = $feeEngine;
        $this->ctx = $ctx ?? CalculationContext::default();
    }

    public function isComputed(WorkflowField $field): bool
    {
        return $field->is_computed || !empty($field->computed_formula);
    }

    public function getDependencies(WorkflowField $field): array
    {
        if (is_array($field->computed_dependencies) && !empty($field->computed_dependencies)) {
            return $field->computed_dependencies;
        }

        $formula = $field->computed_formula ?? $field->calculation_formula ?? '';
        if (empty($formula)) {
            return [];
        }

        preg_match_all('/\{\{([\w-]+)\}\}/', $formula, $matches);
        return array_unique($matches[1] ?? []);
    }

    public function computeValue(WorkflowField $field, array $values, array $context = []): mixed
    {
        $formula = $field->computed_formula ?? $field->calculation_formula ?? '';
        if (empty($formula)) {
            return null;
        }

        $resolved = $this->resolvePlaceholders($formula, $values);

        try {
            $result = $this->feeEngine->calculate($resolved, $values);
            return $result;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findAffectedFields(Collection $allFields, string $changedFieldId): array
    {
        $affected = [];

        foreach ($allFields as $field) {
            if (!$this->isComputed($field)) {
                continue;
            }

            $deps = $this->getDependencies($field);
            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;

            if (in_array($changedFieldId, $deps, true)) {
                $affected[] = $field;
            }
        }

        return $affected;
    }

    public function recalculateAll(Collection $fields, array $values, array $context = []): array
    {
        $computed = [];

        foreach ($fields as $field) {
            if (!$this->isComputed($field)) {
                continue;
            }

            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            $computed[$fieldId] = $this->computeValue($field, $values, $context);
        }

        return $computed;
    }

    public function recalculateChain(Collection $fields, array $values, array $changedFieldIds = [], array $context = []): array
    {
        $computed = [];
        $visited = [];
        $queue = $changedFieldIds;

        while (!empty($queue)) {
            $changedId = array_shift($queue);

            if (isset($visited[$changedId])) {
                continue;
            }
            $visited[$changedId] = true;

            $affected = $this->findAffectedFields($fields, $changedId);

            foreach ($affected as $field) {
                $fieldId = $field->register_field_id ?? 'custom_'.$field->id;

                if (isset($visited[$fieldId])) {
                    continue;
                }

                $computedValue = $this->computeValue($field, $values, $context);
                $computed[$fieldId] = $computedValue;
                $values[$fieldId] = $computedValue;

                $queue[] = $fieldId;
            }
        }

        return $computed;
    }

    public function buildComputedFieldSchema(WorkflowField $field, array $values, array $context = []): array
    {
        $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
        $computedValue = $this->computeValue($field, $values, $context);
        $dependencies = $this->getDependencies($field);

        return [
            'field_id' => $fieldId,
            'is_computed' => true,
            'formula' => $field->computed_formula ?? $field->calculation_formula,
            'dependencies' => $dependencies,
            'computed_value' => $computedValue,
            'is_editable' => false,
            'is_locked' => true,
        ];
    }

    protected function resolvePlaceholders(string $formula, array $values): string
    {
        return preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
            $fieldId = $matches[1];
            $value = $values[$fieldId] ?? '0';
            return is_numeric($value) ? (string) $value : '0';
        }, $formula);
    }
}

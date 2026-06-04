<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class CascadingSelectEngine
{
    public function isCascading(WorkflowField $field): bool
    {
        return !empty($field->parent_field_id) || !empty($field->cascade_config);
    }

    public function getParentFieldId(WorkflowField $field): ?string
    {
        return $field->parent_field_id ?? ($field->cascade_config['parent_field_id'] ?? null);
    }

    public function resolveOptions(WorkflowField $field, array $values, array $context = []): array
    {
        $baseOptions = $field->resolved_options;

        if (!$this->isCascading($field)) {
            return $baseOptions;
        }

        $parentId = $this->getParentFieldId($field);
        if (!$parentId) {
            return $baseOptions;
        }

        $parentValue = $values[$parentId] ?? null;
        if ($parentValue === null || $parentValue === '') {
            return [];
        }

        $cascadeConfig = $field->cascade_config ?? [];
        $mapping = $cascadeConfig['mapping'] ?? [];

        if (!empty($mapping)) {
            return $this->resolveFromMapping($mapping, $parentValue, $baseOptions);
        }

        $parentField = $field->version?->fields?->first(fn($f) => ($f->register_field_id ?? 'custom_'.$f->id) === $parentId);
        if ($parentField) {
            $parentOptions = $parentField->resolved_options;
            return $this->resolveFromNestedOptions($parentOptions, $parentValue);
        }

        return $this->resolveFromNestedOptions($baseOptions, $parentValue);
    }

    public function buildCascadeGraph(Collection $fields): array
    {
        $graph = [];

        foreach ($fields as $field) {
            if (!$this->isCascading($field)) {
                continue;
            }

            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            $parentId = $this->getParentFieldId($field);

            if ($parentId) {
                if (!isset($graph[$parentId])) {
                    $graph[$parentId] = [];
                }
                $graph[$parentId][] = $fieldId;
            }
        }

        return $graph;
    }

    public function getCascadeChain(Collection $fields, string $fieldId): array
    {
        $chain = [];
        $current = null;

        foreach ($fields as $field) {
            $fid = $field->register_field_id ?? 'custom_'.$field->id;
            if ($fid === $fieldId) {
                $current = $field;
                break;
            }
        }

        while ($current) {
            $parentId = $this->getParentFieldId($current);
            if (!$parentId) {
                break;
            }

            $chain[] = $parentId;

            foreach ($fields as $field) {
                $fid = $field->register_field_id ?? 'custom_'.$field->id;
                if ($fid === $parentId) {
                    $current = $field;
                    continue 2;
                }
            }
            break;
        }

        return array_reverse($chain);
    }

    public function validateCascadeIntegrity(Collection $fields): array
    {
        $issues = [];

        foreach ($fields as $field) {
            if (!$this->isCascading($field)) {
                continue;
            }

            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;
            $parentId = $this->getParentFieldId($field);

            if (!$parentId) {
                continue;
            }

            $parentExists = $fields->contains(function ($f) use ($parentId) {
                $fid = $f->register_field_id ?? 'custom_'.$f->id;
                return $fid === $parentId;
            });

            if (!$parentExists) {
                $issues[] = [
                    'field_id' => $fieldId,
                    'issue' => 'parent_not_found',
                    'parent_id' => $parentId,
                    'message' => "Field {$fieldId} references non-existent parent {$parentId}",
                ];
            }
        }

        return $issues;
    }

    protected function resolveFromMapping(array $mapping, string $parentValue, array $baseOptions): array
    {
        $childOptions = $mapping[$parentValue] ?? [];

        if (empty($childOptions)) {
            return [];
        }

        if (is_array($childOptions) && isset($childOptions[0])) {
            return $childOptions;
        }

        return [];
    }

    protected function resolveFromNestedOptions(array $baseOptions, string $parentValue): array
    {
        foreach ($baseOptions as $option) {
            if (($option['value'] ?? null) === $parentValue) {
                return $option['children'] ?? [];
            }
        }

        return [];
    }
}

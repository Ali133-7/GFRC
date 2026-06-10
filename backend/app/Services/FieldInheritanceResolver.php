<?php

namespace App\Services;

use App\Models\RegisterField;
use App\Models\WorkflowField;
use Illuminate\Support\Collection;

/**
 * Field Inheritance Resolver
 *
 * Priority (strict):
 * 1. Workflow Override
 * 2. Workflow Snapshot
 * 3. Register Field (master source of truth)
 * 4. System Default
 *
 * Never silently falls back to 'text' without logging.
 */
class FieldInheritanceResolver
{
    /**
     * Resolve all inheritable properties for a workflow field.
     *
     * @return array{value: mixed, source: string}
     */
    public function resolve(WorkflowField $field): array
    {
        $registerField = $field->registerField;

        $properties = [
            'field_type',
            'options',
            'default_value',
            'placeholder',
            'validation_rules',
            'is_required',
            'is_visible',
            'is_editable',
            'is_locked',
            'is_financial',
            'is_insured',
            'insurance_value',
            'priority',
            'sort_order',
        ];

        $resolved = [];
        foreach ($properties as $prop) {
            $resolved[$prop] = $this->resolveProperty($field, $registerField, $prop);
        }

        // Enforce non-null field_type with audit trail.
        // Any fallback to system_default for field_type is logged — field_type must
        // never silently fall back to 'text' without an audit entry.
        $fieldTypeSource = $resolved['field_type']['source'] ?? 'unknown';
        if (empty($resolved['field_type']['value']) || $fieldTypeSource === 'system_default') {
            \Illuminate\Support\Facades\Log::warning('FieldInheritanceResolver: field_type resolved to null', [
                'workflow_field_id' => $field->id,
                'register_field_id' => $field->register_field_id,
                'source' => $fieldTypeSource,
            ]);
            $resolved['field_type'] = [
                'value' => 'text',
                'source' => 'system_fallback_forced',
            ];
        }

        return $resolved;
    }

    /**
     * Resolve a single property with provenance.
     *
     * @return array{value: mixed, source: string}
     */
    public function resolveProperty(WorkflowField $field, ?RegisterField $registerField, string $property): array
    {
        $workflowOverride = $this->getWorkflowOverride($field, $property);
        if ($workflowOverride !== null) {
            return ['value' => $workflowOverride, 'source' => 'workflow_override'];
        }

        $workflowSnapshot = $this->getWorkflowSnapshot($field, $property);
        if ($workflowSnapshot !== null) {
            return ['value' => $workflowSnapshot, 'source' => 'workflow_snapshot'];
        }

        if ($registerField !== null) {
            $registerValue = $registerField->getAttribute($property);
            if ($registerValue !== null) {
                return ['value' => $registerValue, 'source' => 'register_field'];
            }
        }

        $default = $this->getSystemDefault($property);
        return ['value' => $default, 'source' => 'system_default'];
    }

    private function getWorkflowOverride(WorkflowField $field, string $property): mixed
    {
        // Read raw attribute to avoid accessor recursion when the Resolver is called from an accessor
        $raw = $field->getRawOriginal($property);

        // Custom fields (no register_field_id) use their own stored values as overrides
        if ($field->register_field_id === null) {
            if ($raw !== null && $raw !== '') {
                return $this->castRawValue($property, $raw);
            }
            return null;
        }

        // For linked fields: explicit override = non-null AND non-empty-string
        // (empty string means "inherit from register", not an override)
        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->castRawValue($property, $raw);
    }

    private function getWorkflowSnapshot(WorkflowField $field, string $property): mixed
    {
        // Snapshot values stored in JSON columns during version publish
        $snapshot = $field->getAttribute('snapshot_values');
        if (is_array($snapshot) && array_key_exists($property, $snapshot)) {
            return $snapshot[$property];
        }
        return null;
    }

    private function castRawValue(string $property, mixed $raw): mixed
    {
        return match ($property) {
            'is_required', 'is_visible', 'is_editable', 'is_locked', 'is_financial', 'is_insured' => (bool) $raw,
            'priority', 'sort_order' => (int) $raw,
            'options', 'validation_rules', 'conditional_validation_rules',
            'cross_field_validation_rules', 'computed_dependencies', 'cascade_config'
                => is_string($raw) ? json_decode($raw, true) ?? [] : ($raw ?? []),
            default => $raw,
        };
    }

    private function getSystemDefault(string $property): mixed
    {
        return match ($property) {
            'field_type' => 'text',
            'options' => [],
            'default_value' => null,
            'placeholder' => null,
            'validation_rules' => [],
            'is_required' => false,
            'is_visible' => true,
            'is_editable' => true,
            'is_locked' => false,
            'is_financial' => false,
            'is_insured' => false,
            'insurance_value' => null,
            'priority' => 0,
            'sort_order' => 0,
            default => null,
        };
    }

    /**
     * Bulk resolve for a collection of workflow fields.
     *
     * @param Collection<int, WorkflowField> $fields
     * @return array<string, array>
     */
    public function resolveCollection(Collection $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field->id] = $this->resolve($field);
        }
        return $result;
    }
}

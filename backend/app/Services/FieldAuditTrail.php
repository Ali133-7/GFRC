<?php

namespace App\Services;

use App\Models\WorkflowField;
use Illuminate\Support\Collection;

class FieldAuditTrail
{
    protected array $trail = [];

    public function recordChange(
        string $executionId,
        string $fieldId,
        string $fieldName,
        string $fieldLabel,
        mixed $oldValue,
        mixed $newValue,
        string $changedBy,
        string $reason = '',
        array $context = []
    ): array {
        $entry = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'execution_id' => $executionId,
            'field_id' => $fieldId,
            'field_name' => $fieldName,
            'field_label' => $fieldLabel,
            'old_value' => $this->serializeValue($oldValue),
            'new_value' => $this->serializeValue($newValue),
            'changed_by' => $changedBy,
            'changed_at' => now()->toIso8601String(),
            'reason' => $reason,
            'context' => $context,
            'has_changed' => $this->hasValueChanged($oldValue, $newValue),
        ];

        $this->trail[] = $entry;
        return $entry;
    }

    public function recordFieldChanges(
        string $executionId,
        Collection $fields,
        array $oldValues,
        array $newValues,
        string $changedBy,
        string $reason = ''
    ): array {
        $changes = [];

        foreach ($fields as $field) {
            $fieldId = $field->register_field_id ?? 'custom_'.$field->id;

            $oldValue = $oldValues[$fieldId] ?? null;
            $newValue = $newValues[$fieldId] ?? null;

            if (!$this->hasValueChanged($oldValue, $newValue)) {
                continue;
            }

            $change = $this->recordChange(
                $executionId,
                $fieldId,
                $field->name,
                $field->label,
                $oldValue,
                $newValue,
                $changedBy,
                $reason
            );

            $changes[] = $change;
        }

        return $changes;
    }

    public function getTrail(): array
    {
        return $this->trail;
    }

    public function getTrailForField(string $fieldId): array
    {
        return array_values(array_filter($this->trail, fn($e) => $e['field_id'] === $fieldId));
    }

    public function getTrailForExecution(string $executionId): array
    {
        return array_values(array_filter($this->trail, fn($e) => $e['execution_id'] === $executionId));
    }

    public function hasChanges(): bool
    {
        return !empty($this->trail);
    }

    public function clear(): void
    {
        $this->trail = [];
    }

    public function getSummary(): array
    {
        $fieldsChanged = [];
        $totalChanges = count($this->trail);

        foreach ($this->trail as $entry) {
            $fieldsChanged[$entry['field_id']] = [
                'field_name' => $entry['field_name'],
                'field_label' => $entry['field_label'],
                'change_count' => ($fieldsChanged[$entry['field_id']]['change_count'] ?? 0) + 1,
                'last_changed_at' => $entry['changed_at'],
                'last_changed_by' => $entry['changed_by'],
            ];
        }

        return [
            'total_changes' => $totalChanges,
            'fields_changed' => count($fieldsChanged),
            'field_details' => array_values($fieldsChanged),
        ];
    }

    protected function hasValueChanged(mixed $old, mixed $new): bool
    {
        if ($old === null && $new === null) {
            return false;
        }
        if ($old === null || $new === null) {
            return true;
        }
        return (string) $old !== (string) $new;
    }

    protected function serializeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value;
        }
        return (string) $value;
    }
}

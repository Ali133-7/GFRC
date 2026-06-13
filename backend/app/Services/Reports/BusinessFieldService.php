<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Register;
use App\Models\RegisterField;
use Illuminate\Support\Facades\DB;

/**
 * Business Data Layer for the Enterprise Report Designer.
 *
 * Transforms low-level database tables into business-oriented registers
 * and fields. The report designer never talks to raw tables directly.
 */
class BusinessFieldService
{
    /**
     * System tables that should never appear as report data sources
     * unless the user is an administrator in developer mode.
     */
    protected const SYSTEM_TABLES = [
        'users',
        'roles',
        'permissions',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'settings',
        'audit_logs',
        'jobs',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'workflows',
        'workflow_versions',
        'workflow_steps',
        'workflow_fields',
        'workflow_rules',
        'workflow_executions',
        'notifications',
        'personal_access_tokens',
        'migrations',
        'password_resets',
    ];

    /**
     * Common relationship keys used to auto-join business registers.
     */
    protected const RELATIONSHIP_KEYS = [
        'register_id',
        'created_by',
        'approved_by',
        'cancelled_by',
        'customer_id',
        'entity_id',
        'business_number',
        'workflow_execution_id',
        'workflow_version_id',
    ];

    /**
     * Get business registers available as report data sources.
     *
     * @return array<int, array>
     */
    public function getBusinessRegisters(bool $includeInactive = false): array
    {
        $query = Register::query()
            ->withCount('receipts as record_count');

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name_ar')
            ->get()
            ->map(fn (Register $register) => $this->mapBusinessRegister($register))
            ->toArray();
    }

    /**
     * Get business fields for one or more registers.
     *
     * @return array<int, array>
     */
    public function getBusinessFields(array|string $registerIds): array
    {
        $ids = is_array($registerIds) ? $registerIds : array_filter(explode(',', (string) $registerIds));

        if (empty($ids)) {
            return [];
        }

        return RegisterField::query()
            ->whereIn('register_id', $ids)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('label_ar')
            ->get()
            ->map(fn (RegisterField $field) => $this->mapBusinessField($field))
            ->toArray();
    }

    /**
     * Analyze and suggest automatic joins between selected registers.
     *
     * @return array<int, array>
     */
    public function analyzeRelationships(array $registerIds): array
    {
        if (count($registerIds) < 2) {
            return [];
        }

        $registers = Register::query()
            ->whereIn('id', $registerIds)
            ->with('fields')
            ->get();

        $relationships = [];
        $registerList = $registers->values();

        for ($i = 0; $i < $registerList->count(); $i++) {
            for ($j = $i + 1; $j < $registerList->count(); $j++) {
                $left = $registerList[$i];
                $right = $registerList[$j];

                $matches = $this->findCommonRelationshipKeys($left, $right);

                foreach ($matches as $key) {
                    $relationships[] = [
                        'id' => "rel_{$left->id}_{$right->id}_{$key}",
                        'left_register_id' => $left->id,
                        'left_register_name' => $left->name_ar ?? $left->name_en,
                        'right_register_id' => $right->id,
                        'right_register_name' => $right->name_ar ?? $right->name_en,
                        'relationship_key' => $key,
                        'join_type' => 'LEFT',
                        'confidence' => 'high',
                        'auto_generated' => true,
                        'left_table_alias' => $this->sanitizeAlias($left->code),
                        'right_table_alias' => $this->sanitizeAlias($right->code),
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Preview data for selected registers and fields.
     */
    public function previewBusinessData(
        array $registerIds,
        array $fieldIds,
        array $filters = [],
        int $limit = 50
    ): array {
        if (empty($registerIds) || empty($fieldIds)) {
            return ['data' => [], 'total' => 0];
        }

        $registers = Register::query()
            ->whereIn('id', $registerIds)
            ->pluck('code', 'id');

        $fields = RegisterField::query()
            ->whereIn('id', $fieldIds)
            ->whereIn('register_id', $registerIds)
            ->get();

        // Build a query against receipts filtered by register_id,
        // joining receipt_items for the requested fields.
        $query = DB::table('receipts')
            ->whereIn('register_id', $registerIds);

        // Apply simple filters on receipt-level columns if requested
        foreach ($filters as $filter) {
            $this->applyPreviewFilter($query, $filter);
        }

        $total = $query->clone()->count('receipts.id');

        $query->select(['receipts.id', 'receipts.receipt_number', 'receipts.register_id', 'receipts.total_amount', 'receipts.status', 'receipts.created_at'])
            ->orderByDesc('receipts.created_at')
            ->limit($limit);

        $rows = $query->get();

        // Eager-load field values for the returned receipts
        $receiptIds = $rows->pluck('id')->all();
        $items = DB::table('receipt_items')
            ->whereIn('receipt_id', $receiptIds)
            ->whereIn('field_id', $fieldIds)
            ->get()
            ->groupBy('receipt_id');

        $data = [];
        foreach ($rows as $row) {
            $record = [
                '_register_id' => $row->register_id,
                '_register_name' => $registers[$row->register_id] ?? null,
                'receipt_number' => $row->receipt_number,
                'total_amount' => $row->total_amount,
                'status' => $row->status,
                'created_at' => $row->created_at,
            ];

            foreach ($fields as $field) {
                $value = null;
                $receiptItems = $items[$row->id] ?? collect();
                $item = $receiptItems->firstWhere('field_id', $field->id);

                if ($item) {
                    $value = $item->text_value ?? $item->amount;
                }

                $record[$field->name] = $value;
            }

            $data[] = $record;
        }

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Check whether a table name is considered a system table.
     */
    public static function isSystemTable(string $table): bool
    {
        return in_array(strtolower($table), self::SYSTEM_TABLES, true);
    }

    /**
     * Map a Register model to a business data source DTO.
     */
    protected function mapBusinessRegister(Register $register): array
    {
        return [
            'id' => $register->id,
            'type' => 'register',
            'code' => $register->code,
            'name' => $register->name_ar ?? $register->name_en,
            'name_en' => $register->name_en,
            'description' => $register->description,
            'is_active' => $register->is_active,
            'record_count' => $register->record_count ?? 0,
            'table_alias' => $this->sanitizeAlias($register->code),
        ];
    }

    /**
     * Map a RegisterField model to a business field DTO.
     */
    protected function mapBusinessField(RegisterField $field): array
    {
        return [
            'id' => $field->id,
            'register_id' => $field->register_id,
            'name' => $field->name,
            'label' => $field->label_ar ?? $field->label_en ?? $field->name,
            'label_en' => $field->label_en,
            'description' => $field->description,
            'data_type' => $this->mapFieldType($field->field_type),
            'source_type' => 'register_field',
            'category' => $field->category ?? $this->inferCategory($field),
            'register_name' => $field->register->name_ar ?? $field->register->name_en ?? null,
            'is_searchable' => $field->is_searchable ?? true,
            'is_filterable' => $field->is_filterable ?? true,
            'is_aggregatable' => $field->is_aggregatable ?? $this->inferAggregatable($field),
            'is_required' => $field->is_required,
            'is_visible' => $field->is_visible,
            'is_financial' => $field->is_financial,
            'sort_order' => $field->sort_order,
        ];
    }

    /**
     * Map register field type to report designer type.
     */
    protected function mapFieldType(string $fieldType): string
    {
        return match (strtolower($fieldType)) {
            'number', 'integer', 'int' => 'number',
            'decimal', 'currency', 'amount', 'money' => 'currency',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'boolean', 'checkbox', 'toggle' => 'boolean',
            default => 'string',
        };
    }

    /**
     * Infer a category for a field when none is set.
     */
    protected function inferCategory(RegisterField $field): string
    {
        if ($field->is_financial) {
            return 'financial';
        }

        return match (strtolower($field->field_type)) {
            'date', 'datetime' => 'dates',
            'number', 'decimal', 'currency', 'amount' => 'numeric',
            'boolean', 'checkbox' => 'flags',
            default => 'general',
        };
    }

    /**
     * Infer whether a field can be aggregated.
     */
    protected function inferAggregatable(RegisterField $field): bool
    {
        $numericTypes = ['number', 'integer', 'int', 'decimal', 'currency', 'amount', 'money'];

        return $field->is_financial || in_array(strtolower($field->field_type), $numericTypes, true);
    }

    /**
     * Find common relationship keys between two registers.
     */
    protected function findCommonRelationshipKeys(Register $left, Register $right): array
    {
        $leftFieldNames = $left->fields->pluck('name')->map(fn ($n) => strtolower($n))->all();
        $rightFieldNames = $right->fields->pluck('name')->map(fn ($n) => strtolower($n))->all();

        $common = [];
        foreach (self::RELATIONSHIP_KEYS as $key) {
            if (in_array(strtolower($key), $leftFieldNames, true) && in_array(strtolower($key), $rightFieldNames, true)) {
                $common[] = $key;
            }
        }

        // Also check for fields with identical names across registers
        $identical = array_intersect($leftFieldNames, $rightFieldNames);
        foreach ($identical as $name) {
            if (! in_array($name, array_map('strtolower', $common), true)) {
                $common[] = $name;
            }
        }

        return array_values(array_unique($common));
    }

    /**
     * Create a SQL-safe table alias from register code.
     */
    protected function sanitizeAlias(string $code): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $code);
    }

    /**
     * Apply a simple filter to the preview query.
     */
    protected function applyPreviewFilter($query, array $filter): void
    {
        $field = $filter['field'] ?? null;
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        if (! $field || $value === null || $value === '') {
            return;
        }

        // Only allow filtering on known receipt columns for safety
        $allowedColumns = ['receipt_number', 'status', 'register_id', 'created_by', 'total_amount'];
        if (! in_array($field, $allowedColumns, true)) {
            return;
        }

        $column = "receipts.{$field}";

        match (strtoupper($operator)) {
            'LIKE' => $query->where($column, 'LIKE', "%{$value}%"),
            'IN' => $query->whereIn($column, is_array($value) ? $value : explode(',', $value)),
            'BETWEEN' => $query->whereBetween($column, is_array($value) ? $value : explode(',', $value)),
            default => $query->where($column, $operator, $value),
        };
    }
}

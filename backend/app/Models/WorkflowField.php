<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkflowField extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_version_id', 'register_field_id', 'step_id', 'label_override',
        'custom_name', 'custom_label', 'placeholder', 'default_value',
        'is_required', 'is_visible', 'is_editable', 'is_readonly', 'is_locked',
        'is_financial', 'is_computed', 'is_insured', 'insurance_value',
        'priority', 'sort_order', 'condition_logic', 'fee_code',
        'calculation_formula', 'computed_formula', 'computed_dependencies',
        'field_type', 'options', 'validation_rules',
        'conditional_validation_rules', 'cross_field_validation_rules',
        'parent_field_id', 'option_source_type', 'option_source_config',
        'cascade_config',
    ];

    protected $appends = ['label', 'name'];

    protected $with = ['registerField'];

    protected $casts = [
        'is_required' => 'boolean',
        'is_visible' => 'boolean',
        'is_editable' => 'boolean',
        'is_readonly' => 'boolean',
        'is_locked' => 'boolean',
        'is_financial' => 'boolean',
        'is_computed' => 'boolean',
        'is_insured' => 'boolean',
        'insurance_value' => 'decimal:3',
        'priority' => 'integer',
        'sort_order' => 'integer',
        'condition_logic' => 'array',
        'options' => 'array',
        'validation_rules' => 'array',
        'conditional_validation_rules' => 'array',
        'cross_field_validation_rules' => 'array',
        'computed_dependencies' => 'array',
        'cascade_config' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function registerField(): BelongsTo
    {
        return $this->belongsTo(RegisterField::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->custom_label ?? $this->label_override ?? $this->registerField?->label_ar ?? $this->custom_name ?? $this->registerField?->name ?? '';
    }

    public function getNameAttribute(): string
    {
        return $this->custom_name ?? $this->registerField?->name ?? '';
    }

    public function getFieldTypeAttribute(): string
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'field_type');
        return (string) ($resolved['value'] ?? 'text');
    }

    public function getResolvedOptionsAttribute(): array
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'options');
        return (array) ($resolved['value'] ?? []);
    }

    public function getResolvedDefaultValueAttribute(): mixed
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'default_value');
        return $resolved['value'];
    }

    public function getResolvedIsRequiredAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_required');
        return (bool) ($resolved['value'] ?? false);
    }

    public function getResolvedIsVisibleAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_visible');
        return (bool) ($resolved['value'] ?? true);
    }

    public function getResolvedIsEditableAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_editable');
        return (bool) ($resolved['value'] ?? true);
    }

    public function getResolvedIsLockedAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_locked');
        return (bool) ($resolved['value'] ?? false);
    }

    public function getResolvedIsFinancialAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_financial');
        return (bool) ($resolved['value'] ?? false);
    }

    public function getResolvedIsInsuredAttribute(): bool
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'is_insured');
        return (bool) ($resolved['value'] ?? false);
    }

    public function getResolvedValidationRulesAttribute(): array
    {
        $resolved = app(\App\Services\FieldInheritanceResolver::class)
            ->resolveProperty($this, $this->registerField, 'validation_rules');
        $value = $resolved['value'] ?? [];
        if (is_string($value) && $value !== '') {
            return explode('|', $value);
        }
        return (array) $value;
    }
}

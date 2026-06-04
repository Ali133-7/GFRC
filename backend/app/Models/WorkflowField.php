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
        $raw = $this->attributes['field_type'] ?? null;
        // Only treat as override if explicitly set to a non-default value
        $hasExplicitOverride = $raw !== null && $raw !== '' && $raw !== 'text';
        if ($hasExplicitOverride) {
            return $raw;
        }
        return $this->registerField?->field_type ?? 'text';
    }

    public function getResolvedOptionsAttribute(): array
    {
        if (is_array($this->options) && !empty($this->options)) {
            return $this->options;
        }
        return $this->registerField?->options ?? [];
    }

    public function getResolvedDefaultValueAttribute(): mixed
    {
        if ($this->default_value !== null && $this->default_value !== '') {
            return $this->default_value;
        }
        return $this->registerField?->default_value;
    }

    public function getResolvedIsRequiredAttribute(): bool
    {
        return $this->is_required ?? $this->registerField?->is_required ?? false;
    }

    public function getResolvedIsVisibleAttribute(): bool
    {
        return $this->is_visible ?? $this->registerField?->is_visible ?? true;
    }

    public function getResolvedIsEditableAttribute(): bool
    {
        return $this->is_editable ?? $this->registerField?->is_editable ?? true;
    }

    public function getResolvedIsLockedAttribute(): bool
    {
        return $this->is_locked ?? $this->registerField?->is_locked ?? false;
    }

    public function getResolvedIsFinancialAttribute(): bool
    {
        return $this->is_financial ?? $this->registerField?->is_financial ?? false;
    }

    public function getResolvedIsInsuredAttribute(): bool
    {
        return $this->is_insured ?? $this->registerField?->is_insured ?? false;
    }

    public function getResolvedValidationRulesAttribute(): array
    {
        if (is_array($this->validation_rules)) {
            return $this->validation_rules;
        }
        if (is_string($this->validation_rules) && $this->validation_rules !== '') {
            return explode('|', $this->validation_rules);
        }
        $baseRules = $this->registerField?->validation_rules;
        if (is_array($baseRules)) {
            return $baseRules;
        }
        if (is_string($baseRules) && $baseRules !== '') {
            return explode('|', $baseRules);
        }
        return [];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ValidationRule extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_version_id', 'name', 'description', 'validation_type', 'category',
        'target_register_id', 'trigger_field_id', 'trigger_conditions', 'target_fields', 'query_conditions',
        'sql_query', 'sql_condition', 'route_config', 'lookup_config', 'field_effects', 'expectation',
        'response_type', 'error_message_ar', 'error_message_en',
        'confirm_message_ar', 'confirm_message_en',
        'sort_order', 'is_active', 'realtime_enabled',
        'rule_config', 'priority',
    ];

    protected $casts = [
        'target_fields' => 'array',
        'trigger_conditions' => 'array',
        'query_conditions' => 'array',
        'route_config' => 'array',
        'lookup_config' => 'array',
        'field_effects' => 'array',
        'expectation' => 'string',
        'rule_config' => 'array',
        'is_active' => 'boolean',
        'realtime_enabled' => 'boolean',
        'sort_order' => 'integer',
        'priority' => 'integer',
    ];

    /**
     * Mutator to ensure realtime_enabled is always 0 or 1, not NULL.
     * This fixes SQLite's boolean handling issue.
     */
    public function setRealtimeEnabledAttribute($value): void
    {
        $this->attributes['realtime_enabled'] = $value ? 1 : 0;
    }

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

    public function targetRegister(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'target_register_id');
    }

    public function isError(): bool
    {
        return $this->response_type === 'error';
    }

    public function isWarning(): bool
    {
        return $this->response_type === 'warning';
    }

    public function isConfirm(): bool
    {
        return $this->response_type === 'confirm';
    }

    public function getErrorMessage(): string
    {
        return $this->error_message_ar ?? 'تم اكتشاف مشكلة في البيانات';
    }

    public function getConfirmMessage(): string
    {
        return $this->confirm_message_ar ?? 'هل تريد المتابعة؟';
    }
}

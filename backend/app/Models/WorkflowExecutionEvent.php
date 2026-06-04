<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkflowExecutionEvent extends Model
{
    protected $table = 'workflow_execution_events';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'execution_id',
        'event_type',
        'sequence',
        'event_payload',
        'calculated_items',
        'fee_snapshot',
        'context_snapshot',
        'previous_event_hash',
        'hash',
        'idempotency_key',
        'caused_by',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'event_payload' => 'array',
        'calculated_items' => 'array',
        'fee_snapshot' => 'array',
        'context_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Prevent any update attempt at the model level
        static::updating(function () {
            throw new \RuntimeException('WorkflowExecutionEvent is append-only. Use create instead.');
        });

        // Prevent any delete attempt at the model level
        static::deleting(function () {
            throw new \RuntimeException('WorkflowExecutionEvent is append-only. Delete is not allowed.');
        });
    }

    public function execution()
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    // Event type constants
    public const EXECUTION_STARTED = 'execution_started';
    public const STEP_SUBMITTED = 'step_submitted';
    public const STEP_FAILED = 'step_failed';
    public const EXECUTION_COMPLETED = 'execution_completed';
    public const EXECUTION_CANCELLED = 'execution_cancelled';
    public const EXECUTION_REPLAYED = 'execution_replayed';
}

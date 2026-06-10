<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRoutingLog extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'execution_id', 'from_workflow_id', 'to_workflow_id',
        'from_step_id', 'trigger_rule_id', 'reason', 'values_snapshot', 'created_by',
    ];

    protected $casts = [
        'values_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    public function fromWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'from_workflow_id');
    }

    public function toWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'to_workflow_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

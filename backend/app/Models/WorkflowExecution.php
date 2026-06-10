<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_version_id', 'register_id', 'status', 'mode', 'lock_version', 'current_step_index',
        'values_snapshot', 'calculated_items', 'total_amount', 'receipt_id',
        'branch_state', 'routing_history', 'preserved_values', 'state_mapping',
        'field_states', 'rule_results', 'validation_results', 'routing_decisions', 'financial_trace',
        'last_saved_at',
        'started_by', 'started_at', 'completed_at', 'cancelled_at',
        'cancel_reason', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'current_step_index' => 'integer',
        'lock_version' => 'integer',
        'values_snapshot' => 'array',
        'calculated_items' => 'array',
        'total_amount' => 'decimal:3',
        'branch_state' => 'array',
        'routing_history' => 'array',
        'preserved_values' => 'array',
        'state_mapping' => 'array',
        'field_states' => 'array',
        'rule_results' => 'array',
        'validation_results' => 'array',
        'routing_decisions' => 'array',
        'financial_trace' => 'array',
        'last_saved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function complete(): void
    {
        $updated = $this->where('id', $this->id)
            ->where('lock_version', $this->lock_version)
            ->where('status', 'in_progress')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'lock_version' => $this->lock_version + 1,
            ]);

        if ($updated === 0) {
            throw new \RuntimeException('فشل إكمال التنفيذ - تم تعديله أو تغيير حالته');
        }

        $this->refresh();
    }

    public function cancel(string $reason = null): void
    {
        $updated = $this->where('id', $this->id)
            ->where('lock_version', $this->lock_version)
            ->where('status', 'in_progress')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'lock_version' => $this->lock_version + 1,
            ]);

        if ($updated === 0) {
            throw new \RuntimeException('فشل إلغاء التنفيذ - تم تعديله أو تغيير حالته');
        }

        $this->refresh();
    }

    // --- Branch State Methods ---

    public function getMode(): string
    {
        return $this->mode ?? 'create';
    }

    public function isCreateMode(): bool
    {
        return $this->mode === 'create';
    }

    public function isUpdateMode(): bool
    {
        return $this->mode === 'update';
    }

    public function isRenewalMode(): bool
    {
        return $this->mode === 'renewal';
    }

    public function isReviewMode(): bool
    {
        return $this->mode === 'review';
    }

    public function switchMode(string $mode, string $reason = ''): void
    {
        $oldMode = $this->mode;
        $this->mode = $mode;

        $this->addRoutingEvent([
            'event' => 'mode_switch',
            'from_mode' => $oldMode,
            'to_mode' => $mode,
            'reason' => $reason,
        ]);

        $this->save();
    }

    public function getBranchState(): array
    {
        return $this->branch_state ?? [
            'active_branch' => 'default',
            'redirect_to_workflow_id' => null,
            'redirect_to_step_id' => null,
            'paused' => false,
            'pause_reason' => null,
            'original_execution_id' => null,
        ];
    }

    public function setActiveBranch(string $branch): void
    {
        $state = $this->getBranchState();
        $state['active_branch'] = $branch;
        $this->branch_state = $state;
        $this->save();
    }

    public function pauseExecution(string $reason = ''): void
    {
        $state = $this->getBranchState();
        $state['paused'] = true;
        $state['pause_reason'] = $reason;
        $this->branch_state = $state;
        $this->save();
    }

    public function resumeExecution(): void
    {
        $state = $this->getBranchState();
        $state['paused'] = false;
        $state['pause_reason'] = null;
        $this->branch_state = $state;
        $this->save();
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused' || ($this->getBranchState()['paused'] ?? false) === true;
    }

    public function setRedirect(string $targetWorkflowId, ?string $targetStepId = null): void
    {
        $state = $this->getBranchState();
        $state['redirect_to_workflow_id'] = $targetWorkflowId;
        $state['redirect_to_step_id'] = $targetStepId;
        $this->branch_state = $state;
        $this->save();
    }

    public function clearRedirect(): void
    {
        $state = $this->getBranchState();
        $state['redirect_to_workflow_id'] = null;
        $state['redirect_to_step_id'] = null;
        $this->branch_state = $state;
        $this->save();
    }

    public function getRedirectTarget(): ?array
    {
        $state = $this->getBranchState();
        if ($state['redirect_to_workflow_id']) {
            return [
                'workflow_id' => $state['redirect_to_workflow_id'],
                'step_id' => $state['redirect_to_step_id'] ?? null,
            ];
        }
        return null;
    }

    public function preserveValues(array $values): void
    {
        $this->preserved_values = array_merge($this->preserved_values ?? [], $values);
        $this->save();
    }

    public function getPreservedValues(): array
    {
        return $this->preserved_values ?? [];
    }

    public function setStateMapping(array $mapping): void
    {
        $this->state_mapping = $mapping;
        $this->save();
    }

    public function getStateMapping(): array
    {
        return $this->state_mapping ?? [];
    }

    public function addRoutingEvent(array $event): void
    {
        $history = $this->routing_history ?? [];
        $event['timestamp'] = now()->toISOString();
        $event['execution_id'] = $this->id;
        $history[] = $event;
        $this->routing_history = $history;
        $this->save();
    }

    public function getRoutingHistory(): array
    {
        return $this->routing_history ?? [];
    }

    public function hasRedirect(): bool
    {
        return $this->getRedirectTarget() !== null;
    }
}

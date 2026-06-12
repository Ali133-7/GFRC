<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkflowRule extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_version_id', 'name', 'description',
        'rule_type', 'trigger_field_id', 'condition_logic', 'actions',
        'cases', 'default_actions', 'match_mode',
        'sort_order', 'is_active', 'realtime_enabled',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'realtime_enabled' => 'boolean',
        'condition_logic' => 'array',
        'actions' => 'array',
        'cases' => 'array',
        'default_actions' => 'array',
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
            if (empty($model->rule_type)) {
                $model->rule_type = 'simple';
            }
        });
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function isCaseBased(): bool
    {
        return $this->rule_type === 'case_based';
    }

    public function isSimple(): bool
    {
        return $this->rule_type === 'simple';
    }

    public function getCasesSortedAttribute(): array
    {
        $cases = $this->cases ?? [];
        usort($cases, function ($a, $b) {
            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;
            return $priorityB <=> $priorityA; // Descending priority
        });
        return $cases;
    }
}

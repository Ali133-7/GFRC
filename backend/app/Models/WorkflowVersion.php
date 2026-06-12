<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowVersion extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'workflow_id', 'version', 'status', 'published_at', 'archived_at',
        'published_by', 'change_summary',
    ];

    protected $casts = [
        'version' => 'integer',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowField::class)->orderBy('sort_order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(WorkflowRule::class)->orderBy('sort_order');
    }

    public function validationRules(): HasMany
    {
        return $this->hasMany(\App\Models\ValidationRule::class, 'workflow_version_id')->orderBy('sort_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function publish(): void
    {
        DB::transaction(function () {
            // Archive any existing active versions for this workflow
            $this->workflow->versions()
                ->where('id', '!=', $this->id)
                ->where('status', 'active')
                ->update(['status' => 'archived', 'archived_at' => now()]);

            // Activate this version
            $this->update([
                'status' => 'active',
                'published_at' => now(),
            ]);
        });
    }

    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }
}

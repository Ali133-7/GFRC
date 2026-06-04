<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_version_id', 'title_ar', 'title_en', 'description',
        'sort_order', 'condition_logic', 'is_visible',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_visible' => 'boolean',
        'condition_logic' => 'array',
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

    public function fields(): HasMany
    {
        return $this->hasMany(WorkflowField::class, 'step_id')->orderBy('sort_order');
    }
}

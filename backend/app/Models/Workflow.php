<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'register_id', 'code', 'name_ar', 'name_en', 'description',
        'icon', 'is_active', 'current_version', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_version' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = ['active_version_id'];

    public function getActiveVersionIdAttribute(): ?string
    {
        return $this->versions()
            ->where('status', 'active')
            ->orderBy('version', 'desc')
            ->value('id');
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

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class)->orderBy('version', 'desc');
    }

    public function activeVersion(): ?WorkflowVersion
    {
        return $this->versions()
            ->where('status', 'active')
            ->orderBy('version', 'desc')
            ->first();
    }

    public function currentVersionModel(): ?WorkflowVersion
    {
        return $this->versions()
            ->where('version', $this->current_version)
            ->first();
    }
}

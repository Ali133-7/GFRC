<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'name_ar',
        'code',
        'description',
        'data_source',
        'configuration',
        'type',
        'visibility',
        'scope',
        'created_by',
        'register_id',
        'is_active',
        'is_system',
        'version',
        'parent_report_id',
        'published_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->code)) {
                $model->code = 'RPT_' . strtoupper(Str::random(8));
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ReportField::class)->orderBy('sort_order');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(ReportFilter::class)->orderBy('sort_order');
    }

    public function aggregations(): HasMany
    {
        return $this->hasMany(ReportAggregation::class)->orderBy('sort_order');
    }

    public function groupings(): HasMany
    {
        return $this->hasMany(ReportGrouping::class)->orderBy('sort_order');
    }

    public function charts(): HasMany
    {
        return $this->hasMany(ReportChart::class)->orderBy('sort_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ReportExecution::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(ReportPermission::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'parent_report_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Report::class, 'parent_report_id');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function publish(): void
    {
        $this->update([
            'published_at' => now(),
            'version' => $this->version + 1,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
              ->orWhere('visibility', 'shared')
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', 'role')
                     ->whereHas('permissions', function ($q3) use ($user) {
                         foreach ($user->roles as $role) {
                             $q3->orWhere(function ($q4) use ($role) {
                                 $q4->where('permissionable_type', \App\Models\Role::class)
                                    ->where('permissionable_id', $role->id);
                             });
                         }
                     });
              })
              ->orWhere('created_by', $user->id);
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ReportPermission extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'report_id', 'permissionable_type', 'permissionable_id',
        'permission_type', 'field_restrictions', 'filter_restrictions',
    ];

    protected $casts = [
        'field_restrictions' => 'array',
        'filter_restrictions' => 'array',
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

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function permissionable(): MorphTo
    {
        return $this->morphTo();
    }
}

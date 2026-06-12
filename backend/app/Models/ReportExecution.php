<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportExecution extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'report_id', 'user_id', 'filters_applied', 'rows_returned',
        'execution_time_ms', 'cache_key', 'from_cache', 'export_format', 'ip_address',
    ];

    protected $casts = [
        'filters_applied' => 'array',
        'rows_returned' => 'integer',
        'execution_time_ms' => 'integer',
        'from_cache' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportFilter extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'report_id', 'filter_name', 'filter_label', 'filter_label_ar',
        'field_name', 'filter_type', 'operator', 'options', 'default_value',
        'is_required', 'is_multiple', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_multiple' => 'boolean',
        'sort_order' => 'integer',
        'options' => 'array',
        'default_value' => 'array',
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
}

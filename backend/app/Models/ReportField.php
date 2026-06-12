<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportField extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'report_id', 'field_name', 'field_label', 'field_label_ar',
        'field_type', 'table_alias', 'is_visible', 'is_filterable',
        'is_sortable', 'is_groupable', 'sort_order', 'formatting', 'permissions',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_filterable' => 'boolean',
        'is_sortable' => 'boolean',
        'is_groupable' => 'boolean',
        'sort_order' => 'integer',
        'formatting' => 'array',
        'permissions' => 'array',
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

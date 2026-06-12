<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportGrouping extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'report_id', 'field_name', 'field_label', 'sort_order', 'show_subtotals',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'show_subtotals' => 'boolean',
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

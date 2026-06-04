<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateRule extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id', 'template_id', 'name', 'trigger_field_id', 'trigger_operator',
        'trigger_value', 'target_field_id', 'action', 'action_value',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(TransactionTemplate::class, 'template_id');
    }

    public function triggerField()
    {
        return $this->belongsTo(RegisterField::class, 'trigger_field_id');
    }

    public function targetField()
    {
        return $this->belongsTo(RegisterField::class, 'target_field_id');
    }
}

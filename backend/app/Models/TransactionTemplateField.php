<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionTemplateField extends Model
{
    use HasFactory;

    protected $table = 'transaction_template_fields';
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
        'id', 'template_id', 'register_field_id', 'label_override',
        'placeholder', 'default_value', 'is_required', 'is_visible',
        'is_readonly', 'sort_order', 'options',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_visible' => 'boolean',
        'is_readonly' => 'boolean',
        'sort_order' => 'integer',
        'options' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(TransactionTemplate::class, 'template_id');
    }

    public function registerField()
    {
        return $this->belongsTo(RegisterField::class, 'register_field_id');
    }
}

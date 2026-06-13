<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RegisterField extends Model
{
    use HasFactory, SoftDeletes;

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
        'id',
        'register_id',
        'name',
        'label_ar',
        'label_en',
        'description',
        'field_type',
        'category',
        'is_required',
        'is_visible',
        'is_editable',
        'is_locked',
        'is_financial',
        'is_insured',
        'insurance_value',
        'is_searchable',
        'is_filterable',
        'is_aggregatable',
        'priority',
        'sort_order',
        'validation_rules',
        'default_value',
        'options',
        'deleted_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_visible' => 'boolean',
        'is_editable' => 'boolean',
        'is_locked' => 'boolean',
        'is_financial' => 'boolean',
        'is_insured' => 'boolean',
        'is_searchable' => 'boolean',
        'is_filterable' => 'boolean',
        'is_aggregatable' => 'boolean',
        'insurance_value' => 'decimal:3',
        'priority' => 'integer',
        'sort_order' => 'integer',
        'options' => 'array',
        'validation_rules' => 'array',
    ];

    public function register()
    {
        return $this->belongsTo(Register::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(ReceiptItem::class, 'field_id');
    }
}

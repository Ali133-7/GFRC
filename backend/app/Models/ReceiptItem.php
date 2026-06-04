<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReceiptItem extends Model
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

    public $timestamps = false;

    protected $fillable = [
        'id',
        'receipt_id',
        'field_id',
        'field_name_snapshot',
        'label_ar_snapshot',
        'amount',
        'text_value',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function field()
    {
        return $this->belongsTo(RegisterField::class, 'field_id');
    }
}

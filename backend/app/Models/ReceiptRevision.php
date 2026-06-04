<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReceiptRevision extends Model
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
        'version',
        'revised_by',
        'reason',
        'old_snapshot',
        'new_snapshot',
        'created_at',
    ];

    protected $casts = [
        'old_snapshot' => 'array',
        'new_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    public function reviser()
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}

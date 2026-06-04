<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OfficialFee extends Model
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
        'id', 'category_id', 'fee_code', 'version', 'name_ar', 'name_en', 'amount',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(OfficialFeeCategory::class, 'category_id');
    }

    public function feeVersions()
    {
        return $this->hasMany(FeeVersion::class, 'fee_id')->orderBy('version', 'desc');
    }

    public function currentVersion()
    {
        return $this->feeVersions()->activeAt()->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            });
    }
}

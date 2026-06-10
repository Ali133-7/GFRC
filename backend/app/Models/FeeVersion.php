<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FeeVersion extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'fee_id', 'version', 'amount', 'effective_from', 'effective_to',
        'change_reason', 'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'amount' => 'decimal:3',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::saving(function (FeeVersion $version) {
            $overlap = FeeVersion::where('fee_id', $version->fee_id)
                ->where('id', '!=', $version->id ?? 0)
                ->where(function ($q) use ($version) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $version->effective_from);
                })
                ->where('effective_from', '<=', $version->effective_to ?? '9999-12-31')
                ->exists();

            if ($overlap) {
                throw new \App\Exceptions\Workflow\TemporalOverlapException(
                    "fee_id {$version->fee_id} already has an active version in this date range"
                );
            }
        });
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(OfficialFee::class, 'fee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActiveAt($query, $date = null)
    {
        $date ??= now();
        return $query
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }
}

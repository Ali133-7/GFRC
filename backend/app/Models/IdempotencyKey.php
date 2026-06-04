<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'key',
        'entity_type',
        'entity_id',
        'request_hash',
        'response_snapshot',
        'created_at',
        'expires_at',
    ];

    protected $casts = [
        'response_snapshot' => 'array',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Prevent any update attempt
        static::updating(function () {
            throw new \RuntimeException('IdempotencyKey is immutable. Update is not allowed.');
        });

        // Prevent any delete attempt
        static::deleting(function () {
            throw new \RuntimeException('IdempotencyKey is immutable. Delete is not allowed.');
        });
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    public static function findActive(string $key): ?self
    {
        return self::where('key', $key)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}

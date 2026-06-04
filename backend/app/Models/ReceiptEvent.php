<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReceiptEvent extends Model
{
    protected $table = 'receipt_events';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'receipt_id',
        'event_type',
        'sequence',
        'before_state',
        'after_state',
        'fee_snapshot',
        'context_snapshot',
        'lock_version',
        'previous_event_hash',
        'hash',
        'idempotency_key',
        'caused_by',
        'ip_address',
        'user_agent',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'lock_version' => 'integer',
        'before_state' => 'array',
        'after_state' => 'array',
        'fee_snapshot' => 'array',
        'context_snapshot' => 'array',
        'created_at' => 'datetime',
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

        // Prevent any update attempt at the model level
        static::updating(function () {
            throw new \RuntimeException('ReceiptEvent is append-only. Use create instead.');
        });

        // Prevent any delete attempt at the model level
        static::deleting(function () {
            throw new \RuntimeException('ReceiptEvent is append-only. Delete is not allowed.');
        });
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    // Event type constants
    public const RECEIPT_CREATED = 'receipt_created';
    public const RECEIPT_ISSUED = 'receipt_issued';
    public const RECEIPT_REVISED = 'receipt_revised';
    public const RECEIPT_CANCELLED = 'receipt_cancelled';
    public const RECEIPT_PRINTED = 'receipt_printed';
}

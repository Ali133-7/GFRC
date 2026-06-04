<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Receipt extends Model
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
        'receipt_number',
        'register_id',
        'workflow_execution_id',
        'workflow_version_id',
        'created_by',
        'approved_by',
        'total_amount',
        'status',
        'version',
        'lock_version',
        'notes',
        'idempotency_key',
        'qr_payload',
        'printed_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'metadata',
        'deleted_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:3',
        'version' => 'integer',
        'lock_version' => 'integer',
        'printed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function register()
    {
        return $this->belongsTo(Register::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items()
    {
        return $this->hasMany(ReceiptItem::class);
    }

    public function revisions()
    {
        return $this->hasMany(ReceiptRevision::class)->orderBy('version', 'desc');
    }

    public function workflowExecution()
    {
        return $this->belongsTo(WorkflowExecution::class);
    }

    public function workflowVersion()
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function calculationSnapshot()
    {
        return $this->hasOne(ReceiptCalculationSnapshot::class);
    }
}

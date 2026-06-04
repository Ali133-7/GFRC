<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReceiptCalculationSnapshot extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'receipt_id', 'workflow_version_id', 'workflow_definition',
        'rules_applied', 'fees_used', 'field_values', 'calculation_hash',
    ];

    protected $casts = [
        'workflow_definition' => 'array',
        'rules_applied' => 'array',
        'fees_used' => 'array',
        'field_values' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function verifyIntegrity(): bool
    {
        $computed = hash('sha256', json_encode([
            $this->workflow_definition,
            $this->rules_applied,
            $this->fees_used,
            $this->field_values,
        ]));
        return hash_equals($this->calculation_hash, $computed);
    }
}

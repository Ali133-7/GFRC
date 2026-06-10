<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldStateHistory extends Model
{
    use HasFactory;

    protected $table = 'field_state_history';

    public $timestamps = false;

    protected $casts = [
        'old_state' => 'array',
        'new_state' => 'array',
        'changed_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }
}

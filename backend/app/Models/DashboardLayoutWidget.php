<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DashboardLayoutWidget extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'dashboard_layout_widgets';

    protected $fillable = [
        'id',
        'layout_id',
        'widget_type',
        'title',
        'data_source',
        'display_config',
        'register_id',
        'position_x',
        'position_y',
        'width',
        'height',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'title' => 'array',
        'data_source' => 'array',
        'display_config' => 'array',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function layout()
    {
        return $this->belongsTo(DashboardLayout::class, 'layout_id');
    }

    public function register()
    {
        return $this->belongsTo(Register::class, 'register_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TransactionTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaction_templates';
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
        'id', 'register_id', 'name_ar', 'name_en', 'description', 'sections',
        'icon', 'is_active', 'is_default', 'sort_order', 'usage_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'usage_count' => 'integer',
        'sections' => 'array',
    ];

    public function register()
    {
        return $this->belongsTo(Register::class);
    }

    public function fields()
    {
        return $this->hasMany(TransactionTemplateField::class, 'template_id')->orderBy('sort_order');
    }

    public function rules()
    {
        return $this->hasMany(TemplateRule::class, 'template_id')->orderBy('sort_order');
    }
}

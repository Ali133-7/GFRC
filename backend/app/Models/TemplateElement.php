<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateElement extends Model
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
        'id',
        'template_id',
        'field_id',
        'element_type',
        'label',
        'sort_order',
        'x',
        'y',
        'width',
        'height',
        'is_visible',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_visible' => 'boolean',
        'metadata' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(ReceiptTemplate::class);
    }

    public function field()
    {
        return $this->belongsTo(RegisterField::class, 'field_id');
    }

    public function style()
    {
        return $this->hasOne(TemplateStyle::class, 'element_id');
    }

    /**
     * Get element with its style data
     */
    public function getElementData(): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'field_id' => $this->field_id,
            'element_type' => $this->element_type,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'is_visible' => $this->is_visible,
            'metadata' => $this->metadata,
            'style' => $this->style?->getStyleData(),
        ];
    }

    /**
     * Update element position and size
     */
    public function updatePosition(int $x, int $y, int $width, int $height): void
    {
        $this->update([
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Toggle element visibility
     */
    public function toggleVisibility(): void
    {
        $this->update(['is_visible' => !$this->is_visible]);
    }
}

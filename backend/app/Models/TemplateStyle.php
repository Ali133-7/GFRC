<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TemplateStyle extends Model
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
        'element_id',
        'font_family',
        'font_size',
        'font_weight',
        'font_color',
        'background_color',
        'border_color',
        'border_width',
        'text_align',
        'padding_top',
        'padding_right',
        'padding_bottom',
        'padding_left',
        'opacity',
        'display',
        'line_height',
        'letter_spacing',
    ];

    protected $casts = [
        'font_size' => 'integer',
        'border_width' => 'integer',
        'padding_top' => 'integer',
        'padding_right' => 'integer',
        'padding_bottom' => 'integer',
        'padding_left' => 'integer',
        'opacity' => 'float',
        'line_height' => 'float',
    ];

    public function element()
    {
        return $this->belongsTo(TemplateElement::class, 'element_id');
    }

    /**
     * Get style as CSS-compatible array
     */
    public function getStyleData(): array
    {
        return [
            'id' => $this->id,
            'element_id' => $this->element_id,
            'font_family' => $this->font_family,
            'font_size' => $this->font_size,
            'font_weight' => $this->font_weight,
            'font_color' => $this->font_color,
            'background_color' => $this->background_color,
            'border_color' => $this->border_color,
            'border_width' => $this->border_width,
            'text_align' => $this->text_align,
            'padding' => [
                'top' => $this->padding_top,
                'right' => $this->padding_right,
                'bottom' => $this->padding_bottom,
                'left' => $this->padding_left,
            ],
            'opacity' => $this->opacity,
            'display' => $this->display,
            'line_height' => $this->line_height,
            'letter_spacing' => $this->letter_spacing,
        ];
    }

    /**
     * Get style as inline CSS string (for rendering)
     */
    public function getInlineCSS(): string
    {
        $css = [
            "font-family: {$this->font_family}",
            "font-size: {$this->font_size}px",
            "font-weight: {$this->font_weight}",
            "color: {$this->font_color}",
            "text-align: {$this->text_align}",
            "opacity: {$this->opacity}",
            "display: {$this->display}",
            "line-height: {$this->line_height}",
        ];

        if ($this->background_color) {
            $css[] = "background-color: {$this->background_color}";
        }

        if ($this->border_width > 0 && $this->border_color) {
            $css[] = "border: {$this->border_width}px solid {$this->border_color}";
        }

        $padding = "{$this->padding_top}px {$this->padding_right}px {$this->padding_bottom}px {$this->padding_left}px";
        $css[] = "padding: {$padding}";

        if ($this->letter_spacing) {
            $css[] = "letter-spacing: {$this->letter_spacing}";
        }

        return implode('; ', $css);
    }

    /**
     * Update multiple style properties at once
     */
    public function updateStyles(array $data): void
    {
        $allowedFields = [
            'font_family', 'font_size', 'font_weight', 'font_color',
            'background_color', 'border_color', 'border_width',
            'text_align', 'padding_top', 'padding_right', 'padding_bottom',
            'padding_left', 'opacity', 'display', 'line_height', 'letter_spacing'
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        if (!empty($updateData)) {
            $this->update($updateData);
        }
    }
}

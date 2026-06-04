<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReceiptTemplate extends Model
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
        'register_id',
        'name',
        'description',
        'is_active',
        'is_default',
        'layout_type',
        'page_width',
        'page_height',
        'background_color',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'page_width' => 'integer',
        'page_height' => 'integer',
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

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function elements()
    {
        return $this->hasMany(TemplateElement::class, 'template_id')->orderBy('sort_order');
    }

    /**
     * Create a clone of this template with all its elements
     */
    public function cloneTemplate(string $newName, string $userId): self
    {
        $clone = $this->replicate();
        $clone->name = $newName;
        $clone->is_default = false;
        $clone->created_by = $userId;
        $clone->updated_by = $userId;
        $clone->save();

        // Clone elements
        foreach ($this->elements as $element) {
            $elementClone = $element->replicate();
            $elementClone->template_id = $clone->id;
            $elementClone->save();

            // Clone element styles
            if ($element->style) {
                $styleClone = $element->style->replicate();
                $styleClone->element_id = $elementClone->id;
                $styleClone->save();
            }
        }

        return $clone;
    }

    /**
     * Make this template the default for its register
     */
    public function makeDefault(): void
    {
        ReceiptTemplate::where('register_id', $this->register_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get template data for rendering/display
     */
    public function getTemplateData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'layout_type' => $this->layout_type,
            'page_width' => $this->page_width,
            'page_height' => $this->page_height,
            'background_color' => $this->background_color,
            'elements' => $this->elements->map(fn($e) => $e->getElementData())->toArray(),
        ];
    }
}

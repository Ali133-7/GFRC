<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Register extends Model
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
        'code',
        'name_ar',
        'name_en',
        'description',
        'is_active',
        'fiscal_year',
        'current_sequence',
        'created_by',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fiscal_year' => 'integer',
        'current_sequence' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields()
    {
        return $this->hasMany(RegisterField::class)->orderBy('sort_order');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function templates()
    {
        return $this->hasMany(ReceiptTemplate::class);
    }

    public function defaultTemplate()
    {
        return $this->hasOne(ReceiptTemplate::class)->where('is_default', true);
    }

    /**
     * Get the default template or create one if not exists
     */
    public function getDefaultTemplate(): ReceiptTemplate
    {
        $template = $this->defaultTemplate()->first();

        if (!$template) {
            $template = ReceiptTemplate::create([
                'register_id' => $this->id,
                'name' => "القالب الافتراضي - {$this->name_ar}",
                'description' => "القالب الافتراضي للسجل {$this->code}",
                'is_active' => true,
                'is_default' => true,
                'layout_type' => 'portrait',
                'page_width' => 210,
                'page_height' => 297,
                'created_by' => auth()->id() ?? $this->created_by,
            ]);

            // Create default elements for the template
            $sortOrder = 0;
            foreach ($this->fields as $field) {
                TemplateElement::create([
                    'template_id' => $template->id,
                    'field_id' => $field->id,
                    'element_type' => 'field',
                    'label' => $field->label_ar,
                    'sort_order' => $sortOrder++,
                    'x' => 0,
                    'y' => $sortOrder * 35,
                    'width' => 100,
                    'height' => 30,
                    'is_visible' => true,
                ]);
            }
        }

        return $template;
    }

    public function generateReceiptNumber(): string
    {
        // Use database-level locking to prevent race conditions
        $locked = static::lockForUpdate()->find($this->id);
        if (!$locked) {
            throw new \RuntimeException('Failed to lock register for receipt number generation');
        }
        
        $locked->increment('current_sequence');
        
        // Update this instance to match
        $this->current_sequence = $locked->current_sequence;
        
        return sprintf('%s-%d-%06d', $this->code, $this->fiscal_year, $this->current_sequence);
    }
}

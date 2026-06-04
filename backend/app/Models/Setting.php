<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'key', 'value', 'type', 'group', 'label_ar', 'description', 'is_public'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($this->value) ? (float) $this->value : 0,
            'json' => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting?->getTypedValue() ?? $default;
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $setting = static::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => (string) $value, 'type' => $type]);
        }
    }
}

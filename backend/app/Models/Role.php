<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'api';

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
        static::deleting(function ($model) {
            if ($model->username === 'admin') {
                throw new \RuntimeException('حذف حساب الأدمن غير مسموح به');
            }
        });
    }

    protected $fillable = [
        'id',
        'name',
        'username',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function createdRegisters()
    {
        return $this->hasMany(Register::class, 'created_by');
    }

    public function createdReceipts()
    {
        return $this->hasMany(Receipt::class, 'created_by');
    }

    public function approvedReceipts()
    {
        return $this->hasMany(Receipt::class, 'approved_by');
    }

    public function cancelledReceipts()
    {
        return $this->hasMany(Receipt::class, 'cancelled_by');
    }
}

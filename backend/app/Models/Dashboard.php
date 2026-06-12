<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Dashboard extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'description',
        'scope',
        'organization_id',
        'department_id',
        'role_id',
        'user_id',
        'template_id',
        'parent_dashboard_id',
        'layout_config',
        'theme_config',
        'settings',
        'visibility',
        'is_default',
        'is_active',
        'version',
        'status',
        'created_by',
        'updated_by',
        'published_at',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'theme_config' => 'array',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(DashboardTemplate::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class, 'parent_dashboard_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Dashboard::class, 'parent_dashboard_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(DashboardSection::class)->orderBy('sort_order');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function userDashboards(): HasMany
    {
        return $this->hasMany(UserDashboard::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(DashboardPermission::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('scope', 'system')
              ->orWhere('scope', 'organization')
              ->orWhere('scope', 'department', 'department_id', $user->department_id)
              ->orWhere('scope', 'role', 'role_id', $user->role_id)
              ->orWhere('scope', 'user', 'user_id', $user->id);
        });
    }

    public function scopeVisibleToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
              ->orWhere('visibility', 'system')
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', 'organization')
                     ->where('organization_id', $user->organization_id);
              })
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', 'department')
                     ->where('department_id', $user->department_id);
              })
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', 'role')
                     ->where('role_id', $user->role_id);
              })
              ->orWhere(function ($q2) use ($user) {
                  $q2->where('visibility', 'private')
                     ->where('user_id', $user->id);
              });
        });
    }

    // Methods
    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : ($this->name_en ?? $this->name_ar);
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        $permission = $this->permissions()
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('permission_type', 'user')
                       ->where('permission_target_id', $user->id);
                })
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('permission_type', 'role')
                       ->where('permission_target_id', $user->role_id);
                })
                ->orWhere(function ($q2) use ($user) {
                    $q2->where('permission_type', 'department')
                       ->where('permission_target_id', $user->department_id);
                });
            })
            ->where('can_edit', true)
            ->first();

        return $permission !== null;
    }
}

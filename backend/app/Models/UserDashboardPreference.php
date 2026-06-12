<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardPreference extends Model
{
    protected $fillable = [
        'user_id',
        'default_dashboard_id',
        'default_view',
        'theme',
        'color_palette',
        'font_size',
        'layout_density',
        'auto_refresh_widgets',
        'default_refresh_interval',
        'show_widget_borders',
        'show_widget_shadows',
        'show_notifications',
        'show_announcements',
        'show_quick_actions',
        'show_favorites',
        'quick_links',
        'favorite_reports',
        'favorite_workflows',
        'favorite_registers',
        'bookmarks',
        'executive_mode',
        'tv_mode',
        'tv_rotation_interval',
    ];

    protected $casts = [
        'quick_links' => 'array',
        'favorite_reports' => 'array',
        'favorite_workflows' => 'array',
        'favorite_registers' => 'array',
        'bookmarks' => 'array',
        'auto_refresh_widgets' => 'boolean',
        'show_widget_borders' => 'boolean',
        'show_widget_shadows' => 'boolean',
        'show_notifications' => 'boolean',
        'show_announcements' => 'boolean',
        'show_quick_actions' => 'boolean',
        'show_favorites' => 'boolean',
        'executive_mode' => 'boolean',
        'tv_mode' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultDashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class, 'default_dashboard_id');
    }

    public function scopeForUser($query, ?string $userId = null)
    {
        if ($userId === null) {
            return $query;
        }
        return $query->where('user_id', $userId);
    }
}

<?php

namespace App\Providers;

use App\Models\Receipt;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Observers\AuditableObserver;
use App\Policies\ReceiptPolicy;
use App\Policies\WorkflowExecutionPolicy;
use App\Policies\WorkflowPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::observe(AuditableObserver::class);
        Register::observe(AuditableObserver::class);
        RegisterField::observe(AuditableObserver::class);
        Receipt::observe(AuditableObserver::class);
        Setting::observe(AuditableObserver::class);

        Route::model('user', User::class);
        Route::model('register', Register::class);
        Route::model('receipt', Receipt::class);

        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(WorkflowExecution::class, WorkflowExecutionPolicy::class);

        // Allow super_admin to bypass all permission checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}

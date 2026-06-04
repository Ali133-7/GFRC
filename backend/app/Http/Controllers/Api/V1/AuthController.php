<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:' . $request->ip() . ':' . $request->input('username');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            // Log failed attempt due to rate limiting
            activity()
                ->causedBy(null)
                ->withProperties([
                    'username' => $request->input('username'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => 'rate_limit_exceeded',
                    'lockout_seconds' => $seconds,
                ])
                ->event('login_failed')
                ->tap(function ($activity) use ($request) {
                    $activity->ip_address = $request->ip();
                    $activity->user_agent = $request->userAgent();
                })
                ->log('login_rate_limited');

            throw ValidationException::withMessages([
                'username' => ["محاولات كثيرة. يرجى الانتظار {$seconds} ثانية."],
            ]);
        }

        $user = User::where('username', $request->input('username'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($key, 15 * 60); // 15 minutes

            // Log failed login attempt
            activity()
                ->causedBy(null)
                ->withProperties([
                    'username' => $request->input('username'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => 'invalid_credentials',
                ])
                ->event('login_failed')
                ->tap(function ($activity) use ($request) {
                    $activity->ip_address = $request->ip();
                    $activity->user_agent = $request->userAgent();
                })
                ->log('login_failed');

            throw ValidationException::withMessages([
                'username' => ['بيانات الدخول غير صحيحة'],
            ]);
        }

        if (!$user->is_active) {
            RateLimiter::hit($key, 15 * 60);

            activity()
                ->causedBy(null)
                ->withProperties([
                    'username' => $request->input('username'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => 'account_disabled',
                ])
                ->event('login_failed')
                ->tap(function ($activity) use ($request) {
                    $activity->ip_address = $request->ip();
                    $activity->user_agent = $request->userAgent();
                })
                ->log('login_failed');

            throw ValidationException::withMessages([
                'username' => ['الحساب معطل'],
            ]);
        }

        RateLimiter::clear($key);
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api-token')->plainTextToken;

        // Log successful login
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_at' => now()->toDateTimeString(),
            ])
            ->event('login_success')
            ->tap(function ($activity) use ($request) {
                $activity->ip_address = $request->ip();
                $activity->user_agent = $request->userAgent();
            })
            ->log('login_success');

        return $this->success([
            'user' => new UserResource($user->load('roles', 'permissions')),
            'token' => $token,
        ], 'تم تسجيل الدخول بنجاح');
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user();

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'logout_at' => now()->toDateTimeString(),
            ])
            ->event('logout')
            ->tap(function ($activity) {
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log('logout');

        auth()->user()->currentAccessToken()->delete();
        return $this->success([], 'تم تسجيل الخروج بنجاح');
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(auth()->user()->load('roles', 'permissions')));
    }

    public function logoutAll(): JsonResponse
    {
        $user = auth()->user();

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'action' => 'logout_all_devices',
            ])
            ->event('logout_all')
            ->tap(function ($activity) {
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log('logout_all');

        $user->tokens()->delete();
        return $this->success([], 'تم تسجيل الخروج من جميع الأجهزة بنجاح');
    }
}

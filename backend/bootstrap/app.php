<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('workflow:cleanup-abandoned')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
        
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null; // Don't redirect API requests
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Please login',
                    'error_code' => 'UNAUTHORIZED',
                ], 401);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '?? ???? ???? ??????? ?? ??????. ???? ????????.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                ], 429);
            }
        });

        $exceptions->render(function (\App\Exceptions\Workflow\ExecutionNotInProgressException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'EXECUTION_NOT_IN_PROGRESS',
                ], $e->getCode() ?: 409);
            }
        });

        $exceptions->render(function (\App\Exceptions\Workflow\ExecutionPausedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'EXECUTION_PAUSED',
                ], $e->getCode() ?: 422);
            }
        });

        $exceptions->render(function (\App\Exceptions\Workflow\ValidationBlockedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'VALIDATION_BLOCKED',
                    'blocks' => $e->blocks,
                ], $e->getCode() ?: 422);
            }
        });

        $exceptions->render(function (\App\Exceptions\Workflow\FinancialIntegrityException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => 'FINANCIAL_INTEGRITY_ERROR',
                ], $e->getCode() ?: 422);
            }
        });
    })->create();

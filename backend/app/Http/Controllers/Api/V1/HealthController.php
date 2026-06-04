<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends ApiController
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'app' => [
                'status' => 'ok',
                'version' => config('app.version', '1.0'),
                'environment' => app()->environment(),
            ],
        ];

        $allOk = collect($checks)->every(fn($c) => ($c['status'] ?? 'fail') === 'ok');

        return response()->json([
            'success' => $allOk,
            'data' => $checks,
            'message' => $allOk ? 'النظام يعمل بشكل طبيعي' : 'هناك مشكلة في أحد المكونات',
        ], $allOk ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'driver' => DB::connection()->getDriverName()];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    protected function checkStorage(): array
    {
        $path = storage_path('app');
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $usedPercent = round((($total - $free) / $total) * 100, 2);

        return [
            'status' => $usedPercent > 95 ? 'warning' : 'ok',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Report\CustomReportRequest;
use App\Http\Requests\Report\DailyReportRequest;
use App\Http\Requests\Report\MonthlyReportRequest;
use App\Http\Requests\Report\RegisterSummaryReportRequest;
use App\Http\Requests\Report\UserActivityReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportController extends ApiController
{
    public function __construct(protected ReportService $reportService) {}

    public function daily(DailyReportRequest $request): JsonResponse
    {
        $data = $this->reportService->daily(
            $request->input('date'),
            $request->input('register_id'),
            $request->input('user_id')
        );
        return $this->success($data);
    }

    public function monthly(MonthlyReportRequest $request): JsonResponse
    {
        $data = $this->reportService->monthly(
            $request->input('year'),
            $request->input('month'),
            $request->input('register_id')
        );
        return $this->success($data);
    }

    public function userActivity(UserActivityReportRequest $request): JsonResponse
    {
        $data = $this->reportService->userActivity(
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('user_id')
        );
        return $this->success($data);
    }

    public function registerSummary(RegisterSummaryReportRequest $request): JsonResponse
    {
        $data = $this->reportService->registerSummary(
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('register_id')
        );
        return $this->success($data);
    }

    public function custom(CustomReportRequest $request): JsonResponse
    {
        $data = $this->reportService->custom($request->validated());
        return $this->success($data);
    }

    public function exportCsv(CustomReportRequest $request): Response
    {
        $data = $this->reportService->custom($request->validated());
        $csv = $this->reportService->exportCsv($data, 'report.csv');

        $filename = 'gfrc_report_' . now()->format('Y-m-d_His') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}

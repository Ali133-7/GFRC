<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ReportService
{
    public function daily(?string $date, ?string $registerId = null, ?string $userId = null): array
    {
        $date = $date ?? now()->toDateString();
        $query = Receipt::whereDate('created_at', $date)->where('status', '!=', 'cancelled');

        if ($registerId) $query->where('register_id', $registerId);
        if ($userId) $query->where('created_by', $userId);

        $receipts = $query->with('register', 'creator')->get();

        return [
            'date' => $date,
            'total_receipts' => $receipts->count(),
            'total_amount' => $receipts->sum('total_amount'),
            'by_register' => $receipts->groupBy('register.name_ar')->map(fn($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
            ])->toArray(),
            'by_user' => $receipts->groupBy('creator.name')->map(fn($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
            ])->toArray(),
        ];
    }

    public function monthly(int $year, int $month, ?string $registerId = null): array
    {
        $query = Receipt::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', '!=', 'cancelled');

        if ($registerId) $query->where('register_id', $registerId);

        $receipts = $query->with('register')->get();

        return [
            'year' => $year,
            'month' => $month,
            'total_receipts' => $receipts->count(),
            'total_amount' => $receipts->sum('total_amount'),
            'by_day' => $receipts->groupBy(fn($r) => $r->created_at->day)->map(fn($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
            ])->toArray(),
            'by_register' => $receipts->groupBy('register.name_ar')->map(fn($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
            ])->toArray(),
        ];
    }

    public function userActivity(string $dateFrom, string $dateTo, ?string $userId = null): array
    {
        $query = Activity::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if ($userId) $query->where('causer_id', $userId);

        $activities = $query->get();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_actions' => $activities->count(),
            'by_event' => $activities->groupBy('event')->map->count()->toArray(),
            'by_user' => $activities->groupBy('causer_id')->map(fn($items) => [
                'count' => $items->count(),
                'name' => $items->first()->causer?->name ?? 'غير معروف',
            ])->toArray(),
        ];
    }

    public function registerSummary(string $dateFrom, string $dateTo, ?string $registerId = null): array
    {
        $query = Receipt::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled');

        if ($registerId) $query->where('register_id', $registerId);

        $receipts = $query->with('register')->get();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_receipts' => $receipts->count(),
            'total_amount' => $receipts->sum('total_amount'),
            'cancelled_count' => Receipt::whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->where('status', 'cancelled')
                ->when($registerId, fn($q) => $q->where('register_id', $registerId))
                ->count(),
            'by_register' => $receipts->groupBy('register.name_ar')->map(fn($items) => [
                'count' => $items->count(),
                'amount' => $items->sum('total_amount'),
            ])->toArray(),
        ];
    }

    public function custom(array $filters): array
    {
        $query = Receipt::query()->with('register', 'creator', 'items');

        if (!empty($filters['register_id'])) {
            $query->where('register_id', $filters['register_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('created_by', $filters['user_id']);
        }
        if (!empty($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }
        if (!empty($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        $receipts = $query->orderByDesc('created_at')->get();

        return [
            'filters' => $filters,
            'total_receipts' => $receipts->count(),
            'total_amount' => $receipts->where('status', '!=', 'cancelled')->sum('total_amount'),
            'receipts' => $receipts->toArray(),
        ];
    }

    public function exportCsv(array $data, string $filename): string
    {
        $headers = ["رقم الوصل", "السجل", "المبلغ", "الحالة", "أمين الصندوق", "التاريخ"];
        $lines = [implode(',', array_map(fn($h) => "\"{$h}\"", $headers))];

        foreach ($data['receipts'] ?? [] as $r) {
            $lines[] = implode(',', [
                '"' . ($r['receipt_number'] ?? '') . '"',
                '"' . ($r['register']['name_ar'] ?? '') . '"',
                $r['total_amount'] ?? 0,
                '"' . ($r['status'] ?? '') . '"',
                '"' . ($r['creator']['name'] ?? '') . '"',
                '"' . ($r['created_at'] ?? '') . '"',
            ]);
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", $lines); // UTF-8 BOM for Excel
        return $csv;
    }
}

<?php

namespace App\Services;

use Spatie\Activitylog\Models\Activity;

class AuditService
{
    public function index(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Activity::query()->with('causer');

        if (!empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }
        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }
        if (!empty($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 25);
    }
}

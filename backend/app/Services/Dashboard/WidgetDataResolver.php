<?php

namespace App\Services\Dashboard;

use App\Models\DashboardLayoutWidget;
use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WidgetDataResolver
{
    protected const STATIC_WIDGETS = ['clock', 'weather', 'youtube_audio', 'text_block', 'iframe'];

    public function resolve(DashboardLayoutWidget $widget, ?User $user = null): array
    {
        $type = $widget->widget_type;
        $source = is_array($widget->data_source) ? $widget->data_source : json_decode($widget->data_source ?? '[]', true);
        $display = is_array($widget->display_config) ? $widget->display_config : json_decode($widget->display_config ?? '[]', true);

        if (!is_array($source)) {
            $source = [];
        }
        if (!is_array($display)) {
            $display = [];
        }

        if (in_array($type, self::STATIC_WIDGETS, true)) {
            return [
                'widget_id' => $widget->id,
                'widget_type' => $type,
                'title' => $widget->title,
                'display_config' => $display,
                'data' => $source,
            ];
        }

        $registerId = $source['register_id'] ?? $widget->register_id;

        if (!$registerId) {
            return $this->emptyResponse($widget, 'No register configured');
        }

        $register = Register::find($registerId);
        if (!$register) {
            return $this->emptyResponse($widget, 'Register not found');
        }

        if ($user && !$this->canReadRegister($user, $register)) {
            return $this->emptyResponse($widget, 'Forbidden', 403);
        }

        return match ($type) {
            'stat_card' => $this->resolveStatCard($widget, $register, $source, $display),
            'chart_bar', 'chart_line', 'chart_pie' => $this->resolveChart($widget, $register, $source, $display),
            'table' => $this->resolveTable($widget, $register, $source, $display),
            'progress' => $this->resolveProgress($widget, $register, $source, $display),
            'gauge' => $this->resolveGauge($widget, $register, $source, $display),
            default => $this->resolveGeneric($widget, $register, $source, $display),
        };
    }

    protected function canReadRegister(User $user, Register $register): bool
    {
        $permission = "read-register-{$register->code}";

        if (!\App\Models\Permission::where('name', $permission)->where('guard_name', 'api')->exists()) {
            return false;
        }

        return $user->hasPermissionTo($permission, 'api');
    }

    protected function baseResponse(DashboardLayoutWidget $widget, array $payload): array
    {
        return array_merge([
            'widget_id' => $widget->id,
            'widget_type' => $widget->widget_type,
            'title' => $widget->title,
            'display_config' => $widget->display_config ?? [],
        ], $payload);
    }

    protected function emptyResponse(DashboardLayoutWidget $widget, string $message = 'No data', int $code = 200): array
    {
        return $this->baseResponse($widget, [
            'data' => null,
            'meta' => ['message' => $message, 'code' => $code],
        ]);
    }

    protected function buildQuery(string $registerId, array $filters = [], string $filtersOperator = 'and')
    {
        $query = DB::table('records')
            ->where('register_id', $registerId)
            ->whereNull('deleted_at');

        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $column = "data->{$field}";

            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                if ($filtersOperator === 'or') {
                    $query->orWhere($column, $value);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        return $query;
    }

    protected function fetchRecords(string $registerId, array $filters = [], string $sortBy = 'created_at', string $sortOrder = 'desc', ?int $limit = null)
    {
        $query = $this->buildQuery($registerId, $filters)
            ->orderBy($sortBy, $sortOrder);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    protected function numericValue($record, ?string $field): string
    {
        if (!$field) {
            return '0';
        }

        $data = is_string($record->data) ? json_decode($record->data, true) : ($record->data ?? []);
        $value = $data[$field] ?? 0;

        if (is_numeric($value)) {
            return (string) $value;
        }

        return '0';
    }

    protected function aggregate(iterable $records, string $aggregation, ?string $field): string
    {
        $count = 0;
        $sum = '0';
        $min = null;
        $max = null;

        foreach ($records as $record) {
            $value = $this->numericValue($record, $field);
            $count++;

            if ($aggregation === 'sum' || $aggregation === 'avg') {
                $sum = bcadd($sum, $value, 4);
            } elseif ($aggregation === 'min') {
                $min = $min === null ? $value : min($min, $value);
            } elseif ($aggregation === 'max') {
                $max = $max === null ? $value : max($max, $value);
            }
        }

        return match ($aggregation) {
            'count' => (string) $count,
            'sum' => $sum,
            'avg' => $count > 0 ? bcdiv($sum, (string) $count, 4) : '0',
            'min' => $min ?? '0',
            'max' => $max ?? '0',
            default => $sum,
        };
    }

    protected function resolveStatCard(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        $aggregation = $source['aggregation'] ?? 'sum';
        $field = $source['field'] ?? null;
        $filters = $source['filters'] ?? [];

        $records = $this->buildQuery($register->id, $filters)->get();
        $value = $this->aggregate($records, $aggregation, $field);

        return $this->baseResponse($widget, [
            'data' => [
                'value' => $value,
                'aggregation' => $aggregation,
                'field' => $field,
                'register_id' => $register->id,
            ],
        ]);
    }

    protected function resolveChart(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        $aggregation = $source['aggregation'] ?? 'sum';
        $field = $source['field'] ?? null;
        $groupBy = $source['group_by'] ?? 'period';
        $groupField = $source['group_field'] ?? null;
        $period = $source['period'] ?? 'month';
        $filters = $source['filters'] ?? [];

        $records = $this->buildQuery($register->id, $filters)->get();
        $groups = [];

        foreach ($records as $record) {
            $data = is_string($record->data) ? json_decode($record->data, true) : ($record->data ?? []);

            if ($groupBy === 'field' && $groupField) {
                $key = (string) ($data[$groupField] ?? 'unknown');
            } else {
                $key = $this->periodKey($record->created_at, $period);
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $record;
        }

        $labels = [];
        $values = [];
        ksort($groups);

        foreach ($groups as $key => $groupRecords) {
            $labels[] = $key;
            $values[] = $this->aggregate($groupRecords, $aggregation, $field);
        }

        return $this->baseResponse($widget, [
            'data' => [
                'labels' => $labels,
                'values' => $values,
                'aggregation' => $aggregation,
                'field' => $field,
                'group_by' => $groupBy,
                'register_id' => $register->id,
            ],
        ]);
    }

    protected function periodKey(?string $timestamp, string $period): string
    {
        if (!$timestamp) {
            return 'unknown';
        }

        $dt = \Carbon\Carbon::parse($timestamp);

        return match ($period) {
            'day' => $dt->format('Y-m-d'),
            'week' => $dt->format('Y-W'),
            'month' => $dt->format('Y-m'),
            'year' => $dt->format('Y'),
            default => $dt->format('Y-m'),
        };
    }

    protected function resolveTable(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        $fields = $source['fields'] ?? [];
        $filters = $source['filters'] ?? [];
        $sortBy = $source['sort_by'] ?? 'created_at';
        $sortOrder = $source['sort_order'] ?? 'desc';
        $perPage = $source['per_page'] ?? 10;

        $query = $this->buildQuery($register->id, $filters)
            ->orderBy($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage);
        $rows = [];

        foreach ($paginator->items() as $record) {
            $data = is_string($record->data) ? json_decode($record->data, true) : ($record->data ?? []);
            $row = [
                'id' => $record->id,
                'record_number' => $record->record_number,
                'created_at' => $record->created_at,
            ];

            foreach ($fields as $field) {
                $row[$field] = $data[$field] ?? null;
            }

            $rows[] = $row;
        }

        return $this->baseResponse($widget, [
            'data' => [
                'rows' => $rows,
                'fields' => $fields,
                'register_id' => $register->id,
            ],
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    protected function resolveProgress(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        $aggregation = $source['aggregation'] ?? 'sum';
        $field = $source['field'] ?? null;
        $filters = $source['filters'] ?? [];
        $target = $source['target'] ?? '100';

        $records = $this->buildQuery($register->id, $filters)->get();
        $value = $this->aggregate($records, $aggregation, $field);

        $percentage = '0';
        if (bccomp($target, '0', 4) !== 0) {
            $percentage = bcmul(bcdiv($value, $target, 6), '100', 2);
            $percentage = bcdiv($percentage, '1', 2);
        }

        return $this->baseResponse($widget, [
            'data' => [
                'value' => $value,
                'target' => $target,
                'percentage' => $percentage,
                'aggregation' => $aggregation,
                'field' => $field,
                'register_id' => $register->id,
            ],
        ]);
    }

    protected function resolveGauge(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        $aggregation = $source['aggregation'] ?? 'sum';
        $field = $source['field'] ?? null;
        $filters = $source['filters'] ?? [];
        $min = $source['min'] ?? '0';
        $max = $source['max'] ?? '100';

        $records = $this->buildQuery($register->id, $filters)->get();
        $value = $this->aggregate($records, $aggregation, $field);

        $range = bcsub($max, $min, 4);
        $percentage = '0';
        if (bccomp($range, '0', 4) !== 0) {
            $percentage = bcmul(bcdiv(bcsub($value, $min, 6), $range, 6), '100', 2);
            $percentage = bcdiv($percentage, '1', 2);
        }

        return $this->baseResponse($widget, [
            'data' => [
                'value' => $value,
                'min' => $min,
                'max' => $max,
                'percentage' => $percentage,
                'aggregation' => $aggregation,
                'field' => $field,
                'register_id' => $register->id,
            ],
        ]);
    }

    protected function resolveGeneric(DashboardLayoutWidget $widget, Register $register, array $source, array $display): array
    {
        return $this->baseResponse($widget, [
            'data' => $source,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DashboardLayout;
use App\Models\DashboardLayoutWidget;
use App\Models\Register;
use App\Models\RegisterField;
use App\Services\Dashboard\WidgetDataResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardWidgetController extends ApiController
{
    public function getLayout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $layout = DashboardLayout::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if (!$layout) {
            $layout = $this->createDefaultLayout($user);
        }

        return $this->success($layout->load('widgets'));
    }

    protected function createDefaultLayout($user): DashboardLayout
    {
        return DB::transaction(function () use ($user) {
            $receiptsRegister = Register::where('code', 'receipts')->first();

            $registerId = $receiptsRegister?->id;
            $registerCode = $receiptsRegister?->code ?? 'default';

            $layout = DashboardLayout::create([
                'name' => 'الداشبورد الافتراضي',
                'user_id' => $user->id,
                'is_default' => true,
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $amountField = null;
            if ($registerId) {
                $amountField = RegisterField::where('register_id', $registerId)
                    ->where(function ($query) {
                        $query->where('name', 'amount')
                            ->orWhere('is_financial', true);
                    })
                    ->orderByRaw("CASE WHEN name = 'amount' THEN 0 ELSE 1 END")
                    ->orderBy('is_financial', 'desc')
                    ->value('name');
            }

            $amountField ??= 'amount';

            $widgets = [
                [
                    'widget_type' => 'stat_card',
                    'title' => ['ar' => 'إجمالي المبالغ', 'en' => 'Total Amount'],
                    'data_source' => [
                        'register_id' => $registerId,
                        'aggregation' => 'sum',
                        'field' => $amountField,
                    ],
                    'display_config' => ['format' => 'currency', 'color' => 'primary'],
                    'position_x' => 0,
                    'position_y' => 0,
                    'width' => 3,
                    'height' => 1,
                    'sort_order' => 0,
                    'register_id' => $registerId,
                ],
                [
                    'widget_type' => 'stat_card',
                    'title' => ['ar' => 'عدد السجلات', 'en' => 'Record Count'],
                    'data_source' => [
                        'register_id' => $registerId,
                        'aggregation' => 'count',
                    ],
                    'display_config' => ['format' => 'number', 'color' => 'info'],
                    'position_x' => 3,
                    'position_y' => 0,
                    'width' => 3,
                    'height' => 1,
                    'sort_order' => 1,
                    'register_id' => $registerId,
                ],
                [
                    'widget_type' => 'stat_card',
                    'title' => ['ar' => 'المتوسط', 'en' => 'Average Amount'],
                    'data_source' => [
                        'register_id' => $registerId,
                        'aggregation' => 'avg',
                        'field' => $amountField,
                    ],
                    'display_config' => ['format' => 'currency', 'color' => 'success'],
                    'position_x' => 6,
                    'position_y' => 0,
                    'width' => 3,
                    'height' => 1,
                    'sort_order' => 2,
                    'register_id' => $registerId,
                ],
                [
                    'widget_type' => 'stat_card',
                    'title' => ['ar' => 'الحد الأقصى', 'en' => 'Maximum Amount'],
                    'data_source' => [
                        'register_id' => $registerId,
                        'aggregation' => 'max',
                        'field' => $amountField,
                    ],
                    'display_config' => ['format' => 'currency', 'color' => 'warning'],
                    'position_x' => 9,
                    'position_y' => 0,
                    'width' => 3,
                    'height' => 1,
                    'sort_order' => 3,
                    'register_id' => $registerId,
                ],
                [
                    'widget_type' => 'table',
                    'title' => ['ar' => 'آخر السجلات', 'en' => 'Recent Records'],
                    'data_source' => [
                        'register_id' => $registerId,
                        'fields' => [$amountField, 'created_at'],
                        'sort_by' => 'created_at',
                        'sort_order' => 'desc',
                        'per_page' => 5,
                    ],
                    'display_config' => ['format' => 'table'],
                    'position_x' => 0,
                    'position_y' => 1,
                    'width' => 12,
                    'height' => 3,
                    'sort_order' => 4,
                    'register_id' => $registerId,
                ],
            ];

            foreach ($widgets as $index => $widgetData) {
                $widgetData['layout_id'] = $layout->id;
                $widgetData['created_by'] = $user->id;
                $widgetData['updated_by'] = $user->id;
                $widgetData['sort_order'] = $index;
                DashboardLayoutWidget::create($widgetData);
            }

            return $layout;
        });
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        try {
            $validated = $request->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'widgets' => ['required', 'array'],
                'widgets.*.widget_type' => ['required', 'string', 'max:100'],
                'widgets.*.title' => ['nullable', 'array'],
                'widgets.*.data_source' => ['nullable', 'array'],
                'widgets.*.display_config' => ['nullable', 'array'],
                'widgets.*.register_id' => ['nullable', 'string'],
                'widgets.*.position_x' => ['nullable', 'integer'],
                'widgets.*.position_y' => ['nullable', 'integer'],
                'widgets.*.width' => ['nullable', 'integer'],
                'widgets.*.height' => ['nullable', 'integer'],
                'widgets.*.sort_order' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 'VALIDATION_ERROR', 422);
        }

        $layout = DB::transaction(function () use ($user, $validated) {
            $layout = DashboardLayout::firstOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                [
                    'name' => $validated['name'] ?? 'الداشبورد الافتراضي',
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            if (!empty($validated['name'])) {
                $layout->update([
                    'name' => $validated['name'],
                    'updated_by' => $user->id,
                ]);
            }

            DashboardLayoutWidget::where('layout_id', $layout->id)->delete();

            foreach ($validated['widgets'] as $index => $widgetData) {
                $widgetData['layout_id'] = $layout->id;
                $widgetData['sort_order'] = $widgetData['sort_order'] ?? $index;
                $widgetData['created_by'] = $user->id;
                $widgetData['updated_by'] = $user->id;
                DashboardLayoutWidget::create($widgetData);
            }

            return $layout;
        });

        return $this->success($layout->load('widgets'));
    }

    public function getWidgetData(Request $request, WidgetDataResolver $resolver, ?string $widgetId = null): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $widgetData = $widgetId
            ? DashboardLayoutWidget::with('register')->find($widgetId)?->toArray()
            : ($request->input('widget') ?? $request->all());

        if (empty($widgetData)) {
            return $this->error('Widget not found', [], 'NOT_FOUND', 404);
        }

        $widget = new DashboardLayoutWidget($widgetData);
        $widget->exists = false;

        if ($widget->register_id) {
            $register = $widget->register;
            if ($register && !$this->canReadRegister($user, $register)) {
                return $this->error('Forbidden', [], 'FORBIDDEN', 403);
            }
        }

        $data = $resolver->resolve($widget, $user);

        if (isset($data['meta']['code']) && $data['meta']['code'] === 403) {
            return $this->error('Forbidden', [], 'FORBIDDEN', 403);
        }

        return $this->success($data);
    }

    public function getAvailableRegisters(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $registers = Register::with('fields')->where('is_active', true)->get();

        $registers = $registers->filter(function ($register) use ($user) {
            return $this->canReadRegister($user, $register);
        })->values();

        return $this->success($registers);
    }

    public function getRegisterFields(string $registerId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $register = Register::find($registerId);

        if (!$register) {
            return $this->error('Register not found', [], 'NOT_FOUND', 404);
        }

        if (!$this->canReadRegister($user, $register)) {
            return $this->error('Forbidden', [], 'FORBIDDEN', 403);
        }

        return $this->success($register->fields);
    }

    protected function canReadRegister($user, Register $register): bool
    {
        $permission = "read-register-{$register->code}";

        if (!\App\Models\Permission::where('name', $permission)->where('guard_name', 'api')->exists()) {
            return false;
        }

        return $user->hasPermissionTo($permission, 'api');
    }
}

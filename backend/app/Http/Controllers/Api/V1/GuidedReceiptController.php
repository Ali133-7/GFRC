<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OfficialFee;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\TransactionTemplate;
use App\Models\TemplateRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuidedReceiptController extends ApiController
{
    public function build(Request $request): JsonResponse
    {
        $this->authorize('create', Receipt::class);
        $data = $request->validate([
            'template_id' => 'required|string|exists:transaction_templates,id',
            'values' => 'required|array',
        ]);

        $template = TransactionTemplate::with(['fields.registerField', 'rules'])->findOrFail($data['template_id']);
        $register = Register::with('fields')->findOrFail($template->register_id);
        $values = $data['values'];

        $computed = [];
        $visibleFields = [];

        foreach ($template->fields as $tf) {
            $field = $tf->registerField;
            if (!$field) continue;

            $visibleFields[$field->id] = [
                'amount' => null,
                'value' => $values[$field->name] ?? $tf->default_value ?? null,
                'visible' => $tf->is_visible,
                'readonly' => $tf->is_readonly,
            ];
        }

        foreach ($template->rules as $rule) {
            if (!$rule->is_active) continue;
            $triggerValue = $values[$rule->triggerField->name] ?? null;
            $matches = $this->evaluateRule($rule, $triggerValue);

            if ($matches && isset($visibleFields[$rule->target_field_id])) {
                match ($rule->action) {
                    'set_value' => $visibleFields[$rule->target_field_id]['value'] = $rule->action_value,
                    'set_amount' => $visibleFields[$rule->target_field_id]['amount'] = $rule->action_value,
                    'hide' => $visibleFields[$rule->target_field_id]['visible'] = false,
                    'show' => $visibleFields[$rule->target_field_id]['visible'] = true,
                    default => null,
                };
            }
        }

        $items = [];
        $total = 0;

        foreach ($register->fields as $rf) {
            if (!isset($visibleFields[$rf->id]) || !$visibleFields[$rf->id]['visible']) {
                continue;
            }

            $vf = $visibleFields[$rf->id];
            $amount = $vf['amount'];
            $textValue = $vf['value'];

            if ($rf->is_financial && $amount === null) {
                $amount = is_numeric($textValue) ? (float) $textValue : 0;
            }

            $amt = $amount !== null ? (float) $amount : null;
            if ($amt !== null && $amt > 0) {
                $total += $amt;
            }

            $items[] = [
                'field_id' => $rf->id,
                'field_name_snapshot' => $rf->name,
                'label_ar_snapshot' => $rf->label_ar,
                'amount' => $amt !== null ? number_format($amt, 3, '.', '') : null,
                'text_value' => $textValue,
            ];
        }

        return $this->success([
            'template' => [
                'id' => $template->id,
                'name_ar' => $template->name_ar,
                'register_id' => $template->register_id,
            ],
            'items' => $items,
            'total_amount' => number_format($total, 3, '.', ''),
            'values' => $values,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Receipt::class);
        $data = $request->validate([
            'template_id' => 'required|string|exists:transaction_templates,id',
            'values' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $template = TransactionTemplate::findOrFail($data['template_id']);
        $register = Register::findOrFail($template->register_id);

        $build = $this->build($request->replace([
            'template_id' => $data['template_id'],
            'values' => $data['values'],
        ]));
        $buildData = $build->getData(true)['data'] ?? [];

        return DB::transaction(function () use ($register, $buildData, $data, $template) {
            $receipt = Receipt::create([
                'id' => (string) Str::uuid(),
                'receipt_number' => $register->generateReceiptNumber(),
                'register_id' => $register->id,
                'created_by' => auth()->id(),
                'total_amount' => $buildData['total_amount'] ?? '0.000',
                'status' => 'issued',
                'version' => 1,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($buildData['items'] ?? [] as $item) {
                $item['id'] = (string) Str::uuid();
                $item['receipt_id'] = $receipt->id;
                ReceiptItem::create($item);
            }

            $template->increment('usage_count');

            return $this->success($receipt->load('items'), 'تم إصدار الوصل بنجاح');
        });
    }

    protected function evaluateRule(TemplateRule $rule, mixed $value): bool
    {
        $trigger = (string) $rule->trigger_value;
        $current = (string) ($value ?? '');

        return match ($rule->trigger_operator) {
            'equals' => $current === $trigger,
            'not_equals' => $current !== $trigger,
            'contains' => str_contains($current, $trigger),
            'gt' => is_numeric($current) && is_numeric($trigger) && (float) $current > (float) $trigger,
            'lt' => is_numeric($current) && is_numeric($trigger) && (float) $current < (float) $trigger,
            default => false,
        };
    }
}

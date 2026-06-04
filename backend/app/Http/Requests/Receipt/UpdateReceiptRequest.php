<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('id');
        if (is_string($receipt)) {
            $receipt = \App\Models\Receipt::find($receipt);
        }
        return $receipt && in_array($receipt->status, ['draft', 'pending']) && $this->user()->can('create-receipt');
    }

    public function rules(): array
    {
        return [
            'total_amount' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_id' => ['required', 'uuid', 'exists:register_fields,id'],
            'items.*.value' => ['nullable'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $receipt = $this->route('id');
            if (is_string($receipt)) {
                $receipt = \App\Models\Receipt::find($receipt);
            }
            if (!$receipt) {
                return;
            }
            $register = $receipt->register;
            $fields = $register->fields;
            $items = collect($this->input('items', []));
            $sum = '0';
            foreach ($items as $idx => $item) {
                $field = $fields->firstWhere('id', $item['field_id'] ?? null);
                if (!$field) {
                    $validator->errors()->add("items.{$idx}.field_id", 'الحقل غير موجود في هذا السجل');
                    continue;
                }
                if ($field->is_required && (is_null($item['value'] ?? null) && is_null($item['amount'] ?? null))) {
                    $validator->errors()->add("items.{$idx}.value", 'هذا الحقل مطلوب');
                }
                if ($field->is_financial) {
                    $amt = isset($item['amount']) ? (string) $item['amount'] : '0';
                    if (bccomp($amt, '0', 3) < 0) {
                        $validator->errors()->add("items.{$idx}.amount", 'المبلغ يجب أن يكون موجباً');
                    }
                    $sum = bcadd($sum, $amt, 3);
                }
            }
            $total = (string) $this->input('total_amount', '0');
            if (bccomp($sum, $total, 3) !== 0) {
                $validator->errors()->add('total_amount', 'المبلغ الإجمالي لا يطابق مجموع الحقول المالية');
            }
        });
    }
}

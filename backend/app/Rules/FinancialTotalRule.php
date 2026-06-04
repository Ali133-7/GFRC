<?php

namespace App\Rules;

use App\Models\RegisterField;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FinancialTotalRule implements ValidationRule
{
    protected array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $field = RegisterField::find($item['field_id'] ?? null);
            if ($field && $field->is_financial) {
                $amount = isset($item['amount']) ? (float) $item['amount'] : 0;
                if ($amount < 0) {
                    $fail('لا يمكن أن يكون المبلغ سالباً');
                    return;
                }
                $sum += $amount;
            }
        }

        if (bccomp((string) $sum, (string) $value, 3) !== 0) {
            $fail('المبلغ الكلي لا يطابق مجموع الحقول المالية');
        }

        if (bccomp((string) $value, '0', 3) <= 0) {
            $fail('يجب أن يكون المبلغ الكلي أكبر من صفر');
        }
    }
}

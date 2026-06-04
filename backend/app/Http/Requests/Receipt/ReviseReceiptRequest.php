<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class ReviseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('revise-receipt');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10'],
            'total_amount' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_id' => ['required', 'uuid'],
            'items.*.value' => ['nullable'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

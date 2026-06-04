<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class CancelReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel-receipt');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10'],
        ];
    }
}

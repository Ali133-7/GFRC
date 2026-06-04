<?php

namespace App\Http\Requests\Register;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRegisterFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-registers');
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'uuid', 'exists:register_fields,id'],
            'fields.*.sort_order' => ['required', 'integer'],
        ];
    }
}

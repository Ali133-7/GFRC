<?php

namespace App\Http\Requests\Register;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegisterFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-registers');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'label_ar' => ['required', 'string', 'max:200'],
            'label_en' => ['nullable', 'string', 'max:200'],
            'field_type' => ['required', 'in:text,number,decimal,date,select,textarea,hidden,calculated'],
            'is_required' => ['boolean'],
            'is_visible' => ['boolean'],
            'is_financial' => ['boolean'],
            'sort_order' => ['integer'],
            'validation_rules' => ['nullable', 'string', 'max:500'],
            'default_value' => ['nullable', 'string', 'max:500'],
            'options' => ['nullable', 'array'],
        ];
    }
}

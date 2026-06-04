<?php

namespace App\Http\Requests\Register;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-registers');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:registers,code'],
            'name_ar' => ['required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}

<?php

namespace App\Http\Requests\Register;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-registers');
    }

    public function rules(): array
    {
        $registerId = $this->route('id');
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('registers')->ignore($registerId)],
            'name_ar' => ['required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}

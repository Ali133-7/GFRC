<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class MonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-reports');
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
        ];
    }
}

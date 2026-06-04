<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSummaryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-reports');
    }

    public function rules(): array
    {
        return [
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
        ];
    }
}

<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class DailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-reports');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'register_id' => ['nullable', 'uuid', 'exists:registers,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}

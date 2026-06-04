<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UserActivityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-reports');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
        ];
    }
}

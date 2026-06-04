<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class CustomReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'register_id' => 'nullable|string|uuid',
            'status' => 'nullable|in:draft,pending,issued,printed,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'user_id' => 'nullable|string|uuid',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|gte:min_amount',
        ];
    }
}

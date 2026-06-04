<?php

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view-audit-logs');
    }

    public function rules(): array
    {
        return [
            'subject_type' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'string'],
            'causer_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

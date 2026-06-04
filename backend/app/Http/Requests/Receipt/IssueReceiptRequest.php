<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class IssueReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('issue-receipt');
    }

    public function rules(): array
    {
        return [];
    }
}

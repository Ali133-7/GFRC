<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        $userId = $this->route('id');
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}

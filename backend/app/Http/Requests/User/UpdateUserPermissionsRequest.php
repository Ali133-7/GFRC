<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-users');
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $permissions = $this->input('permissions', []);

            $existingPerms = DB::table('permissions')
                ->whereIn('name', $permissions)
                ->where('guard_name', 'api')
                ->pluck('name')
                ->toArray();

            // Check if any permissions don't exist
            $invalidPerms = array_diff($permissions, $existingPerms);
            if (!empty($invalidPerms)) {
                $validator->errors()->add(
                    'permissions',
                    'Invalid permissions: ' . implode(', ', $invalidPerms)
                );
            }
        });
    }
}

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
            $targetUser = \App\Models\User::findOrFail($this->route('id'));
            $permissions = $this->input('permissions', []);

            $existingPerms = DB::table('permissions')
                ->whereIn('name', $permissions)
                ->where('guard_name', 'api')
                ->pluck('name')
                ->toArray();

            if (in_array('system.reset', $permissions) && !in_array('system.reset', $existingPerms)) {
                $validator->errors()->add(
                    'permissions',
                    'صلاحية system.reset غير موجودة في النظام'
                );
            }

            if (in_array('system.reset', $existingPerms) && !$targetUser->hasRole('super_admin')) {
                $validator->errors()->add(
                    'permissions',
                    'لا يمكن منح صلاحية حذف كافة البيانات إلا لمستخدمي super_admin'
                );
            }

            $currentUser = $this->user();
            if (in_array('system.reset', $existingPerms) && !$currentUser->hasRole('super_admin')) {
                $validator->errors()->add(
                    'permissions',
                    'يجب أن تكون super_admin لمنح صلاحية حذف كافة البيانات'
                );
            }
        });
    }
}

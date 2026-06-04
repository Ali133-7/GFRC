<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        return $this->success($roles);
    }

    public function store(): JsonResponse
    {
        $data = request()->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $this->success($role->load('permissions'), 'تم إنشاء الدور بنجاح');
    }

    public function updatePermissions(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $data = request()->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions']);
        return $this->success($role->load('permissions'), 'تم تحديث صلاحيات الدور بنجاح');
    }
}

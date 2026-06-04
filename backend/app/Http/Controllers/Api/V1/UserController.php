<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Http\Requests\User\UpdateUserPermissionsRequest;
use App\Http\Resources\UserResource;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class UserController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('view', User::class);
        $users = User::with('roles')
            ->when(request('search'), fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->paginate(request('per_page', 25));

        return $this->success(
            UserResource::collection($users),
            '',
            $this->paginationMeta($users)
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('manage', User::class);
        $data = $request->validated();
        $data['id'] = (string) Str::uuid();
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $this->success(new UserResource($user->load('roles')), 'تم إنشاء المستخدم بنجاح');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', User::class);
        $user = User::with('roles')->findOrFail($id);
        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $this->authorize('manage', User::class);
        $user = User::findOrFail($id);
        $data = $request->validated();
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $this->success(new UserResource($user->load('roles')), 'تم تحديث المستخدم بنجاح');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('manage', User::class);
        $user = User::findOrFail($id);
        $user->delete();
        return $this->success([], 'تم حذف المستخدم بنجاح');
    }

    public function updateRoles(UpdateUserRolesRequest $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->authorize('updateRoles', $user);
        $user->syncRoles($request->input('roles'));
        return $this->success(new UserResource($user->load('roles')), 'تم تحديث الأدوار بنجاح');
    }

    public function updatePermissions(UpdateUserPermissionsRequest $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $requestedPermissions = $request->input('permissions', []);

        $validPermissionNames = DB::table('permissions')
            ->whereIn('name', $requestedPermissions)
            ->where('guard_name', 'api')
            ->pluck('name')
            ->toArray();

        $directPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $permissionsToRemove = array_diff($directPermissions, $validPermissionNames);
        $permissionsToAdd = array_diff($validPermissionNames, $directPermissions);

        foreach ($permissionsToRemove as $perm) {
            $user->revokePermissionTo($perm);
        }
        foreach ($permissionsToAdd as $perm) {
            $exists = DB::table('permissions')
                ->where('name', $perm)
                ->where('guard_name', 'api')
                ->exists();
            if ($exists) {
                $user->givePermissionTo($perm);
            }
        }

        return $this->success(new UserResource($user->load('roles')), 'تم تحديث الصلاحيات بنجاح');
    }

    public function activitySummary(string $id): JsonResponse
    {
        $this->authorize('view', User::class);
        $user = User::findOrFail($id);

        $totalReceipts = Receipt::where('created_by', $id)->count();
        $totalIssued = Receipt::where('created_by', $id)->where('status', 'issued')->count();
        $totalCancelled = Receipt::where('created_by', $id)->where('status', 'cancelled')->count();
        $totalAmount = Receipt::where('created_by', $id)->where('status', '!=', 'cancelled')->sum('total_amount');

        $recentActivity = Activity::where('causer_id', $id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'event' => $a->event,
                'description' => $a->description,
                'created_at' => $a->created_at,
            ]);

        return $this->success([
            'user' => new UserResource($user),
            'summary' => [
                'total_receipts' => $totalReceipts,
                'total_issued' => $totalIssued,
                'total_cancelled' => $totalCancelled,
                'total_amount' => $totalAmount,
                'last_login_at' => $user->last_login_at,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }
}

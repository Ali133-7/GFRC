<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user): bool
    {
        return $user->hasAnyPermission(['view-users', 'manage-users']);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('manage-users');
    }

    public function updateRoles(User $user, User $target): bool
    {
        return $user->hasPermissionTo('manage-users');
    }
}

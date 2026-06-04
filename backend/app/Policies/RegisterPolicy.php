<?php

namespace App\Policies;

use App\Models\Register;
use App\Models\User;

class RegisterPolicy
{
    public function view(User $user): bool
    {
        return $user->hasAnyPermission(['view-registers', 'manage-registers']);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('manage-registers');
    }

    public function manageFields(User $user, ?Register $register = null): bool
    {
        return $user->hasPermissionTo('manage-registers');
    }
}

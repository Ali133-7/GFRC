<?php

namespace App\Policies;

use App\Models\User;

class SystemResetPolicy
{
    public function reset(User $user): bool
    {
        return $user->hasPermissionTo('system.reset');
    }
}

<?php

namespace App\Policies;

use App\Models\TransactionTemplate;
use App\Models\User;

class TransactionTemplatePolicy
{
    public function view(User $user): bool
    {
        return $user->hasAnyPermission(['view-receipt', 'create-receipt', 'manage-registers', 'manage-settings']);
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyPermission(['manage-registers', 'manage-settings']);
    }
}

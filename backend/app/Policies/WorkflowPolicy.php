<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    public function view(User $user, Workflow $workflow = null): bool
    {
        return $user->hasPermissionTo('view-receipt') || $user->hasPermissionTo('manage-settings');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage-settings');
    }

    public function update(User $user, Workflow $workflow = null): bool
    {
        return $user->hasPermissionTo('manage-settings');
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('manage-settings');
    }
}

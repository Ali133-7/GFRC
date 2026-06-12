<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowExecution;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowExecutionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any executions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-receipt', 'api') 
            || $user->hasPermissionTo('manage-settings', 'api')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can view a specific execution.
     */
    public function view(User $user, WorkflowExecution $execution): bool
    {
        // super_admin can view all
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // User can view their own executions
        if ($execution->started_by === $user->id) {
            return true;
        }

        // Users with manage-settings can view all
        if ($user->hasPermissionTo('manage-settings', 'api')) {
            return true;
        }

        // Users with view-receipt can view executions for registers they have access to
        if ($user->hasPermissionTo('view-receipt', 'api')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create executions.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-receipt', 'api')
            || $user->hasPermissionTo('manage-settings', 'api')
            || $user->hasRole('super_admin');
    }

    /**
     * Determine if the user can update a specific execution.
     */
    public function update(User $user, WorkflowExecution $execution): bool
    {
        // Only the starter or super_admin can modify executions
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($execution->started_by === $user->id) {
            return $execution->isInProgress() || $execution->isPaused();
        }

        return false;
    }

    /**
     * Determine if the user can complete a specific execution.
     */
    public function complete(User $user, WorkflowExecution $execution): bool
    {
        return $this->update($user, $execution);
    }

    /**
     * Determine if the user can cancel a specific execution.
     */
    public function cancel(User $user, WorkflowExecution $execution): bool
    {
        // Only the starter or super_admin can cancel
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($execution->started_by === $user->id) {
            return $execution->isInProgress() || $execution->isPaused();
        }

        // Users with manage-settings can cancel any
        return $user->hasPermissionTo('manage-settings', 'api');
    }

    /**
     * Determine if the user can manage execution branching/routing.
     */
    public function branch(User $user, WorkflowExecution $execution): bool
    {
        return $this->update($user, $execution) 
            || $user->hasPermissionTo('manage-settings', 'api');
    }

    /**
     * Determine if the user can preview an execution.
     */
    public function preview(User $user): bool
    {
        return $user->hasPermissionTo('create-receipt', 'api')
            || $user->hasPermissionTo('manage-settings', 'api')
            || $user->hasRole('super_admin');
    }
}

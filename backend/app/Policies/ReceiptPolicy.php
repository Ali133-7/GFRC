<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function view(User $user): bool
    {
        return $user->hasAnyPermission(['view-receipt', 'create-receipt', 'issue-receipt', 'cancel-receipt', 'revise-receipt', 'print-receipt', 'manage-registers']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['create-receipt', 'issue-receipt', 'manage-registers']);
    }

    public function update(User $user, Receipt $receipt): bool
    {
        if (! $user->hasAnyPermission(['create-receipt', 'revise-receipt'])) {
            return false;
        }
        return in_array($receipt->status, ['draft', 'pending']);
    }

    public function issue(User $user, Receipt $receipt): bool
    {
        return $user->hasAnyPermission(['issue-receipt', 'cancel-receipt', 'revise-receipt']);
    }

    public function cancel(User $user, Receipt $receipt): bool
    {
        return $user->hasAnyPermission(['cancel-receipt', 'revise-receipt']);
    }

    public function revise(User $user, Receipt $receipt): bool
    {
        return $user->hasPermissionTo('revise-receipt')
            && in_array($receipt->status, ['issued', 'printed']);
    }

    public function print(User $user): bool
    {
        return $user->hasAnyPermission(['print-receipt', 'view-receipt']);
    }
}

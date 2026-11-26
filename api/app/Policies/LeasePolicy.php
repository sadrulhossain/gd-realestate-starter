<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('leases.view');
    }

    public function view(User $user, Lease $model): bool
    {
        return $user->checkPermissionTo('leases.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('leases.create');
    }

    public function update(User $user, Lease $model): bool
    {
        return $user->checkPermissionTo('leases.update');
    }

    public function delete(User $user, Lease $model): bool
    {
        return $user->checkPermissionTo('leases.delete');
    }
}

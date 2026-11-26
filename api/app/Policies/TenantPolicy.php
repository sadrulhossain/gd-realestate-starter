<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('tenants.view');
    }

    public function view(User $user, Tenant $model): bool
    {
        return $user->checkPermissionTo('tenants.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('tenants.create');
    }

    public function update(User $user, Tenant $model): bool
    {
        return $user->checkPermissionTo('tenants.update');
    }

    public function delete(User $user, Tenant $model): bool
    {
        return $user->checkPermissionTo('tenants.delete');
    }
}

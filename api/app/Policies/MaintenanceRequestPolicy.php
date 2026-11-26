<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('maintenance.view');
    }

    public function view(User $user, MaintenanceRequest $model): bool
    {
        return $user->checkPermissionTo('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('maintenance.create');
    }

    public function update(User $user, MaintenanceRequest $model): bool
    {
        return $user->checkPermissionTo('maintenance.update');
    }

    public function delete(User $user, MaintenanceRequest $model): bool
    {
        return $user->checkPermissionTo('maintenance.delete');
    }
}

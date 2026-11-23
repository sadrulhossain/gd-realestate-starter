<?php

namespace App\Policies;

use App\Models\ProjectUnit;
use App\Models\User;

class ProjectUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('project_units.view');
    }

    public function view(User $user, ProjectUnit $model): bool
    {
        return $user->checkPermissionTo('project_units.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('project_units.create');
    }

    public function update(User $user, ProjectUnit $model): bool
    {
        return $user->checkPermissionTo('project_units.update');
    }

    public function delete(User $user, ProjectUnit $model): bool
    {
        return $user->checkPermissionTo('project_units.delete');
    }

    /** Publishing creates a property, so both permissions are needed. */
    public function publish(User $user, ProjectUnit $model): bool
    {
        return $user->checkPermissionTo('project_units.update') && $user->checkPermissionTo('properties.create');
    }
}

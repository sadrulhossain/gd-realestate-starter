<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('projects.view');
    }

    public function view(User $user, Project $model): bool
    {
        return $user->checkPermissionTo('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('projects.create');
    }

    public function update(User $user, Project $model): bool
    {
        return $user->checkPermissionTo('projects.update');
    }

    public function delete(User $user, Project $model): bool
    {
        return $user->checkPermissionTo('projects.delete');
    }
}

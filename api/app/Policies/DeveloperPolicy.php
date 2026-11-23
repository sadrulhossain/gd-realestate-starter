<?php

namespace App\Policies;

use App\Models\Developer;
use App\Models\User;

class DeveloperPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('developers.view');
    }

    public function view(User $user, Developer $model): bool
    {
        return $user->checkPermissionTo('developers.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('developers.create');
    }

    public function update(User $user, Developer $model): bool
    {
        return $user->checkPermissionTo('developers.update');
    }

    public function delete(User $user, Developer $model): bool
    {
        return $user->checkPermissionTo('developers.delete');
    }
}

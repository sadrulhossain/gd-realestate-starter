<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viewing;

/** Own-records scope: without `viewings.view_all` a user reaches only the viewings of their agent profile. */
class ViewingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('viewings.view');
    }

    public function view(User $user, Viewing $model): bool
    {
        return $user->checkPermissionTo('viewings.view') && $this->reaches($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('viewings.create');
    }

    public function update(User $user, Viewing $model): bool
    {
        return $user->checkPermissionTo('viewings.update') && $this->reaches($user, $model);
    }

    public function delete(User $user, Viewing $model): bool
    {
        return $user->checkPermissionTo('viewings.delete') && $this->reaches($user, $model);
    }

    private function reaches(User $user, Viewing $viewing): bool
    {
        return $user->checkPermissionTo('viewings.view_all') || $viewing->agent_id === $user->agent?->id;
    }
}

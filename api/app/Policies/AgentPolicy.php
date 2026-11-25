<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('agents.view');
    }

    public function view(User $user, Agent $model): bool
    {
        return $user->checkPermissionTo('agents.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('agents.create');
    }

    public function update(User $user, Agent $model): bool
    {
        return $user->checkPermissionTo('agents.update');
    }

    public function delete(User $user, Agent $model): bool
    {
        return $user->checkPermissionTo('agents.delete');
    }
}

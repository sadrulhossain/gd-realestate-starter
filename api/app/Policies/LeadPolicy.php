<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/** Own-records scope: without `leads.view_all` a user reaches only the leads assigned to their agent profile. */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('leads.view');
    }

    public function view(User $user, Lead $model): bool
    {
        return $user->checkPermissionTo('leads.view') && $this->reaches($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('leads.create');
    }

    public function update(User $user, Lead $model): bool
    {
        return $user->checkPermissionTo('leads.update') && $this->reaches($user, $model);
    }

    public function assign(User $user, Lead $model): bool
    {
        return $user->checkPermissionTo('leads.assign');
    }

    public function delete(User $user, Lead $model): bool
    {
        return $user->checkPermissionTo('leads.delete') && $this->reaches($user, $model);
    }

    private function reaches(User $user, Lead $lead): bool
    {
        return $user->checkPermissionTo('leads.view_all')
            || ($lead->agent_id !== null && $lead->agent_id === $user->agent?->id);
    }
}

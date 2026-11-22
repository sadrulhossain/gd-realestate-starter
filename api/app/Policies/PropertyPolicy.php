<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('properties.view');
    }

    public function view(User $user, Property $property): bool
    {
        return $user->checkPermissionTo('properties.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('properties.create');
    }

    public function update(User $user, Property $property): bool
    {
        return $user->checkPermissionTo('properties.update');
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->checkPermissionTo('properties.delete');
    }
}

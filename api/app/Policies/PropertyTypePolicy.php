<?php

namespace App\Policies;

use App\Models\PropertyType;
use App\Models\User;

class PropertyTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('property_types.view');
    }

    public function view(User $user, PropertyType $property_type): bool
    {
        return $user->checkPermissionTo('property_types.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('property_types.create');
    }

    public function update(User $user, PropertyType $property_type): bool
    {
        return $user->checkPermissionTo('property_types.update');
    }

    public function delete(User $user, PropertyType $property_type): bool
    {
        return $user->checkPermissionTo('property_types.delete');
    }
}

<?php

namespace App\Policies;

use App\Models\Amenity;
use App\Models\User;

class AmenityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('amenities.view');
    }

    public function view(User $user, Amenity $amenity): bool
    {
        return $user->checkPermissionTo('amenities.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('amenities.create');
    }

    public function update(User $user, Amenity $amenity): bool
    {
        return $user->checkPermissionTo('amenities.update');
    }

    public function delete(User $user, Amenity $amenity): bool
    {
        return $user->checkPermissionTo('amenities.delete');
    }
}

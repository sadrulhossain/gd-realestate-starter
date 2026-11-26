<?php

namespace App\Policies;

use App\Models\RentCharge;
use App\Models\User;

/** Charges come from the lease schedule: no create or delete. `update` covers recording payments. */
class RentChargePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('rent_charges.view');
    }

    public function view(User $user, RentCharge $model): bool
    {
        return $user->checkPermissionTo('rent_charges.view');
    }

    public function update(User $user, RentCharge $model): bool
    {
        return $user->checkPermissionTo('rent_charges.update');
    }
}

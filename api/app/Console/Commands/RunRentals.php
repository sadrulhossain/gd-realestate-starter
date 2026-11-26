<?php

namespace App\Console\Commands;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Services\LeaseService;
use App\Services\PropertyStatusService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Daily rental upkeep: end leases past their end date, then mark every property with a running lease as rented. */
class RunRentals extends Command
{
    protected $signature = 'rentals:run';

    protected $description = 'End expired leases and sync property statuses';

    public function handle(LeaseService $leases, PropertyStatusService $properties): int
    {
        if (! config('rentals.enabled')) {
            $this->info('rentals are switched off');

            return self::SUCCESS;
        }

        $ended = $synced = $stuck = 0;
        $expired = Lease::query()->where('status', LeaseStatus::Active)->where('end_date', '<', today()->toDateString());

        foreach ($expired->lazyById() as $lease) {
            $leases->transition($lease, LeaseStatus::Ended, null, $lease->end_date);
            $ended++;
        }

        $running = Lease::query()->with('property')->where('status', LeaseStatus::Active)
            ->whereRelation('property', 'status', '!=', PropertyStatus::Rented->value);

        foreach ($running->lazyById() as $lease) {
            try {
                $properties->transition($lease->property, PropertyStatus::Rented, null, "Lease #{$lease->id} is active");
                $synced++;
            } catch (ValidationException) {
                $this->warn("lease #{$lease->id}: property {$lease->property->ref_code} is {$lease->property->status->value}, not changed");
                $stuck++;
            }
        }

        $this->info("leases ended: {$ended}, properties marked rented: {$synced}, left unchanged: {$stuck}");

        return self::SUCCESS;
    }
}

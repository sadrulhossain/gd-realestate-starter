<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place a lease's status changes. Activating writes the rent schedule and marks the property rented;
 * ending or terminating voids the unpaid later periods and frees the property.
 */
class LeaseService
{
    /** What was agreed: fixed once the lease leaves draft (the schedule was built from it). */
    private const TERMS = ['property_id', 'tenant_id', 'start_date', 'end_date', 'rent_amount', 'rent_period', 'deposit_amount'];

    public function __construct(private PropertyStatusService $properties) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when a term of a non-draft lease would change
     */
    public function update(Lease $lease, array $data): Lease
    {
        $lease->fill($data);

        if ($lease->status !== LeaseStatus::Draft) {
            $changed = array_values(array_filter(self::TERMS, fn (string $field) => $lease->isDirty($field)));

            if ($changed !== []) {
                throw ValidationException::withMessages(array_fill_keys($changed, ['Only a draft lease can change its terms.']));
            }
        }

        $lease->save();

        return $lease;
    }

    /** @throws ValidationException when the move is not allowed */
    public function transition(Lease $lease, LeaseStatus $to, ?User $actor = null, ?CarbonInterface $endedOn = null): Lease
    {
        return DB::transaction(function () use ($lease, $to, $actor, $endedOn): Lease {
            // Lock the property first: two activations for it cannot both pass the check.
            $property = Property::query()->lockForUpdate()->findOrFail($lease->property_id);
            $lease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if (! in_array($to, $lease->status->next(), true)) {
                $this->fail('status', "A lease cannot move from {$lease->status->value} to {$to->value}.");
            }

            match ($to) {
                LeaseStatus::Active => $this->activate($lease, $property, $actor),
                LeaseStatus::Ended, LeaseStatus::Terminated => $this->close($lease, $property, $to, $endedOn ?? today(), $actor),
                default => $lease->forceFill(['status' => $to])->save(),
            };

            return $lease;
        });
    }

    /**
     * One charge per period, due on its first day. Periods count from the start date (a lease from 31 January
     * is next due on the last day of February, then 31 March). A short last period is charged in full.
     *
     * @return list<array{period_start: string, period_end: string, due_date: string, amount: int}>
     */
    public function schedule(Lease $lease): array
    {
        $months = $lease->rent_period === 'year' ? 12 : 1;
        $start = $lease->start_date->toImmutable();
        $end = $lease->end_date->toImmutable();
        $rows = [];

        for ($i = 0; ($from = $start->addMonthsNoOverflow($i * $months))->lte($end); $i++) {
            $to = $start->addMonthsNoOverflow(($i + 1) * $months)->subDay();

            $rows[] = [
                'period_start' => $from->toDateString(),
                'period_end' => ($to->gt($end) ? $end : $to)->toDateString(),
                'due_date' => $from->toDateString(),
                'amount' => $lease->rent_amount,
            ];
        }

        return $rows;
    }

    private function activate(Lease $lease, Property $property, ?User $actor): void
    {
        if ($property->listing_type !== 'rent') {
            $this->fail('status', 'Only a rent listing can be leased.');
        }

        if ($lease->end_date->lt(today())) {
            $this->fail('status', 'This lease has already passed its end date.');
        }

        if (Lease::query()->where('property_id', $property->id)->where('status', LeaseStatus::Active)->exists()) {
            $this->fail('status', 'This property already has an active lease.');
        }

        if (! in_array($property->status, [PropertyStatus::Available, PropertyStatus::UnderOffer, PropertyStatus::Rented], true)) {
            $this->fail('status', "The property is {$property->status->value}: make it available first.");
        }

        $lease->forceFill(['status' => LeaseStatus::Active])->save();
        $lease->charges()->createMany($this->schedule($lease));

        if ($property->status !== PropertyStatus::Rented) {
            $this->properties->transition($property, PropertyStatus::Rented, $actor, "Lease #{$lease->id} started");
        }
    }

    private function close(Lease $lease, Property $property, LeaseStatus $to, CarbonInterface $on, ?User $actor): void
    {
        if ($on->gt(today())) {
            $this->fail('ended_on', 'The end date cannot be in the future.');
        }

        // A mistyped year must not void the whole schedule. (A lease that has not started yet can be closed today.)
        if ($on->lt($lease->start_date) && ! $on->isSameDay(today())) {
            $this->fail('ended_on', 'The end date cannot be before the lease starts.');
        }

        $lease->forceFill(['status' => $to, 'ended_on' => $on->toDateString()])->save();

        // Periods that had not started are not owed. Anything already paid towards stays on the books.
        $lease->charges()->where('period_start', '>', $on->toDateString())->where('paid_amount', 0)->update(['status' => 'void']);

        if ($property->status === PropertyStatus::Rented) {
            $this->properties->transition($property, PropertyStatus::Available, $actor, "Lease #{$lease->id} {$to->value}");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

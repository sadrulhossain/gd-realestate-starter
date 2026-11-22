<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The only place a property's status changes. Writes one history row per change. */
class PropertyStatusService
{
    /** @throws ValidationException when the transition is not allowed */
    public function transition(Property $property, PropertyStatus $to, ?User $actor = null, ?string $note = null): Property
    {
        $from = $property->status;

        if (! $from->canMoveTo($to, $property->listing_type)) {
            throw ValidationException::withMessages([
                'status' => ["A {$property->listing_type} property cannot move from {$from->value} to {$to->value}."],
            ]);
        }

        DB::transaction(function () use ($property, $from, $to, $actor, $note): void {
            $property->status = $to;

            if ($to === PropertyStatus::Available && $property->published_at === null) {
                $property->published_at = now();
            }

            $property->save();
            $property->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $actor?->id,
                'note' => $note,
            ]);
        });

        return $property;
    }
}

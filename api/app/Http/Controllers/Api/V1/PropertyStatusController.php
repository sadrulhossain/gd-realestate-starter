<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePropertyStatusRequest;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Models\Property;
use App\Services\PropertyStatusService;

class PropertyStatusController extends Controller
{
    /**
     * Change a property's status
     *
     * Allowed moves: draft to available or archived; available to draft, under_offer, sold (sale only),
     * rented (rent only) or archived; under_offer to available, sold, rented or archived; sold and rented
     * to available or archived; archived to draft. Anything else answers 422. Each change is written to the history.
     */
    public function store(ChangePropertyStatusRequest $request, Property $property, PropertyStatusService $statuses): PropertyResource
    {
        $statuses->transition(
            $property,
            PropertyStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('note'),
        );

        return new PropertyResource($property->refresh()->load(['propertyType', 'location', 'coverMedia', 'statusHistory']));
    }
}

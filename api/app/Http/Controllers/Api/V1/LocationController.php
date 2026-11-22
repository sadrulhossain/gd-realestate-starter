<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLocationRequest;
use App\Http\Requests\Api\V1\UpdateLocationRequest;
use App\Http\Resources\Api\V1\LocationResource;
use App\Models\Location;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    /**
     * List active locations (public)
     *
     * A flat list ordered by `sort_order`; build the tree from `parent_id`.
     * A location below an inactive parent is hidden too. No authentication.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        $active = Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $parents = $active->pluck('parent_id', 'id');

        $visible = $active->filter(function (Location $location) use ($parents): bool {
            for ($parent = $location->parent_id; $parent !== null; $parent = $parents[$parent]) {
                if (! $parents->has($parent)) {
                    return false;
                }
            }

            return true;
        });

        return LocationResource::collection($visible->values());
    }

    /**
     * List locations
     *
     * A flat list of every location, inactive ones included; build the tree from `parent_id`.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Location::class);

        return LocationResource::collection(Location::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    /** Show a location */
    public function show(Location $location): LocationResource
    {
        Gate::authorize('view', $location);

        return new LocationResource($location);
    }

    /** Create a location */
    public function store(StoreLocationRequest $request): LocationResource
    {
        // refresh(): load the DB defaults (sort_order, is_active) omitted from the request.
        return new LocationResource(Location::query()->create($request->validated())->refresh());
    }

    /** Update a location */
    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        $location->update($request->validated());

        return new LocationResource($location);
    }

    /**
     * Delete a location
     *
     * A location that still has children answers 409.
     */
    public function destroy(Location $location): Response
    {
        Gate::authorize('delete', $location);

        abort_if($location->children()->exists(), 409, 'Delete or move the child locations first.');

        $location->delete();

        return response()->noContent();
    }
}

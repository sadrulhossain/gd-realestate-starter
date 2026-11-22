<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAmenityRequest;
use App\Http\Requests\Api\V1\UpdateAmenityRequest;
use App\Http\Resources\Api\V1\AmenityResource;
use App\Models\Amenity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AmenityController extends Controller
{
    /**
     * List amenities (public)
     *
     * Active rows ordered by `sort_order`. No authentication.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        return AmenityResource::collection(Amenity::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get());
    }

    /** List amenities */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Amenity::class);

        return AmenityResource::collection(Amenity::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    /** Show a amenity */
    public function show(Amenity $amenity): AmenityResource
    {
        Gate::authorize('view', $amenity);

        return new AmenityResource($amenity);
    }

    /** Create a amenity */
    public function store(StoreAmenityRequest $request): AmenityResource
    {
        // refresh(): load the DB defaults (sort_order) omitted from the request.
        return new AmenityResource(Amenity::query()->create($request->validated())->refresh());
    }

    /** Update a amenity */
    public function update(UpdateAmenityRequest $request, Amenity $amenity): AmenityResource
    {
        $amenity->update($request->validated());

        return new AmenityResource($amenity);
    }

    /** Delete an amenity */
    public function destroy(Amenity $amenity): Response
    {
        Gate::authorize('delete', $amenity);

        $amenity->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePropertyTypeRequest;
use App\Http\Requests\Api\V1\UpdatePropertyTypeRequest;
use App\Http\Resources\Api\V1\PropertyTypeResource;
use App\Models\PropertyType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PropertyTypeController extends Controller
{
    /**
     * List property types (public)
     *
     * Active rows ordered by `sort_order`. No authentication.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        return PropertyTypeResource::collection(PropertyType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get());
    }

    /** List property types */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PropertyType::class);

        return PropertyTypeResource::collection(PropertyType::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    /** Show a property type */
    public function show(PropertyType $property_type): PropertyTypeResource
    {
        Gate::authorize('view', $property_type);

        return new PropertyTypeResource($property_type);
    }

    /** Create a property type */
    public function store(StorePropertyTypeRequest $request): PropertyTypeResource
    {
        // refresh(): load the DB defaults (sort_order) omitted from the request.
        return new PropertyTypeResource(PropertyType::query()->create($request->validated())->refresh());
    }

    /** Update a property type */
    public function update(UpdatePropertyTypeRequest $request, PropertyType $property_type): PropertyTypeResource
    {
        $property_type->update($request->validated());

        return new PropertyTypeResource($property_type);
    }

    /** Delete a property type */
    public function destroy(PropertyType $property_type): Response
    {
        Gate::authorize('delete', $property_type);

        $property_type->delete();

        return response()->noContent();
    }
}

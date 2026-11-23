<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublishProjectUnitRequest;
use App\Http\Requests\Api\V1\StoreProjectUnitRequest;
use App\Http\Requests\Api\V1\UpdateProjectUnitRequest;
use App\Http\Resources\Api\V1\ProjectUnitResource;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Services\ProjectUnitPublisher;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectUnitController extends Controller
{
    private const WITH = ['propertyType', 'property'];

    /** List the units of a project */
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProjectUnit::class);

        return ProjectUnitResource::collection($project->units()->with(self::WITH)->get());
    }

    /** Show a unit */
    public function show(ProjectUnit $unit): ProjectUnitResource
    {
        Gate::authorize('view', $unit);

        return new ProjectUnitResource($unit->load(self::WITH));
    }

    /** Create a unit in a project */
    public function store(StoreProjectUnitRequest $request, Project $project): ProjectUnitResource
    {
        $unit = $project->units()->create($request->validated());

        return new ProjectUnitResource($unit->refresh()->load(self::WITH));
    }

    /** Update a unit */
    public function update(UpdateProjectUnitRequest $request, ProjectUnit $unit): ProjectUnitResource
    {
        $unit->update($request->validated());

        return new ProjectUnitResource($unit->refresh()->load(self::WITH));
    }

    /**
     * Delete a unit
     *
     * A unit that is published as a property answers 409: delete or unlink the property first.
     */
    public function destroy(ProjectUnit $unit): Response
    {
        Gate::authorize('delete', $unit);

        abort_if($unit->property()->exists(), 409, 'This unit is published as a property. Delete that property first.');

        $unit->delete();

        return response()->noContent();
    }

    /**
     * Publish a unit as a property
     *
     * Creates one `draft` property from the unit (title, type, price, size, beds, baths, floor) with the project's
     * location, coordinates and developer link. Optional `listing_type` (default sale) and `rent_period`.
     * A unit can be published once (422 afterwards); finish and publish the draft with the properties endpoints.
     */
    public function publish(PublishProjectUnitRequest $request, ProjectUnit $unit, ProjectUnitPublisher $publisher): PropertyResource
    {
        $property = $publisher->publish($unit, $request->validated('listing_type', 'sale'), $request->validated('rent_period'));

        return new PropertyResource($property->load(['propertyType', 'location', 'coverMedia', 'amenities', 'media', 'statusHistory']));
    }
}

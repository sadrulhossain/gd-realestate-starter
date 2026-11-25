<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\PublicProjectResource;
use App\Models\Location;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const CARD = ['developer', 'location'];

    /**
     * List projects (public)
     *
     * Active projects, featured first then newest. Filters: `location` (id, includes sub-locations), `developer` (id),
     * `status` (upcoming, under_construction, completed). Each row carries `units_count` (units still available)
     * and `from_price` (lowest available unit price, minor units). 12 per page. No authentication.
     */
    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'location' => ['integer', 'min:1'],
            'developer' => ['integer', 'min:1'],
            'status' => [Rule::in(Project::STATUSES)],
        ]);

        $projects = Project::query()->where('is_active', true)
            ->with(self::CARD)
            ->withCount(['units' => fn ($q) => $q->where('status', 'available')])
            ->withMin(['units as from_price' => fn ($q) => $q->where('status', 'available')], 'price')
            ->when($filters['location'] ?? null, function ($q, $id) {
                $location = Location::query()->find($id);

                return $q->whereIn('location_id', $location ? [$location->id, ...$location->descendantIds()] : [0]);
            })
            ->when($filters['developer'] ?? null, fn ($q, $id) => $q->where('developer_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('featured')->orderByDesc('id')
            ->paginate(12)->withQueryString();

        return PublicProjectResource::collection($projects);
    }

    /**
     * Show a project by slug (public)
     *
     * Active projects only (others answer 404), with the developer and all units. No authentication.
     */
    public function publicShow(string $slug): PublicProjectResource
    {
        $project = Project::query()->where('is_active', true)->where('slug', $slug)
            ->with([...self::CARD, 'units.propertyType'])
            ->withCount(['units' => fn ($q) => $q->where('status', 'available')])
            ->withMin(['units as from_price' => fn ($q) => $q->where('status', 'available')], 'price')
            ->firstOrFail();

        return new PublicProjectResource($project);
    }

    /** List projects */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Project::class);

        return ProjectResource::collection(
            Project::query()->with(self::CARD)->withCount('units')->orderByDesc('id')->paginate(20),
        );
    }

    /** Show a project with its units */
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project->load([...self::CARD, 'units.propertyType', 'units.property'])->loadCount('units'));
    }

    /** Create a project */
    public function store(StoreProjectRequest $request): ProjectResource
    {
        return new ProjectResource(Project::query()->create($request->validated())->refresh()->load(self::CARD));
    }

    /** Update a project */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return new ProjectResource($project->refresh()->load(self::CARD));
    }

    /**
     * Delete a project and its units
     *
     * A project with a property linked to it (published units or properties tagged with the project) answers 409.
     */
    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        abort_if($project->properties()->exists(), 409, 'Unlink or delete the properties of this project first.');

        $project->delete();

        return response()->noContent();
    }
}

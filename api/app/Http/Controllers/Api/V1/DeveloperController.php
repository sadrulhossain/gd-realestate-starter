<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeveloperRequest;
use App\Http\Requests\Api\V1\UpdateDeveloperRequest;
use App\Http\Resources\Api\V1\DeveloperResource;
use App\Http\Resources\Api\V1\PublicDeveloperResource;
use App\Models\Developer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeveloperController extends Controller
{
    /**
     * List developers (public)
     *
     * Active developers that have at least one active project, ordered by `sort_order`. No authentication.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        $developers = Developer::query()->where('is_active', true)
            ->whereHas('projects', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('id')->get();

        return PublicDeveloperResource::collection($developers);
    }

    /** List developers */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Developer::class);

        return DeveloperResource::collection(Developer::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    /** Show a developer */
    public function show(Developer $developer): DeveloperResource
    {
        Gate::authorize('view', $developer);

        return new DeveloperResource($developer);
    }

    /** Create a developer */
    public function store(StoreDeveloperRequest $request): DeveloperResource
    {
        return new DeveloperResource(Developer::query()->create($request->validated())->refresh());
    }

    /** Update a developer */
    public function update(UpdateDeveloperRequest $request, Developer $developer): DeveloperResource
    {
        $developer->update($request->validated());

        return new DeveloperResource($developer);
    }

    /**
     * Delete a developer
     *
     * A developer that still has projects answers 409.
     */
    public function destroy(Developer $developer): Response
    {
        Gate::authorize('delete', $developer);

        abort_if($developer->projects()->exists(), 409, 'Delete or reassign the developer\'s projects first.');

        $developer->delete();

        return response()->noContent();
    }
}

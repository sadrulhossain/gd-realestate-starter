<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAgentRequest;
use App\Http\Requests\Api\V1\UpdateAgentRequest;
use App\Http\Resources\Api\V1\AgentResource;
use App\Http\Resources\Api\V1\PublicAgentResource;
use App\Models\Agent;
use App\Services\MediaService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AgentController extends Controller
{
    /**
     * List agents (public)
     *
     * Active agents by sort order, each with `listings_count` (their available or under-offer properties). No authentication.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        return PublicAgentResource::collection(
            Agent::query()->where('is_active', true)->withCount('listings')->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    /**
     * Show an agent by slug (public)
     *
     * Active agents only (others answer 404). No authentication.
     */
    public function publicShow(string $slug): PublicAgentResource
    {
        return new PublicAgentResource(
            Agent::query()->where('is_active', true)->where('slug', $slug)->withCount('listings')->firstOrFail(),
        );
    }

    /** List agents */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Agent::class);

        return AgentResource::collection(Agent::query()->orderBy('sort_order')->orderBy('name')->paginate(20));
    }

    /** Show an agent */
    public function show(Agent $agent): AgentResource
    {
        Gate::authorize('view', $agent);

        return new AgentResource($agent);
    }

    /** Create an agent */
    public function store(StoreAgentRequest $request): AgentResource
    {
        return new AgentResource(Agent::query()->create($request->validated())->refresh());
    }

    /** Update an agent */
    public function update(UpdateAgentRequest $request, Agent $agent): AgentResource
    {
        $agent->update($request->validated());

        return new AgentResource($agent->refresh());
    }

    /**
     * Delete an agent
     *
     * An agent with properties, leads or viewings answers 409: reassign or deactivate (`is_active`) instead.
     */
    public function destroy(Agent $agent, MediaService $media): Response
    {
        Gate::authorize('delete', $agent);

        abort_if(
            $agent->properties()->exists() || $agent->leads()->exists() || $agent->viewings()->exists(),
            409,
            'This agent still has properties, leads or viewings. Reassign them or deactivate the agent.',
        );

        $media->delete($agent->image);
        $agent->delete();

        return response()->noContent();
    }
}

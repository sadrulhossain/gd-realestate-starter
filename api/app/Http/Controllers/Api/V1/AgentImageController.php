<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAgentImageRequest;
use App\Http\Resources\Api\V1\AgentResource;
use App\Models\Agent;
use App\Services\MediaService;
use Illuminate\Support\Facades\Gate;

class AgentImageController extends Controller
{
    /**
     * Upload an agent photo
     *
     * Multipart field `image` (jpeg, png or webp). Replaces the current photo;
     * WebP variants are generated in the background.
     */
    public function store(StoreAgentImageRequest $request, Agent $agent, MediaService $media): AgentResource
    {
        $previous = $agent->image;

        $agent->update(['image' => $media->store($request->file('image'), 'agents')]);
        $media->delete($previous);

        return new AgentResource($agent);
    }

    /** Remove the agent photo */
    public function destroy(Agent $agent, MediaService $media): AgentResource
    {
        Gate::authorize('update', $agent);

        $media->delete($agent->image);
        $agent->update(['image' => null]);

        return new AgentResource($agent);
    }
}

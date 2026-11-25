<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ViewingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeViewingStatusRequest;
use App\Http\Requests\Api\V1\StoreViewingRequest;
use App\Http\Requests\Api\V1\UpdateViewingRequest;
use App\Http\Resources\Api\V1\ViewingResource;
use App\Models\Property;
use App\Models\Viewing;
use App\Notifications\ViewingScheduled;
use App\Services\ViewingScheduler;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class ViewingController extends Controller
{
    private const WITH = ['property:id,ref_code,title,slug', 'lead:id,name', 'agent:id,name'];

    /**
     * List viewings
     *
     * Agenda order (earliest first), 50 per page. Filters: `from` and `to` (dates, on the start time), `agent_id`,
     * `status`, `property_id`, `lead_id`. Without `viewings.view_all` only your own agent profile's viewings are listed.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Viewing::class);

        $f = $request->validate([
            'from' => ['date'],
            'to' => ['date', 'after_or_equal:from'],
            'agent_id' => ['integer', 'min:1'],
            'status' => [Rule::enum(ViewingStatus::class)],
            'property_id' => ['integer', 'min:1'],
            'lead_id' => ['integer', 'min:1'],
        ]);

        $viewings = Viewing::query()->visibleTo($request->user())->with(self::WITH)
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('starts_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('starts_at', '<', now()->parse($v)->addDay()->startOfDay()))
            ->when($f['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($f['lead_id'] ?? null, fn ($q, $v) => $q->where('lead_id', $v))
            ->orderBy('starts_at')->orderBy('id')->paginate(50)->withQueryString();

        return ViewingResource::collection($viewings);
    }

    /** Show a viewing */
    public function show(Viewing $viewing): ViewingResource
    {
        Gate::authorize('view', $viewing);

        return new ViewingResource($viewing->load(self::WITH));
    }

    /**
     * Book a viewing
     *
     * The agent is `agent_id` (needs `viewings.view_all`), otherwise the property's agent; without `viewings.view_all`
     * it is always your own agent profile. Answers 422 on `starts_at` when the agent already has a scheduled or
     * confirmed viewing that overlaps. The agent and the lead (when it has an email) are notified by queued email.
     */
    public function store(StoreViewingRequest $request, ViewingScheduler $scheduler): ViewingResource
    {
        $property = Property::query()->findOrFail($request->validated('property_id'));
        $agentId = $scheduler->resolveAgentId($request->user(), $property, $request->validated('agent_id'));

        $viewing = $scheduler->create($request->safe()->except(['agent_id']), $agentId)->load(self::WITH);
        $viewing->load('lead:id,name,email');

        $this->notify($viewing);

        return new ViewingResource($viewing->refresh()->load(self::WITH));
    }

    /** Reschedule or edit a viewing (times are re-checked for conflicts; status has its own endpoint) */
    public function update(UpdateViewingRequest $request, Viewing $viewing, ViewingScheduler $scheduler): ViewingResource
    {
        $scheduler->update($viewing, $request->validated());

        return new ViewingResource($viewing->refresh()->load(self::WITH));
    }

    /**
     * Change a viewing's status
     *
     * Allowed moves: scheduled to confirmed, cancelled or no_show; confirmed to completed, cancelled or no_show.
     * Completed, cancelled and no_show are final and free the agent's calendar.
     */
    public function status(ChangeViewingStatusRequest $request, Viewing $viewing, ViewingScheduler $scheduler): ViewingResource
    {
        $scheduler->transition($viewing, ViewingStatus::from($request->validated('status')));

        return new ViewingResource($viewing->refresh()->load(self::WITH));
    }

    /** Delete a viewing */
    public function destroy(Viewing $viewing): Response
    {
        Gate::authorize('delete', $viewing);

        $viewing->delete();

        return response()->noContent();
    }

    /** Email the agent and the lead. */
    private function notify(Viewing $viewing): void
    {
        $viewing->load('agent.user:id,email');
        $agentEmail = $viewing->agent->email ?: $viewing->agent->user?->email;

        foreach (array_unique(array_filter([$agentEmail, $viewing->lead?->email])) as $to) {
            Notification::route('mail', $to)->notify(new ViewingScheduled($viewing));
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignLeadRequest;
use App\Http\Requests\Api\V1\ChangeLeadStatusRequest;
use App\Http\Requests\Api\V1\StoreLeadNoteRequest;
use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Http\Requests\Api\V1\StorePublicLeadRequest;
use App\Http\Requests\Api\V1\UpdateLeadRequest;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Setting;
use App\Notifications\NewLead;
use App\Services\LeadStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    private const WITH = ['agent:id,name', 'property:id,ref_code,title,slug', 'project:id,name,slug'];

    /**
     * Send an enquiry (public)
     *
     * Needs a captcha from `public/captcha`. The `website` field is a honeypot and must be left out or empty.
     * Throttled per IP. The lead goes to the property's agent when it has one, and that agent (or the site owner)
     * is notified by queued email.
     */
    public function publicStore(StorePublicLeadRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['name', 'email', 'phone', 'message', 'property_id', 'project_id']);

        $lead = new Lead($data);
        $lead->source = 'web';
        $lead->agent_id = isset($data['property_id']) ? Property::query()->whereKey($data['property_id'])->value('agent_id') : null;
        $lead->save();

        $this->notify($lead);

        return response()->json(['data' => ['received' => true]], 201);
    }

    /**
     * List leads
     *
     * Newest first, 20 per page. Filters: `status`, `agent_id`, `source`, `property_id`, `project_id`,
     * `q` (name, email or phone). Without `leads.view_all` only the leads assigned to your own agent profile are listed.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lead::class);

        $f = $request->validate([
            'status' => [Rule::enum(LeadStatus::class)],
            'agent_id' => ['integer', 'min:1'],
            'source' => [Rule::in(Lead::SOURCES)],
            'property_id' => ['integer', 'min:1'],
            'project_id' => ['integer', 'min:1'],
            'q' => ['string', 'max:100'],
        ]);

        $leads = Lead::query()->visibleTo($request->user())->with(self::WITH)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($f['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($f['project_id'] ?? null, fn ($q, $v) => $q->where('project_id', $v))
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';

                return $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)->orWhere('phone', 'ilike', $like));
            })
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return LeadResource::collection($leads);
    }

    /** Show a lead with its timeline */
    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        return new LeadResource($lead->load([...self::WITH, 'notes.user:id,name']));
    }

    /**
     * Create a lead
     *
     * For enquiries that came in by phone, walk-in or referral. The status starts at `new`. Only users who can assign
     * leads may set `agent_id`; for everyone else the lead goes to their own agent profile.
     */
    public function store(StoreLeadRequest $request): LeadResource
    {
        $lead = new Lead($request->safe()->except(['agent_id']));
        $lead->source = $request->validated('source', 'phone');
        $lead->agent_id = $request->user()->can('leads.assign')
            ? $request->validated('agent_id')
            : $request->user()->agent?->id;
        $lead->save();

        $this->notify($lead);

        return new LeadResource($lead->refresh()->load(self::WITH));
    }

    /** Update a lead (contact details and links; status and agent have their own endpoints) */
    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        return new LeadResource($lead->refresh()->load(self::WITH));
    }

    /** Delete a lead and its timeline */
    public function destroy(Lead $lead): Response
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return response()->noContent();
    }

    /**
     * Move a lead through the pipeline
     *
     * Allowed moves: new to contacted or lost; contacted to qualified or lost; qualified to viewing, negotiation or lost;
     * viewing to qualified, negotiation or lost; negotiation to viewing, won or lost; lost to new (reopen); won is final.
     * `lost_reason` is required when moving to lost. Each change is written to the timeline.
     */
    public function status(ChangeLeadStatusRequest $request, Lead $lead, LeadStatusService $statuses): LeadResource
    {
        $statuses->transition($lead, LeadStatus::from($request->validated('status')), $request->user(), $request->validated('lost_reason'));

        return new LeadResource($lead->refresh()->load([...self::WITH, 'notes.user:id,name']));
    }

    /**
     * Assign a lead to an agent
     *
     * Send `agent_id` (an active agent) or null to unassign. The new agent is notified by queued email.
     */
    public function assign(AssignLeadRequest $request, Lead $lead): LeadResource
    {
        $agentId = $request->validated('agent_id');
        $name = $agentId ? Agent::query()->whereKey($agentId)->value('name') : null;

        $lead->agent_id = $agentId;
        $lead->save();
        $lead->notes()->create([
            'user_id' => $request->user()->id,
            'kind' => 'assign',
            'body' => $name ? "Assigned to {$name}" : 'Unassigned',
        ]);

        if ($agentId !== null) {
            $this->notify($lead);
        }

        return new LeadResource($lead->refresh()->load([...self::WITH, 'notes.user:id,name']));
    }

    /** Add a note to a lead's timeline */
    public function note(StoreLeadNoteRequest $request, Lead $lead): LeadResource
    {
        $lead->notes()->create(['user_id' => $request->user()->id, 'kind' => 'note', 'body' => $request->validated('body')]);

        return new LeadResource($lead->load([...self::WITH, 'notes.user:id,name']));
    }

    /** Email the assigned agent, or the site owner when nobody is assigned. */
    private function notify(Lead $lead): void
    {
        $agent = $lead->agent_id ? Agent::query()->with('user:id,email')->find($lead->agent_id) : null;
        $to = $agent ? ($agent->email ?: $agent->user?->email) : (Setting::current()->email ?: config('contact.notify_to'));

        if (filled($to)) {
            Notification::route('mail', $to)->notify(new NewLead($lead));
        }
    }
}

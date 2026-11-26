<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeLeaseStatusRequest;
use App\Http\Requests\Api\V1\StoreLeaseRequest;
use App\Http\Requests\Api\V1\UpdateLeaseRequest;
use App\Http\Resources\Api\V1\LeaseResource;
use App\Models\Lease;
use App\Services\LeaseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaseController extends Controller
{
    private const WITH = ['property:id,ref_code,title', 'tenant:id,name'];

    /** List leases (newest first). Filters: `status`, `property_id`, `tenant_id`, `q` (tenant name or property reference). */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lease::class);

        $f = $request->validate([
            'status' => [Rule::enum(LeaseStatus::class)],
            'property_id' => ['integer', 'min:1'],
            'tenant_id' => ['integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $leases = Lease::query()->with(self::WITH)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->when($f['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($f['q'] ?? null, function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->whereRelation('tenant', 'name', 'ilike', $like)->orWhereRelation('property', 'ref_code', 'ilike', $like));
            })
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return LeaseResource::collection($leases);
    }

    /** Show a lease with its rent schedule */
    public function show(Lease $lease): LeaseResource
    {
        Gate::authorize('view', $lease);

        return new LeaseResource($lease->load([...self::WITH, 'charges']));
    }

    /** Create a draft lease on a rent listing */
    public function store(StoreLeaseRequest $request): LeaseResource
    {
        $lease = Lease::query()->create($request->validated());

        return new LeaseResource($lease->refresh()->load(self::WITH));
    }

    /** Update a lease (only a draft can change its terms; notes can always change) */
    public function update(UpdateLeaseRequest $request, Lease $lease, LeaseService $leases): LeaseResource
    {
        $leases->update($lease, $request->validated());

        return new LeaseResource($lease->refresh()->load(self::WITH));
    }

    /**
     * Change a lease's status
     *
     * draft to active (writes the rent schedule, marks the property rented) or cancelled; active to ended or
     * terminated on `ended_on` (default today, not in the future), which voids later unpaid periods and makes the
     * property available again.
     */
    public function status(ChangeLeaseStatusRequest $request, Lease $lease, LeaseService $leases): LeaseResource
    {
        $on = $request->validated('ended_on');

        $lease = $leases->transition($lease, LeaseStatus::from($request->validated('status')), $request->user(), $on ? CarbonImmutable::parse($on) : null);

        return new LeaseResource($lease->load([...self::WITH, 'charges']));
    }

    /** Delete a lease that never started (draft or cancelled); others answer 409 */
    public function destroy(Lease $lease): Response
    {
        Gate::authorize('delete', $lease);

        abort_unless(in_array($lease->status, [LeaseStatus::Draft, LeaseStatus::Cancelled], true), 409, 'A lease that has started is part of the records and cannot be deleted.');

        $lease->delete();

        return response()->noContent();
    }
}

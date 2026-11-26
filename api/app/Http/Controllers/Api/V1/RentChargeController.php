<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRentPaymentRequest;
use App\Http\Requests\Api\V1\UpdateRentChargeRequest;
use App\Http\Resources\Api\V1\RentChargeResource;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Services\RentLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RentChargeController extends Controller
{
    private const WITH = ['lease:id,property_id,tenant_id', 'lease.property:id,ref_code,title', 'lease.tenant:id,name'];

    /**
     * List rent charges
     *
     * By due date, earliest first, 50 per page. Filters: `status` (pending, partial, paid, void or overdue),
     * `lease_id`, `from` and `to` (due dates, inclusive).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RentCharge::class);

        $f = $request->validate([
            'status' => [Rule::in([...RentCharge::STATUSES, 'overdue'])],
            'lease_id' => ['integer', 'min:1'],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $charges = RentCharge::query()->with(self::WITH)
            ->when($f['status'] ?? null, fn ($q, $v) => $v === 'overdue' ? $q->overdue() : $q->where('status', $v))
            ->when($f['lease_id'] ?? null, fn ($q, $v) => $q->where('lease_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('due_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('due_date', '<=', $v))
            ->orderBy('due_date')->orderBy('id')->paginate(50)->withQueryString();

        return RentChargeResource::collection($charges);
    }

    /** Show a rent charge with its payments */
    public function show(RentCharge $rentCharge): RentChargeResource
    {
        Gate::authorize('view', $rentCharge);

        return $this->full($rentCharge);
    }

    /** Change a charge's due date or note */
    public function update(UpdateRentChargeRequest $request, RentCharge $rentCharge): RentChargeResource
    {
        $rentCharge->update($request->validated());

        return $this->full($rentCharge);
    }

    /** Record a payment (at most what is still owed) */
    public function pay(StoreRentPaymentRequest $request, RentCharge $rentCharge, RentLedger $ledger): RentChargeResource
    {
        $ledger->pay($rentCharge, $request->validated(), $request->user());

        return $this->full($rentCharge->refresh());
    }

    /** Remove a payment entered by mistake */
    public function reverse(RentCharge $rentCharge, RentPayment $payment, RentLedger $ledger): RentChargeResource
    {
        Gate::authorize('update', $rentCharge);

        $ledger->reverse($payment);

        return $this->full($rentCharge->refresh());
    }

    private function full(RentCharge $charge): RentChargeResource
    {
        return new RentChargeResource($charge->load([...self::WITH, 'payments']));
    }
}

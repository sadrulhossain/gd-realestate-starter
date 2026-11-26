<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangeMaintenanceStatusRequest;
use App\Http\Requests\Api\V1\StoreMaintenanceRequest;
use App\Http\Requests\Api\V1\UpdateMaintenanceRequest;
use App\Http\Resources\Api\V1\MaintenanceResource;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaintenanceController extends Controller
{
    private const WITH = ['property:id,ref_code,title'];

    /** List maintenance requests (newest first). Filters: `status`, `priority`, `property_id`. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        $f = $request->validate([
            'status' => [Rule::enum(MaintenanceStatus::class)],
            'priority' => [Rule::in(MaintenanceRequest::PRIORITIES)],
            'property_id' => ['integer', 'min:1'],
        ]);

        $items = MaintenanceRequest::query()->with(self::WITH)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($f['property_id'] ?? null, fn ($q, $v) => $q->where('property_id', $v))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return MaintenanceResource::collection($items);
    }

    /** Show a maintenance request */
    public function show(MaintenanceRequest $maintenance): MaintenanceResource
    {
        Gate::authorize('view', $maintenance);

        return new MaintenanceResource($maintenance->load(self::WITH));
    }

    /** Log a maintenance request */
    public function store(StoreMaintenanceRequest $request): MaintenanceResource
    {
        $item = MaintenanceRequest::query()->create($request->validated());

        return new MaintenanceResource($item->refresh()->load(self::WITH));
    }

    /** Update a maintenance request */
    public function update(UpdateMaintenanceRequest $request, MaintenanceRequest $maintenance): MaintenanceResource
    {
        $maintenance->update($request->validated());

        return new MaintenanceResource($maintenance->refresh()->load(self::WITH));
    }

    /** Change a request's status (resolved and cancelled requests can be reopened) */
    public function status(ChangeMaintenanceStatusRequest $request, MaintenanceRequest $maintenance): MaintenanceResource
    {
        $to = MaintenanceStatus::from($request->validated('status'));

        if (! in_array($to, $maintenance->status->next(), true)) {
            throw ValidationException::withMessages(['status' => ["A request cannot move from {$maintenance->status->value} to {$to->value}."]]);
        }

        $maintenance->forceFill(['status' => $to, 'resolved_at' => $to === MaintenanceStatus::Resolved ? now() : null])->save();

        return new MaintenanceResource($maintenance->load(self::WITH));
    }

    /** Delete a maintenance request */
    public function destroy(MaintenanceRequest $maintenance): Response
    {
        Gate::authorize('delete', $maintenance);

        $maintenance->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTenantRequest;
use App\Http\Requests\Api\V1\UpdateTenantRequest;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    /** List tenants (by name; `q` searches name, email and phone) */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenant::class);

        $f = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $tenants = Tenant::query()
            ->when($f['q'] ?? null, function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)->orWhere('phone', 'ilike', $like));
            })
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return TenantResource::collection($tenants);
    }

    /** Show a tenant */
    public function show(Tenant $tenant): TenantResource
    {
        Gate::authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    /** Create a tenant */
    public function store(StoreTenantRequest $request): TenantResource
    {
        return new TenantResource(Tenant::query()->create($request->validated()));
    }

    /** Update a tenant */
    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $tenant->update($request->validated());

        return new TenantResource($tenant);
    }

    /** Delete a tenant (409 while they have a lease) */
    public function destroy(Tenant $tenant): Response
    {
        Gate::authorize('delete', $tenant);

        abort_if($tenant->leases()->exists(), 409, 'This tenant has leases and cannot be deleted.');

        $tenant->delete();

        return response()->noContent();
    }
}

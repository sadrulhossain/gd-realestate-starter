<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicPropertyIndexRequest;
use App\Http\Requests\Api\V1\StorePropertyRequest;
use App\Http\Requests\Api\V1\UpdatePropertyRequest;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Http\Resources\Api\V1\PublicPropertyDetailResource;
use App\Http\Resources\Api\V1\PublicPropertyResource;
use App\Models\Property;
use App\Models\Setting;
use App\Services\MediaService;
use App\Services\PropertySearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PropertyController extends Controller
{
    private const CARD = ['propertyType', 'location', 'coverMedia'];

    /**
     * Search properties (public)
     *
     * Only `available` and `under_offer` properties. Filters: `listing_type`, `property_type` (key or id),
     * `location` (id, includes its sub-locations), `price_min`/`price_max` (minor units), `beds_min`, `baths_min`,
     * `area_min`/`area_max`, `amenities` (ids, all required), `furnished`, `featured`, `status`.
     * `q` is a keyword search (full-text, typo tolerant); `lat`, `lng` and `radius_km` limit by distance.
     * `sort`: relevance (default with `q`), newest (default), price_asc, price_desc, area_asc, area_desc.
     * 12 per page (`per_page` up to 48). No authentication.
     */
    public function publicIndex(PublicPropertyIndexRequest $request, PropertySearch $search): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $properties = $search->apply(Property::query()->public()->with(self::CARD), $filters)
            ->paginate($filters['per_page'] ?? 12)->withQueryString();

        return PublicPropertyResource::collection($properties);
    }

    /**
     * Show a property by slug (public)
     *
     * Available and under-offer properties only (others answer 404). `related` holds up to four similar properties.
     */
    public function publicShow(string $slug): PublicPropertyDetailResource
    {
        $property = Property::query()->public()->where('slug', $slug)
            ->with([...self::CARD, 'amenities', 'media'])->firstOrFail();

        $related = Property::query()->public()->with(self::CARD)
            ->whereKeyNot($property->id)
            ->where('listing_type', $property->listing_type)
            ->where(fn ($q) => $q->where('property_type_id', $property->property_type_id)->orWhere('location_id', $property->location_id))
            ->orderByRaw('(location_id = ?) desc', [$property->location_id])
            ->orderByRaw('published_at desc nulls last')->orderByDesc('id')
            ->limit(4)->get();

        return (new PublicPropertyDetailResource($property))
            ->additional(['related' => PublicPropertyResource::collection($related)]);
    }

    /**
     * List properties
     *
     * Every status, newest first, 20 per page. Filter with `status`, `listing_type` and `q` (title or reference code).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Property::class);

        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'max:20'],
            'listing_type' => ['sometimes', 'string', 'max:10'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $properties = Property::query()->with(self::CARD)
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['listing_type'] ?? null, fn ($q, $v) => $q->where('listing_type', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'ilike', '%'.addcslashes($v, '%_\\').'%')
                ->orWhere('ref_code', 'ilike', '%'.addcslashes($v, '%_\\').'%')))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return PropertyResource::collection($properties);
    }

    /** Show a property, with its media and status history */
    public function show(Property $property): PropertyResource
    {
        Gate::authorize('view', $property);

        return new PropertyResource($property->load([...self::CARD, 'amenities', 'media', 'statusHistory']));
    }

    /**
     * Create a property
     *
     * Always starts as `draft`; change the status with `POST properties/{id}/status`.
     * `rent_period` is required for rent listings. `price` is in minor units. `amenity_ids` is the full amenity list.
     */
    public function store(StorePropertyRequest $request): PropertyResource
    {
        $data = $request->validated();
        $amenityIds = Arr::pull($data, 'amenity_ids', []);
        $data['area_unit'] ??= Setting::current()->area_unit;

        $property = DB::transaction(function () use ($data, $amenityIds): Property {
            $property = Property::query()->create($data);
            $property->amenities()->sync($amenityIds);

            return $property;
        });

        return new PropertyResource($property->refresh()->load([...self::CARD, 'amenities', 'media', 'statusHistory']));
    }

    /** Update a property (not its status) */
    public function update(UpdatePropertyRequest $request, Property $property): PropertyResource
    {
        $data = $request->validated();
        $amenityIds = Arr::pull($data, 'amenity_ids');

        DB::transaction(function () use ($property, $data, $amenityIds): void {
            $property->update($data);

            if ($amenityIds !== null) {
                $property->amenities()->sync($amenityIds);
            }
        });

        return new PropertyResource($property->refresh()->load([...self::CARD, 'amenities', 'media', 'statusHistory']));
    }

    /** Delete a property and its media files */
    public function destroy(Property $property, MediaService $media): Response
    {
        Gate::authorize('delete', $property);

        $files = $property->media()->get()->filter->isFile();

        DB::transaction(fn () => $property->delete());

        $files->each(fn ($item) => $media->delete($item->path));

        return response()->noContent();
    }
}

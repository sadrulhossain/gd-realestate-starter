<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Public property filters, keyword search and sorting. Input is already validated
 * (PublicPropertyIndexRequest); every filter column is indexed.
 */
class PropertySearch
{
    /**
     * @param  Builder<Property>  $query
     * @param  array<string, mixed>  $f
     * @return Builder<Property>
     */
    public function apply(Builder $query, array $f): Builder
    {
        $query
            ->when($f['listing_type'] ?? null, fn ($q, $v) => $q->where('listing_type', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['property_type'] ?? null, function ($q, $v) {
                $id = ctype_digit((string) $v) ? (int) $v : PropertyType::query()->where('key', $v)->value('id');

                return $q->where('property_type_id', $id ?? 0);
            })
            ->when($f['location'] ?? null, fn ($q, $id) => $q->whereIn('location_id', $this->locationIds((int) $id)))
            ->when(isset($f['price_min']), fn ($q) => $q->where('price', '>=', $f['price_min']))
            ->when(isset($f['price_max']), fn ($q) => $q->where('price', '<=', $f['price_max']))
            ->when(isset($f['beds_min']), fn ($q) => $q->where('beds', '>=', $f['beds_min']))
            ->when(isset($f['baths_min']), fn ($q) => $q->where('baths', '>=', $f['baths_min']))
            ->when(isset($f['area_min']), fn ($q) => $q->where('area', '>=', $f['area_min']))
            ->when(isset($f['area_max']), fn ($q) => $q->where('area', '<=', $f['area_max']))
            ->when($f['slugs'] ?? null, fn ($q, $slugs) => $q->whereIn('slug', $slugs))
            ->when($f['furnished'] ?? null, fn ($q, $v) => $q->where('furnished', $v))
            ->when(isset($f['featured']), fn ($q) => $q->where('featured', (bool) $f['featured']))
            ->when($f['amenities'] ?? null, fn ($q, $ids) => $this->withAllAmenities($q, $ids));

        if (isset($f['lat'], $f['lng'], $f['radius_km'])) {
            $this->withinRadius($query, (float) $f['lat'], (float) $f['lng'], (float) $f['radius_km']);
        }

        $term = trim((string) ($f['q'] ?? ''));

        if ($term !== '') {
            $this->matching($query, $term);
        }

        return $this->sorted($query, $f['sort'] ?? null, $term);
    }

    /**
     * Full-text match (ranked) OR a typo-tolerant trigram match on the title.
     *
     * @param  Builder<Property>  $query
     */
    private function matching(Builder $query, string $term): void
    {
        // A looser word-similarity cut-off than the default 0.6, so one-letter typos still match.
        DB::select("select set_config('pg_trgm.word_similarity_threshold', '0.4', false)");

        $query->where(fn ($q) => $q
            ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$term])
            ->orWhereRaw('?::text <% title', [$term]));
    }

    /**
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    private function sorted(Builder $query, ?string $sort, string $term): Builder
    {
        $sort ??= $term !== '' ? 'relevance' : 'newest';

        return match (true) {
            $sort === 'relevance' && $term !== '' => $query
                ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('simple', ?)) desc", [$term])
                ->orderByRaw('word_similarity(?::text, title) desc', [$term])
                ->orderByDesc('id'),
            $sort === 'price_asc' => $query->orderBy('price')->orderBy('id'),
            $sort === 'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            $sort === 'area_asc' => $query->orderByRaw('area asc nulls last')->orderBy('id'),
            $sort === 'area_desc' => $query->orderByRaw('area desc nulls last')->orderByDesc('id'),
            default => $query->orderByRaw('published_at desc nulls last')->orderByDesc('id'),
        };
    }

    /**
     * A location and everything below it.
     *
     * @return list<int>
     */
    private function locationIds(int $id): array
    {
        $location = Location::query()->find($id);

        return $location ? [$location->id, ...$location->descendantIds()] : [0];
    }

    /**
     * Properties that have every one of the amenities.
     *
     * @param  Builder<Property>  $query
     * @param  list<int>  $ids
     */
    private function withAllAmenities(Builder $query, array $ids): void
    {
        $ids = array_values(array_unique($ids));

        $query->whereIn('id', fn ($sub) => $sub->select('property_id')->from('property_amenities')
            ->whereIn('amenity_id', $ids)->groupBy('property_id')
            ->havingRaw('count(distinct amenity_id) = ?', [count($ids)]));
    }

    /**
     * cube + earthdistance: the box test uses the GiST index, the distance test trims the corners.
     *
     * @param  Builder<Property>  $query
     */
    private function withinRadius(Builder $query, float $lat, float $lng, float $km): void
    {
        $metres = $km * 1000;

        $query
            ->whereRaw('earth_box(ll_to_earth(?::float8, ?::float8), ?::float8) @> ll_to_earth(lat, lng)', [$lat, $lng, $metres])
            ->whereRaw('earth_distance(ll_to_earth(?::float8, ?::float8), ll_to_earth(lat, lng)) <= ?::float8', [$lat, $lng, $metres]);
    }
}

<?php

use App\Enums\PropertyStatus;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Project;
use App\Models\Property;
use App\Models\PropertyType;

function titles($response): array
{
    return collect($response->json('data'))->pluck('title')->all();
}

function makeLocation(string $slug, ?Location $parent = null): Location
{
    return Location::create(['name' => ucfirst($slug), 'slug' => $slug, 'parent_id' => $parent?->id]);
}

it('shows only available and under-offer properties, anonymously', function () {
    Property::factory()->available()->create(['title' => 'Open']);
    Property::factory()->status(PropertyStatus::UnderOffer)->create(['title' => 'Offer']);
    Property::factory()->create(['title' => 'Draft']);
    Property::factory()->status(PropertyStatus::Sold)->create(['title' => 'Sold']);
    Property::factory()->status(PropertyStatus::Rented)->create(['title' => 'Rented']);
    Property::factory()->status(PropertyStatus::Archived)->create(['title' => 'Archived']);

    $response = $this->getJson('/api/v1/public/properties')->assertOk();

    expect(titles($response))->toEqualCanonicalizing(['Open', 'Offer']);
});

it('hides draft and sold properties from the detail page too', function () {
    $draft = Property::factory()->create();
    $sold = Property::factory()->status(PropertyStatus::Sold)->create();
    $open = Property::factory()->available()->create(['title' => 'Visible flat']);

    $this->getJson("/api/v1/public/properties/{$draft->slug}")->assertNotFound();
    $this->getJson("/api/v1/public/properties/{$sold->slug}")->assertNotFound();
    $this->getJson("/api/v1/public/properties/{$open->slug}")->assertOk()->assertJsonPath('data.title', 'Visible flat');
});

it('never exposes owner, agent or workflow fields publicly', function () {
    $open = Property::factory()->available()->create(['owner_id' => 7, 'agent_id' => 8, 'project_id' => Project::factory()->create()->id]);

    $card = $this->getJson('/api/v1/public/properties')->assertOk()->json('data.0');
    $detail = $this->getJson("/api/v1/public/properties/{$open->slug}")->assertOk()->json('data');

    foreach ([$card, $detail] as $payload) {
        expect($payload)->not->toHaveKeys(['owner_id', 'agent_id', 'project_id', 'unit_id', 'status_history', 'created_at']);
    }
});

it('filters by listing type, property type, price, beds, baths, area, furnished and featured', function () {
    $villa = PropertyType::create(['key' => 'villa', 'name' => 'Villa', 'group' => 'residential']);
    Property::factory()->available()->create(['title' => 'Cheap flat', 'price' => 1_000, 'beds' => 1, 'baths' => 1, 'area' => 500]);
    Property::factory()->available()->create(['title' => 'Big villa', 'price' => 9_000, 'beds' => 5, 'baths' => 4, 'area' => 4000, 'property_type_id' => $villa->id, 'furnished' => 'furnished', 'featured' => true]);
    Property::factory()->available()->rent('month')->create(['title' => 'Rental', 'price' => 50, 'beds' => 2, 'baths' => 1, 'area' => null]);

    $q = fn (string $query) => titles($this->getJson('/api/v1/public/properties?'.$query)->assertOk());

    expect($q('listing_type=rent'))->toBe(['Rental'])
        ->and($q('listing_type=sale&property_type=villa'))->toBe(['Big villa'])
        ->and($q('property_type='.$villa->id))->toBe(['Big villa'])
        ->and($q('price_min=500&price_max=2000'))->toBe(['Cheap flat'])
        ->and($q('beds_min=4'))->toBe(['Big villa'])
        ->and($q('baths_min=2'))->toBe(['Big villa'])
        ->and($q('area_min=1000'))->toBe(['Big villa'])
        ->and($q('area_max=1000'))->toBe(['Cheap flat'])
        ->and($q('furnished=furnished'))->toBe(['Big villa'])
        ->and($q('featured=1'))->toBe(['Big villa'])
        ->and($q('featured=0&listing_type=sale'))->toBe(['Cheap flat']);
});

it('includes sub-locations when filtering by location', function () {
    $country = makeLocation('bd');
    $city = makeLocation('dhaka', $country);
    $area = makeLocation('gulshan', $city);
    $other = makeLocation('sylhet', $country);

    Property::factory()->available()->create(['title' => 'In area', 'location_id' => $area->id]);
    Property::factory()->available()->create(['title' => 'In city', 'location_id' => $city->id]);
    Property::factory()->available()->create(['title' => 'Elsewhere', 'location_id' => $other->id]);

    $q = fn (int $id) => titles($this->getJson("/api/v1/public/properties?location={$id}&sort=price_asc")->assertOk());

    expect($q($country->id))->toEqualCanonicalizing(['In area', 'In city', 'Elsewhere'])
        ->and($q($city->id))->toEqualCanonicalizing(['In area', 'In city'])
        ->and($q($area->id))->toBe(['In area']);
});

it('requires every selected amenity', function () {
    $lift = Amenity::create(['name' => 'Lift']);
    $pool = Amenity::create(['name' => 'Pool']);
    $both = Property::factory()->available()->create(['title' => 'Both']);
    $both->amenities()->sync([$lift->id, $pool->id]);
    Property::factory()->available()->create(['title' => 'Lift only'])->amenities()->sync([$lift->id]);
    Property::factory()->available()->create(['title' => 'None']);

    $q = fn (string $query) => titles($this->getJson('/api/v1/public/properties?'.$query)->assertOk());

    expect($q("amenities={$lift->id}"))->toEqualCanonicalizing(['Both', 'Lift only'])
        ->and($q("amenities={$lift->id},{$pool->id}"))->toBe(['Both'])
        ->and($q("amenities[]={$lift->id}&amenities[]={$pool->id}"))->toBe(['Both']);
});

it('sorts by newest, price and area', function () {
    Property::factory()->available()->create(['title' => 'A', 'price' => 300, 'area' => 100]);
    Property::factory()->available()->create(['title' => 'B', 'price' => 100, 'area' => 300]);
    Property::factory()->available()->create(['title' => 'C', 'price' => 200, 'area' => 200]);

    $q = fn (string $sort) => titles($this->getJson("/api/v1/public/properties?sort={$sort}")->assertOk());

    expect($q('price_asc'))->toBe(['B', 'C', 'A'])
        ->and($q('price_desc'))->toBe(['A', 'C', 'B'])
        ->and($q('area_asc'))->toBe(['A', 'C', 'B'])
        ->and($q('area_desc'))->toBe(['B', 'C', 'A'])
        ->and($q('newest'))->toBe(['C', 'B', 'A']);
});

it('paginates', function () {
    Property::factory()->available()->count(5)->create();

    $this->getJson('/api/v1/public/properties?per_page=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3);
});

it('finds properties by full-text search and ranks better matches first', function () {
    Property::factory()->available()->create(['title' => 'Lakeview apartment', 'description' => 'Quiet street near the park']);
    Property::factory()->available()->create(['title' => 'City office', 'description' => 'A lakeview terrace is shared with the tenants']);
    Property::factory()->available()->create(['title' => 'Warehouse', 'description' => 'Large loading bay']);
    Property::factory()->available()->create(['title' => 'Plot', 'description' => 'Corner plot', 'address_line' => '12 Lake Road']);

    $titles = titles($this->getJson('/api/v1/public/properties?q=lakeview')->assertOk());

    expect($titles)->toHaveCount(2)->and($titles[0])->toBe('Lakeview apartment');
    expect(titles($this->getJson('/api/v1/public/properties?q=loading+bay')->assertOk()))->toBe(['Warehouse']);
    expect(titles($this->getJson('/api/v1/public/properties?q=Road')->assertOk()))->toBe(['Plot']);
    expect(titles($this->getJson('/api/v1/public/properties?q=zzzzzz')->assertOk()))->toBe([]);
});

it('tolerates typos in the title through pg_trgm', function () {
    Property::factory()->available()->create(['title' => 'Spacious apartment in Gulshan']);
    Property::factory()->available()->create(['title' => 'Industrial warehouse']);

    expect(titles($this->getJson('/api/v1/public/properties?q=aprtment')->assertOk()))->toBe(['Spacious apartment in Gulshan']);
});

it('keeps hidden properties out of search results', function () {
    Property::factory()->create(['title' => 'Secret draft mansion']);

    expect(titles($this->getJson('/api/v1/public/properties?q=mansion')->assertOk()))->toBe([]);
});

it('limits results to a radius around a point (cube + earthdistance)', function () {
    // Gulshan, Banani (about 2 km apart) and Chattogram (about 215 km away).
    Property::factory()->available()->create(['title' => 'Gulshan', 'lat' => 23.7925, 'lng' => 90.4078]);
    Property::factory()->available()->create(['title' => 'Banani', 'lat' => 23.7937, 'lng' => 90.4066]);
    Property::factory()->available()->create(['title' => 'Chattogram', 'lat' => 22.3569, 'lng' => 91.7832]);
    Property::factory()->available()->create(['title' => 'No coordinates']);

    $q = fn (string $query) => titles($this->getJson('/api/v1/public/properties?lat=23.7925&lng=90.4078&'.$query)->assertOk());

    expect($q('radius_km=5'))->toEqualCanonicalizing(['Gulshan', 'Banani'])
        ->and($q('radius_km=300'))->toEqualCanonicalizing(['Gulshan', 'Banani', 'Chattogram'])
        ->and($q('radius_km=0.01&sort=newest'))->toBe(['Gulshan']);
});

it('validates the query string', function () {
    $bad = $this->getJson('/api/v1/public/properties?listing_type=swap&price_min=5&price_max=1&lat=95&sort=random&per_page=500&status=sold&furnished=lots')
        ->assertStatus(422);

    expect(array_keys($bad->json('error.details')))
        ->toEqualCanonicalizing(['listing_type', 'price_max', 'lat', 'lng', 'radius_km', 'sort', 'per_page', 'status', 'furnished']);

    $this->getJson('/api/v1/public/properties?lat=23.7')->assertStatus(422);
});

it('returns related properties on the detail page, never the property itself or hidden ones', function () {
    $city = makeLocation('dhaka');
    $main = Property::factory()->available()->create(['title' => 'Main', 'location_id' => $city->id]);
    Property::factory()->available()->create(['title' => 'Neighbour', 'location_id' => $city->id]);
    Property::factory()->available()->rent()->create(['title' => 'Rental neighbour', 'location_id' => $city->id]);
    Property::factory()->create(['title' => 'Draft neighbour', 'location_id' => $city->id]);

    $related = collect($this->getJson("/api/v1/public/properties/{$main->slug}")->assertOk()->json('related'))->pluck('title');

    expect($related->all())->toBe(['Neighbour']);
});

it('detail includes description, amenities and media', function () {
    $lift = Amenity::create(['name' => 'Lift']);
    $open = Property::factory()->available()->create(['description' => 'Bright rooms']);
    $open->amenities()->sync([$lift->id]);
    $open->media()->create(['kind' => 'image', 'path' => 'properties/x/original.jpg', 'is_cover' => true]);

    $this->getJson("/api/v1/public/properties/{$open->slug}")->assertOk()
        ->assertJsonPath('data.description', 'Bright rooms')
        ->assertJsonPath('data.amenities.0.name', 'Lift')
        ->assertJsonPath('data.media.0.kind', 'image')
        ->assertJsonPath('data.cover.kind', 'image');
});

it('falls back to the default text when the second language is empty', function () {
    $open = Property::factory()->available()->create(['title' => 'Default title', 'title_2nd' => null]);

    $this->getJson("/api/v1/public/properties/{$open->slug}?lang=".config('locales.second'))
        ->assertOk()->assertJsonPath('data.title', 'Default title');
});

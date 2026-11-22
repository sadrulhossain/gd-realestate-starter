<?php

use App\Enums\PropertyStatus;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Property;
use App\Models\PropertyType;
use Database\Seeders\PropertiesModuleSeeder;

beforeEach(fn () => $this->seed(PropertiesModuleSeeder::class));

function validProperty(array $over = []): array
{
    return [
        'title' => 'Sunny flat in Gulshan',
        'listing_type' => 'sale',
        'property_type_id' => PropertyType::query()->firstOrCreate(['key' => 'apartment'], ['name' => 'Apartment', 'group' => 'residential'])->id,
        'location_id' => Location::query()->firstOrCreate(['slug' => 'dhaka', 'parent_id' => null], ['name' => 'Dhaka'])->id,
        'price' => 12_500_000,
        ...$over,
    ];
}

it('requires authentication and properties.view', function () {
    $this->getJson('/api/v1/properties')->assertUnauthorized();

    actingAsRole('nobody');
    $this->getJson('/api/v1/properties')->assertForbidden();
});

it('forbids writes without the matching permission', function () {
    $property = Property::factory()->create();
    actingAsRole('viewer', ['properties.view']);

    $this->postJson('/api/v1/properties', validProperty())->assertForbidden();
    $this->patchJson("/api/v1/properties/{$property->id}", ['title' => 'x'])->assertForbidden();
    $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'available'])->assertForbidden();
    $this->deleteJson("/api/v1/properties/{$property->id}")->assertForbidden();
    expect(Property::count())->toBe(1);
});

it('creates a draft with a reference code, slug, amenities and a history row', function () {
    $lift = Amenity::create(['name' => 'Lift']);
    $pool = Amenity::create(['name' => 'Pool']);
    $user = actingAsRole('editor', ['properties.create']);

    $response = $this->postJson('/api/v1/properties', validProperty(['amenity_ids' => [$lift->id, $pool->id], 'beds' => 3]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.area_unit', 'sqft')
        ->assertJsonPath('data.price', 12_500_000)
        ->assertJsonPath('data.status_history.0.to_status', 'draft')
        ->assertJsonPath('data.status_history.0.user_id', $user->id);

    expect($response->json('data.ref_code'))->toMatch('/^P-\d{6}$/')
        ->and($response->json('data.slug'))->toStartWith('sunny-flat-in-gulshan-p-')
        ->and($response->json('data.amenity_ids'))->toEqualCanonicalizing([$lift->id, $pool->id]);
});

it('gives each property a distinct, increasing reference code', function () {
    $a = Property::factory()->create();
    $b = Property::factory()->create();

    expect($a->ref_code)->not->toBe($b->ref_code)
        ->and((int) substr($b->ref_code, 2))->toBeGreaterThan((int) substr($a->ref_code, 2));
});

it('requires a rent period for rent listings and drops it for sale listings', function () {
    actingAsRole('editor', ['properties.create', 'properties.update']);

    $missing = $this->postJson('/api/v1/properties', validProperty(['listing_type' => 'rent']))->assertStatus(422);
    expect(array_keys($missing->json('error.details')))->toEqual(['rent_period']);

    $sale = $this->postJson('/api/v1/properties', validProperty(['rent_period' => 'month']))->assertStatus(422);
    expect(array_keys($sale->json('error.details')))->toEqual(['rent_period']);

    $id = $this->postJson('/api/v1/properties', validProperty(['listing_type' => 'rent', 'rent_period' => 'month']))
        ->assertCreated()->assertJsonPath('data.rent_period', 'month')->json('data.id');

    $this->patchJson("/api/v1/properties/{$id}", ['listing_type' => 'sale'])->assertOk()->assertJsonPath('data.rent_period', null);
});

it('validates input', function () {
    actingAsRole('editor', ['properties.create']);

    $bad = $this->postJson('/api/v1/properties', ['price' => -1, 'lat' => 100, 'listing_type' => 'swap', 'amenity_ids' => [999]])->assertStatus(422);
    expect(array_keys($bad->json('error.details')))
        ->toEqualCanonicalizing(['title', 'listing_type', 'property_type_id', 'location_id', 'price', 'lat', 'lng', 'amenity_ids.0']);
});

it('updates fields and syncs amenities without touching the status', function () {
    $lift = Amenity::create(['name' => 'Lift']);
    $property = Property::factory()->available()->create();
    actingAsRole('editor', ['properties.update']);

    $this->patchJson("/api/v1/properties/{$property->id}", ['title' => 'New title', 'beds' => 4, 'amenity_ids' => [$lift->id], 'featured' => true])
        ->assertOk()
        ->assertJsonPath('data.title', 'New title')
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.amenity_ids', [$lift->id]);

    $status = $this->patchJson("/api/v1/properties/{$property->id}", ['status' => 'sold'])->assertStatus(422);
    expect(array_keys($status->json('error.details')))->toEqual(['status']);
    expect($property->refresh()->status)->toBe(PropertyStatus::Available);
});

it('keeps slugs unique', function () {
    $other = Property::factory()->create();
    actingAsRole('editor', ['properties.create']);

    $dup = $this->postJson('/api/v1/properties', validProperty(['slug' => $other->slug]))->assertStatus(422);
    expect(array_keys($dup->json('error.details')))->toEqual(['slug']);
});

it('lists every status for the admin with filters, and shows one with history', function () {
    Property::factory()->create(['title' => 'Draft house']);
    $sold = Property::factory()->status(PropertyStatus::Sold)->create(['title' => 'Sold villa']);
    actingAsRole('viewer', ['properties.view']);

    $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/properties?status=sold')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sold->id);
    $this->getJson('/api/v1/properties?q='.$sold->ref_code)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/properties/{$sold->id}")->assertOk()->assertJsonPath('data.owner_id', null)->assertJsonStructure(['data' => ['status_history']]);
});

it('deletes a property', function () {
    $property = Property::factory()->create();
    actingAsRole('editor', ['properties.delete']);

    $this->deleteJson("/api/v1/properties/{$property->id}")->assertNoContent();
    expect(Property::count())->toBe(0);
});

it('seeds the menu row idempotently', function () {
    $this->seed(PropertiesModuleSeeder::class);

    expect(Menu::where('permission_name', 'properties.view')->count())->toBe(1);
});

<?php

use App\Models\Location;
use App\Models\Menu;
use Database\Seeders\LocationsModuleSeeder;

beforeEach(fn () => $this->seed(LocationsModuleSeeder::class));

function place(string $slug, ?Location $parent = null, array $extra = []): Location
{
    return Location::create(['name' => ucfirst($slug), 'slug' => $slug, 'parent_id' => $parent?->id, ...$extra]);
}

it('requires authentication and locations.view for the admin list', function () {
    $this->getJson('/api/v1/locations')->assertUnauthorized();

    actingAsRole('nobody');
    $this->getJson('/api/v1/locations')->assertForbidden();
});

it('forbids writes without the matching permission', function () {
    $city = place('dhaka');
    actingAsRole('viewer', ['locations.view']);

    $this->postJson('/api/v1/locations', ['name' => 'X', 'slug' => 'x'])->assertForbidden();
    $this->patchJson("/api/v1/locations/{$city->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/v1/locations/{$city->id}")->assertForbidden();
    expect(Location::count())->toBe(1);
});

it('builds a country > city > area tree and rejects a fourth level', function () {
    actingAsRole('editor', ['locations.create']);

    $country = $this->postJson('/api/v1/locations', ['name' => 'Bangladesh', 'slug' => 'bangladesh', 'lat' => 23.7, 'lng' => 90.4])
        ->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');
    $city = $this->postJson('/api/v1/locations', ['name' => 'Dhaka', 'slug' => 'dhaka', 'parent_id' => $country])->assertCreated()->json('data.id');
    $area = $this->postJson('/api/v1/locations', ['name' => 'Gulshan', 'slug' => 'gulshan', 'parent_id' => $city])->assertCreated()->json('data.id');

    $fourth = $this->postJson('/api/v1/locations', ['name' => 'Block A', 'slug' => 'block-a', 'parent_id' => $area])->assertStatus(422);
    expect(array_keys($fourth->json('error.details')))->toEqual(['parent_id']);
});

it('keeps slugs unique per parent, including among roots', function () {
    $dhaka = place('dhaka');
    $sylhet = place('sylhet');
    actingAsRole('editor', ['locations.create', 'locations.update']);

    $dupRoot = $this->postJson('/api/v1/locations', ['name' => 'Again', 'slug' => 'dhaka'])->assertStatus(422);
    expect(array_keys($dupRoot->json('error.details')))->toEqual(['slug']);

    $this->postJson('/api/v1/locations', ['name' => 'Centre', 'slug' => 'centre', 'parent_id' => $dhaka->id])->assertCreated();
    $this->postJson('/api/v1/locations', ['name' => 'Centre', 'slug' => 'centre', 'parent_id' => $sylhet->id])->assertCreated();
    $this->postJson('/api/v1/locations', ['name' => 'Centre', 'slug' => 'centre', 'parent_id' => $sylhet->id])->assertStatus(422);

    // Moving a node under a parent that already has its slug is rejected too.
    $moved = place('centre');
    $clash = $this->patchJson("/api/v1/locations/{$moved->id}", ['parent_id' => $dhaka->id])->assertStatus(422);
    expect(array_keys($clash->json('error.details')))->toEqual(['slug']);
});

it('rejects moving a location under itself or its descendants and keeps the depth limit', function () {
    $country = place('bd');
    $city = place('dhaka', $country);
    $area = place('gulshan', $city);
    $other = place('uk');
    actingAsRole('editor', ['locations.update']);

    $self = $this->patchJson("/api/v1/locations/{$country->id}", ['parent_id' => $country->id])->assertStatus(422);
    expect(array_keys($self->json('error.details')))->toEqual(['parent_id']);
    $cycle = $this->patchJson("/api/v1/locations/{$country->id}", ['parent_id' => $area->id])->assertStatus(422);
    expect(array_keys($cycle->json('error.details')))->toEqual(['parent_id']);

    // Moving a country with two levels below it under another country would make four levels.
    $deep = $this->patchJson("/api/v1/locations/{$country->id}", ['parent_id' => $other->id])->assertStatus(422);
    expect(array_keys($deep->json('error.details')))->toEqual(['parent_id']);

    // A leaf can move; a city can become a root.
    $this->patchJson("/api/v1/locations/{$area->id}", ['parent_id' => $other->id])->assertOk()->assertJsonPath('data.parent_id', $other->id);
    $this->patchJson("/api/v1/locations/{$city->id}", ['parent_id' => null])->assertOk()->assertJsonPath('data.parent_id', null);
});

it('refuses to delete a location that has children', function () {
    $country = place('bd');
    $city = place('dhaka', $country);
    actingAsRole('editor', ['locations.delete']);

    $this->deleteJson("/api/v1/locations/{$country->id}")->assertStatus(409);
    $this->deleteJson("/api/v1/locations/{$city->id}")->assertNoContent();
    $this->deleteJson("/api/v1/locations/{$country->id}")->assertNoContent();
    expect(Location::count())->toBe(0);
});

it('serves only active locations publicly, hiding the children of an inactive parent', function () {
    $country = place('bd');
    $city = place('dhaka', $country);
    place('gulshan', $city);
    $hidden = place('hidden', $country, ['is_active' => false]);
    place('under-hidden', $hidden);

    $this->getJson('/api/v1/public/locations')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissing(['slug' => 'hidden'])
        ->assertJsonMissing(['slug' => 'under-hidden']);
});

it('validates input', function () {
    actingAsRole('editor', ['locations.create']);

    $create = $this->postJson('/api/v1/locations', ['lat' => 120, 'lng' => 'x', 'parent_id' => 999, 'is_active' => 'maybe'])->assertStatus(422);
    expect(array_keys($create->json('error.details')))->toEqualCanonicalizing(['name', 'slug', 'lat', 'lng', 'parent_id', 'is_active']);
});

it('seeds the menu row idempotently', function () {
    $this->seed(LocationsModuleSeeder::class);

    expect(Menu::where('permission_name', 'locations.view')->count())->toBe(1);
});

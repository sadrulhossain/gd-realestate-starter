<?php

use App\Models\Amenity;
use App\Models\Menu;
use App\Models\PropertyType;
use Database\Seeders\AmenitiesModuleSeeder;
use Database\Seeders\PropertyTypesModuleSeeder;

beforeEach(fn () => $this->seed([PropertyTypesModuleSeeder::class, AmenitiesModuleSeeder::class]));

it('requires authentication and the view permission', function (string $path) {
    $this->getJson($path)->assertUnauthorized();

    actingAsRole('nobody');
    $this->getJson($path)->assertForbidden();
})->with(['/api/v1/property-types', '/api/v1/amenities']);

it('seeds the defaults once', function () {
    $this->seed([PropertyTypesModuleSeeder::class, AmenitiesModuleSeeder::class]);

    expect(PropertyType::count())->toBe(7)
        ->and(PropertyType::where('key', 'plot')->value('group'))->toBe('land')
        ->and(Amenity::count())->toBe(8)
        ->and(Menu::where('permission_name', 'property_types.view')->count())->toBe(1)
        ->and(Menu::where('permission_name', 'amenities.view')->count())->toBe(1);
});

it('does not overwrite edited seed rows when re-seeding', function () {
    PropertyType::where('key', 'shop')->update(['name' => 'Retail unit']);

    $this->seed(PropertyTypesModuleSeeder::class);

    expect(PropertyType::where('key', 'shop')->value('name'))->toBe('Retail unit');
});

it('serves only active property types and amenities publicly', function () {
    PropertyType::where('key', 'plot')->update(['is_active' => false]);
    Amenity::where('name', 'Gym')->update(['is_active' => false]);

    $this->getJson('/api/v1/public/property-types')->assertOk()->assertJsonCount(6, 'data')->assertJsonMissing(['key' => 'plot']);
    $this->getJson('/api/v1/public/amenities')->assertOk()->assertJsonCount(7, 'data')->assertJsonMissing(['name' => 'Gym']);
});

it('manages property types', function () {
    actingAsRole('editor', ['property_types.create', 'property_types.update', 'property_types.delete']);

    $id = $this->postJson('/api/v1/property-types', ['key' => 'villa', 'name' => 'Villa', 'group' => 'residential'])
        ->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

    $this->patchJson("/api/v1/property-types/{$id}", ['name' => 'Luxury villa', 'is_active' => false])->assertOk()->assertJsonPath('data.name', 'Luxury villa');
    $this->deleteJson("/api/v1/property-types/{$id}")->assertNoContent();
    expect(PropertyType::where('key', 'villa')->exists())->toBeFalse();
});

it('validates property types: group, unique key', function () {
    actingAsRole('editor', ['property_types.create']);

    $bad = $this->postJson('/api/v1/property-types', ['key' => 'Bad Key', 'group' => 'castle'])->assertStatus(422);
    expect(array_keys($bad->json('error.details')))->toEqualCanonicalizing(['name', 'key', 'group']);

    $dup = $this->postJson('/api/v1/property-types', ['key' => 'plot', 'name' => 'Plot', 'group' => 'land'])->assertStatus(422);
    expect(array_keys($dup->json('error.details')))->toEqual(['key']);
});

it('forbids property type and amenity writes without permission', function () {
    actingAsRole('viewer', ['property_types.view', 'amenities.view']);

    $this->postJson('/api/v1/property-types', ['key' => 'villa', 'name' => 'Villa', 'group' => 'residential'])->assertForbidden();
    $this->deleteJson('/api/v1/property-types/'.PropertyType::first()->id)->assertForbidden();
    $this->postJson('/api/v1/amenities', ['name' => 'Sauna'])->assertForbidden();
    $this->deleteJson('/api/v1/amenities/'.Amenity::first()->id)->assertForbidden();
});

it('manages amenities', function () {
    actingAsRole('editor', ['amenities.create', 'amenities.update', 'amenities.delete']);

    $id = $this->postJson('/api/v1/amenities', ['name' => 'Sauna', 'name_2nd' => 'সনা', 'icon' => 'flame', 'group' => 'lifestyle'])
        ->assertCreated()->assertJsonPath('data.name_2nd', 'সনা')->json('data.id');

    $this->patchJson("/api/v1/amenities/{$id}", ['group' => null])->assertOk()->assertJsonPath('data.group', null);
    $this->deleteJson("/api/v1/amenities/{$id}")->assertNoContent();

    $bad = $this->postJson('/api/v1/amenities', ['sort_order' => -1])->assertStatus(422);
    expect(array_keys($bad->json('error.details')))->toEqualCanonicalizing(['name', 'sort_order']);
});

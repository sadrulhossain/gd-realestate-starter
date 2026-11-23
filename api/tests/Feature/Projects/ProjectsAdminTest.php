<?php

use App\Models\Developer;
use App\Models\Menu;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\Property;
use App\Models\PropertyType;
use Database\Seeders\DevelopersModuleSeeder;
use Database\Seeders\ProjectsModuleSeeder;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

// This API wraps validation errors in `error.details`, so the stock assertion does not apply.
TestResponse::macro('assertDetails', function (array $keys) {
    /** @var TestResponse $this */
    expect(array_keys($this->json('error.details') ?? []))->toContain(...$keys);

    return $this;
});

beforeEach(function () {
    $this->seed(DevelopersModuleSeeder::class);
    $this->seed(ProjectsModuleSeeder::class);
});

function apartmentType(): PropertyType
{
    return PropertyType::query()->firstOrCreate(['key' => 'apartment'], ['name' => 'Apartment', 'group' => 'residential']);
}

it('requires authentication and the view permission', function () {
    $project = Project::factory()->create();

    foreach (['/api/v1/developers', '/api/v1/projects', "/api/v1/projects/{$project->id}/units"] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }

    actingAsRole('nobody');
    foreach (['/api/v1/developers', '/api/v1/projects', "/api/v1/projects/{$project->id}", "/api/v1/projects/{$project->id}/units"] as $url) {
        $this->getJson($url)->assertForbidden();
    }
});

it('forbids writes without the matching permission', function () {
    $project = Project::factory()->create();
    $unit = ProjectUnit::factory()->for($project)->create();
    actingAsRole('viewer', ['developers.view', 'projects.view', 'project_units.view', 'properties.create']);

    $this->postJson('/api/v1/developers', ['name' => 'X'])->assertForbidden();
    $this->postJson('/api/v1/projects', ['name' => 'X'])->assertForbidden();
    $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'X'])->assertForbidden();
    $this->postJson("/api/v1/projects/{$project->id}/units", ['code' => 'A'])->assertForbidden();
    $this->deleteJson("/api/v1/units/{$unit->id}")->assertForbidden();
    // Publishing needs project_units.update as well as properties.create.
    $this->postJson("/api/v1/units/{$unit->id}/publish")->assertForbidden();
    expect(Property::count())->toBe(0);
});

it('creates, updates and deletes a developer', function () {
    actingAsRole('editor', ['developers.create', 'developers.update', 'developers.delete']);

    $id = $this->postJson('/api/v1/developers', ['name' => 'Acme Builders'])
        ->assertCreated()->assertJsonPath('data.slug', 'acme-builders')->assertJsonPath('data.is_active', true)->json('data.id');

    $this->patchJson("/api/v1/developers/{$id}", ['phone' => '+880 1700'])->assertOk()->assertJsonPath('data.phone', '+880 1700');
    $this->deleteJson("/api/v1/developers/{$id}")->assertNoContent();
    expect(Developer::count())->toBe(0);
});

it('rejects invalid developer and project input with field errors', function () {
    actingAsRole('editor', ['developers.create', 'projects.create']);

    $this->postJson('/api/v1/developers', ['website' => 'ftp://x', 'email' => 'nope'])
        ->assertUnprocessable()->assertDetails(['name', 'website', 'email']);
    $this->postJson('/api/v1/projects', ['status' => 'sold', 'developer_id' => 999, 'lat' => 10])
        ->assertUnprocessable()->assertDetails(['name', 'location_id', 'status', 'developer_id', 'lng']);
});

it('refuses to delete a developer that has projects (409)', function () {
    $project = Project::factory()->create();
    actingAsRole('editor', ['developers.delete', 'projects.delete']);

    $this->deleteJson("/api/v1/developers/{$project->developer_id}")->assertStatus(409);
    $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();
    $this->deleteJson("/api/v1/developers/{$project->developer_id}")->assertNoContent();
});

it('creates a project with a generated slug and lists it with unit counts', function () {
    $project = Project::factory()->make();
    actingAsRole('editor', ['projects.view', 'projects.create']);

    $this->postJson('/api/v1/projects', [
        'name' => 'Skyline Towers', 'location_id' => $project->location_id, 'developer_id' => $project->developer_id,
        'status' => 'under_construction', 'handover_date' => '2028-06-30',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'skyline-towers')
        ->assertJsonPath('data.status', 'under_construction')
        ->assertJsonPath('data.handover_date', '2028-06-30')
        ->assertJsonPath('data.is_active', true);

    $this->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.0.units_count', 0);
});

it('manages units: unique code per project, 422 on bad input, 409 when published', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $type = apartmentType();
    actingAsRole('editor', ['project_units.view', 'project_units.create', 'project_units.update', 'project_units.delete', 'properties.create']);

    $body = ['code' => 'A-101', 'property_type_id' => $type->id, 'price' => 9_000_000, 'beds' => 2];
    $id = $this->postJson("/api/v1/projects/{$project->id}/units", $body)
        ->assertCreated()->assertJsonPath('data.status', 'available')->assertJsonPath('data.area_unit', 'sqft')->json('data.id');

    $this->postJson("/api/v1/projects/{$project->id}/units", $body)->assertUnprocessable()->assertDetails(['code']);
    $this->postJson("/api/v1/projects/{$other->id}/units", $body)->assertCreated(); // same code, other project
    $this->postJson("/api/v1/projects/{$project->id}/units", ['status' => 'gone'])
        ->assertUnprocessable()->assertDetails(['code', 'property_type_id', 'price', 'status']);

    $this->patchJson("/api/v1/units/{$id}", ['status' => 'reserved', 'code' => 'A-101'])->assertOk()->assertJsonPath('data.status', 'reserved');
    $this->getJson("/api/v1/projects/{$project->id}/units")->assertOk()->assertJsonCount(1, 'data');

    $this->postJson("/api/v1/units/{$id}/publish")->assertCreated();
    $this->deleteJson("/api/v1/units/{$id}")->assertStatus(409);
});

it('publishes a unit as a draft property, once', function () {
    $project = Project::factory()->create(['name' => 'Skyline', 'lat' => 23.78, 'lng' => 90.41, 'address_line' => 'Road 1']);
    $unit = ProjectUnit::factory()->for($project)->create(['code' => 'B-7', 'price' => 12_000_000, 'beds' => 3, 'floor' => 7]);
    actingAsRole('editor', ['project_units.update', 'properties.create']);

    $response = $this->postJson("/api/v1/units/{$unit->id}/publish")
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'Skyline - B-7')
        ->assertJsonPath('data.listing_type', 'sale')
        ->assertJsonPath('data.price', 12_000_000)
        ->assertJsonPath('data.beds', 3)
        ->assertJsonPath('data.floor', 7)
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.unit_id', $unit->id)
        ->assertJsonPath('data.location_id', $project->location_id)
        ->assertJsonPath('data.lat', 23.78)
        ->assertJsonPath('data.status_history.0.to_status', 'draft');

    expect($response->json('data.ref_code'))->toMatch('/^P-\d{6}$/');

    $this->postJson("/api/v1/units/{$unit->id}/publish")->assertUnprocessable()->assertDetails(['unit']);
    expect(Property::count())->toBe(1);
});

it('publishes a unit for rent only with a rent period', function () {
    $unit = ProjectUnit::factory()->create();
    actingAsRole('editor', ['project_units.update', 'properties.create']);

    $this->postJson("/api/v1/units/{$unit->id}/publish", ['listing_type' => 'rent'])->assertUnprocessable()->assertDetails(['rent_period']);
    $this->postJson("/api/v1/units/{$unit->id}/publish", ['listing_type' => 'sale', 'rent_period' => 'month'])->assertUnprocessable();
    $this->postJson("/api/v1/units/{$unit->id}/publish", ['listing_type' => 'rent', 'rent_period' => 'month'])
        ->assertCreated()->assertJsonPath('data.rent_period', 'month');
});

it('keeps a project with linked properties (409) and validates unit links on properties', function () {
    $unit = ProjectUnit::factory()->create();
    $otherProject = Project::factory()->create();
    $property = Property::factory()->create(['project_id' => $unit->project_id, 'unit_id' => $unit->id]);
    actingAsRole('editor', ['projects.delete', 'properties.update']);

    $this->deleteJson("/api/v1/projects/{$unit->project_id}")->assertStatus(409);
    $this->patchJson("/api/v1/properties/{$property->id}", ['project_id' => $otherProject->id])
        ->assertUnprocessable()->assertDetails(['unit_id']);

    $second = Property::factory()->create();
    $this->patchJson("/api/v1/properties/{$second->id}", ['project_id' => $unit->project_id, 'unit_id' => $unit->id])
        ->assertUnprocessable()->assertDetails(['unit_id']);
});

it('serves public developers and projects without private fields', function () {
    $developer = Developer::factory()->create(['email' => 'secret@example.com', 'phone' => '123']);
    $project = Project::factory()->create(['developer_id' => $developer->id, 'name' => 'Open Project']);
    Project::factory()->create(['name' => 'Hidden', 'is_active' => false, 'developer_id' => $developer->id]);
    ProjectUnit::factory()->for($project)->create(['code' => 'U1', 'price' => 5_000_000]);
    ProjectUnit::factory()->for($project)->create(['code' => 'U2', 'price' => 7_000_000]);
    ProjectUnit::factory()->for($project)->create(['code' => 'U3', 'price' => 1_000_000, 'status' => 'sold']);

    $list = $this->getJson('/api/v1/public/projects')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.units_count', 2)
        ->assertJsonPath('data.0.from_price', 5_000_000);
    expect($list->json('data.0'))->not->toHaveKeys(['is_active', 'created_at', 'units']);
    expect($list->json('data.0.developer'))->not->toHaveKeys(['email', 'phone']);

    $show = $this->getJson("/api/v1/public/projects/{$project->slug}")->assertOk()->assertJsonCount(3, 'data.units');
    expect($show->json('data.units.0'))->not->toHaveKeys(['property_id', 'created_at']);
    $this->getJson('/api/v1/public/projects/hidden')->assertNotFound();
    $this->getJson('/api/v1/public/developers')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.email');
});

it('filters public projects by location tree, developer and status', function () {
    $a = Project::factory()->create(['status' => 'completed']);
    Project::factory()->create();

    $this->getJson("/api/v1/public/projects?location={$a->location_id}&status=completed")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/public/projects?developer={$a->developer_id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/public/projects?status=nope')->assertUnprocessable();
});

it('seeds permissions and menu rows idempotently', function () {
    $this->seed(DevelopersModuleSeeder::class);
    $this->seed(ProjectsModuleSeeder::class);

    expect(Permission::query()->whereIn('name', ['developers.view', 'projects.delete', 'project_units.update'])->count())->toBe(3)
        ->and(Menu::query()->whereIn('path', ['/developers', '/projects'])->count())->toBe(2);
});

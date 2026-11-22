<?php

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\PropertyStatusService;
use Database\Seeders\PropertiesModuleSeeder;

beforeEach(fn () => $this->seed(PropertiesModuleSeeder::class));

it('moves through the workflow and writes a history row per change', function () {
    $property = Property::factory()->create();
    $user = actingAsRole('editor', ['properties.update']);

    $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'available', 'note' => 'Photos done'])
        ->assertOk()->assertJsonPath('data.status', 'available');
    $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'under_offer'])->assertOk();
    $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'sold'])->assertOk()->assertJsonPath('data.status', 'sold');

    $history = $property->refresh()->statusHistory;
    expect($history->pluck('to_status')->all())->toBe(['sold', 'under_offer', 'available', 'draft'])
        ->and($history[2]->note)->toBe('Photos done')
        ->and($history[2]->user_id)->toBe($user->id)
        ->and($history[1]->from_status)->toBe('available')
        ->and($property->published_at)->not->toBeNull();
});

it('rejects a transition the table does not allow', function () {
    $property = Property::factory()->create();
    actingAsRole('editor', ['properties.update']);

    $bad = $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'sold'])->assertStatus(422);

    expect(array_keys($bad->json('error.details')))->toEqual(['status'])
        ->and($property->refresh()->status)->toBe(PropertyStatus::Draft)
        ->and($property->statusHistory)->toHaveCount(1);
});

it('allows sold only for sale and rented only for rent listings', function () {
    $sale = Property::factory()->available()->create();
    $rent = Property::factory()->available()->rent()->create();
    actingAsRole('editor', ['properties.update']);

    $this->postJson("/api/v1/properties/{$sale->id}/status", ['status' => 'rented'])->assertStatus(422);
    $this->postJson("/api/v1/properties/{$rent->id}/status", ['status' => 'sold'])->assertStatus(422);
    $this->postJson("/api/v1/properties/{$rent->id}/status", ['status' => 'rented'])->assertOk();
    $this->postJson("/api/v1/properties/{$sale->id}/status", ['status' => 'sold'])->assertOk();
});

it('validates the status value', function () {
    $property = Property::factory()->create();
    actingAsRole('editor', ['properties.update']);

    $bad = $this->postJson("/api/v1/properties/{$property->id}/status", ['status' => 'bogus'])->assertStatus(422);
    expect(array_keys($bad->json('error.details')))->toEqual(['status']);
});

it('has a way out of every status', function () {
    foreach (PropertyStatus::cases() as $status) {
        expect($status->next())->not->toBeEmpty()->not->toContain($status);
    }
});

it('keeps the first publish date when a property is reopened', function () {
    $property = Property::factory()->create();
    $service = app(PropertyStatusService::class);

    $service->transition($property, PropertyStatus::Available);
    $first = $property->refresh()->published_at;
    $service->transition($property, PropertyStatus::Archived);
    $service->transition($property, PropertyStatus::Draft);
    $service->transition($property, PropertyStatus::Available);

    expect($property->refresh()->published_at->equalTo($first))->toBeTrue();
});

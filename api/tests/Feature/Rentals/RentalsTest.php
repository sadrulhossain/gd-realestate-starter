<?php

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Menu;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Services\LeaseService;
use Database\Seeders\RentalsModuleSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

const RENTAL_PERMISSIONS = [
    'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
    'leases.view', 'leases.create', 'leases.update', 'leases.delete',
    'rent_charges.view', 'rent_charges.update',
    'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.delete',
    'rental_reports.view', 'properties.view', 'properties.update', 'properties.delete', 'dashboard.view',
];

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-03-15 10:00:00'));
});

function manager(): void
{
    actingAsRole('admin', RENTAL_PERMISSIONS);
}

function rentFlat(PropertyStatus $status = PropertyStatus::Available): Property
{
    return Property::factory()->rent()->status($status)->create();
}

/** @param  array<string, mixed>  $with */
function draftLease(array $with = []): Lease
{
    return Lease::query()->create([
        'property_id' => $with['property_id'] ?? rentFlat()->id,
        'tenant_id' => Tenant::query()->create(['name' => 'Rahim Uddin'])->id,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'rent_amount' => 2_500_000,
        ...$with,
    ]);
}

function activeLease(array $with = []): Lease
{
    return app(LeaseService::class)->transition(draftLease($with), LeaseStatus::Active);
}

it('rejects anonymous callers and users without the permission', function (string $path) {
    $this->getJson("/api/v1/{$path}")->assertUnauthorized();

    actingAsRole('agent', ['properties.view']);
    $this->getJson("/api/v1/{$path}")->assertForbidden();
})->with(['tenants', 'leases', 'rent-charges', 'maintenance', 'rental-reports/arrears']);

it('creates, searches and updates tenants, and keeps one with a lease', function () {
    manager();

    $this->postJson('/api/v1/tenants', [])->assertUnprocessable()->assertJsonPath('error.details.name.0', 'The name field is required.');
    $id = $this->postJson('/api/v1/tenants', ['name' => 'Karim', 'phone' => '0170'])->assertCreated()->json('data.id');

    $this->getJson('/api/v1/tenants?q=kar')->assertOk()->assertJsonPath('meta.total', 1);
    $this->patchJson("/api/v1/tenants/{$id}", ['email' => 'karim@example.test'])->assertOk()->assertJsonPath('data.name', 'Karim');

    $lease = draftLease();
    $this->deleteJson("/api/v1/tenants/{$lease->tenant_id}")->assertConflict();
    $this->deleteJson("/api/v1/tenants/{$id}")->assertNoContent();
});

it('validates a lease and creates it as a draft', function () {
    manager();
    $sale = Property::factory()->available()->create();
    $tenant = Tenant::query()->create(['name' => 'Rahim']);

    $this->postJson('/api/v1/leases', ['property_id' => $sale->id, 'tenant_id' => $tenant->id, 'start_date' => '2026-04-01', 'end_date' => '2026-03-01', 'rent_amount' => 0])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['property_id', 'rent_amount']]]);

    $flat = rentFlat();
    $body = ['property_id' => $flat->id, 'tenant_id' => $tenant->id, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'rent_amount' => 100_000];

    $this->postJson('/api/v1/leases', [...$body, 'end_date' => '2026-03-01'])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['end_date']]]);
    $this->postJson('/api/v1/leases', [...$body, 'end_date' => '2040-01-01'])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['end_date']]]);
    $this->postJson('/api/v1/leases', $body)->assertCreated()
        ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.rent_period', 'month')->assertJsonPath('data.start_date', '2026-04-01');

    expect(RentCharge::query()->count())->toBe(0)->and($flat->refresh()->status)->toBe(PropertyStatus::Available);
});

it('activating writes the schedule and marks the property rented', function () {
    manager();
    $lease = draftLease();

    $this->postJson("/api/v1/leases/{$lease->id}/status", ['status' => 'active'])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(12, 'data.charges')
        ->assertJsonPath('data.charges.0.due_date', '2026-01-01')
        ->assertJsonPath('data.charges.11.period_end', '2026-12-31');

    expect($lease->property->refresh()->status)->toBe(PropertyStatus::Rented);
});

it('keeps month ends in the schedule and charges a short last period in full', function () {
    $lease = draftLease(['start_date' => '2026-01-31', 'end_date' => '2026-04-10']);
    $rows = app(LeaseService::class)->schedule($lease);

    expect(array_column($rows, 'period_start'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31'])
        ->and(array_column($rows, 'period_end'))->toBe(['2026-02-27', '2026-03-30', '2026-04-10'])
        ->and(array_column($rows, 'amount'))->toBe([2_500_000, 2_500_000, 2_500_000]);

    $yearly = draftLease(['start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'rent_period' => 'year']);
    expect(app(LeaseService::class)->schedule($yearly))->toHaveCount(2);
});

it('refuses to activate a second lease, an unlisted property or an expired lease', function () {
    manager();
    $first = activeLease();
    $second = draftLease(['property_id' => $first->property_id]);

    $this->postJson("/api/v1/leases/{$second->id}/status", ['status' => 'active'])->assertUnprocessable();

    $hidden = draftLease(['property_id' => rentFlat(PropertyStatus::Draft)->id]);
    $this->postJson("/api/v1/leases/{$hidden->id}/status", ['status' => 'active'])->assertUnprocessable();

    $old = draftLease(['start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
    $this->postJson("/api/v1/leases/{$old->id}/status", ['status' => 'active'])->assertUnprocessable();
    $this->postJson("/api/v1/leases/{$old->id}/status", ['status' => 'ended'])->assertUnprocessable();

    expect(RentCharge::query()->count())->toBe(12);
});

it('fixes the terms and the records once a lease has started', function () {
    manager();
    $lease = activeLease();

    $this->patchJson("/api/v1/leases/{$lease->id}", ['rent_amount' => 1])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['rent_amount']]]);
    // The admin form sends every field back: unchanged terms are fine.
    $this->patchJson("/api/v1/leases/{$lease->id}", [
        'property_id' => $lease->property_id, 'tenant_id' => $lease->tenant_id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'rent_amount' => 2_500_000, 'rent_period' => 'month', 'deposit_amount' => 0, 'notes' => 'Keys handed over',
    ])->assertOk()->assertJsonPath('data.notes', 'Keys handed over');

    $this->deleteJson("/api/v1/leases/{$lease->id}")->assertConflict();
    $this->postJson("/api/v1/properties/{$lease->property_id}/status", ['status' => 'available'])->assertUnprocessable();
    $this->deleteJson("/api/v1/properties/{$lease->property_id}")->assertConflict();
});

it('terminating voids later unpaid periods and frees the property', function () {
    manager();
    $lease = activeLease();
    $may = $lease->charges()->where('period_start', '2026-05-01')->firstOrFail();
    $this->postJson("/api/v1/rent-charges/{$may->id}/payments", ['amount' => 100, 'paid_on' => '2026-03-15', 'method' => 'bank'])->assertOk();

    $this->postJson("/api/v1/leases/{$lease->id}/status", ['status' => 'terminated', 'ended_on' => '2026-04-01'])->assertUnprocessable();
    $this->postJson("/api/v1/leases/{$lease->id}/status", ['status' => 'terminated', 'ended_on' => '2025-03-01'])->assertUnprocessable();
    $this->postJson("/api/v1/leases/{$lease->id}/status", ['status' => 'terminated'])->assertOk()->assertJsonPath('data.ended_on', '2026-03-15');

    // January to March stay owed, May keeps its part payment, the other eight are void.
    expect($lease->charges()->where('status', 'void')->count())->toBe(8)
        ->and($may->refresh()->status)->toBe('partial')
        ->and($lease->property->refresh()->status)->toBe(PropertyStatus::Available);
});

it('records and reverses payments without overpaying', function () {
    manager();
    $charge = activeLease()->charges()->firstOrFail();
    $pay = fn (int $amount) => $this->postJson("/api/v1/rent-charges/{$charge->id}/payments", ['amount' => $amount, 'paid_on' => '2026-03-10', 'method' => 'cash']);

    $pay(1_000_000)->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.overdue', true);
    $pay(1_500_001)->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['amount']]]);
    $done = $pay(1_500_000)->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.overdue', false);

    $payment = $done->json('data.payments.0.id');
    $other = RentCharge::query()->whereKeyNot($charge->id)->firstOrFail();
    $this->deleteJson("/api/v1/rent-charges/{$other->id}/payments/{$payment}")->assertNotFound();
    $this->deleteJson("/api/v1/rent-charges/{$charge->id}/payments/{$payment}")->assertOk()->assertJsonPath('data.paid_amount', 1_500_000);

    $this->postJson("/api/v1/rent-charges/{$charge->id}/payments", ['amount' => 1, 'paid_on' => '2026-04-01', 'method' => 'barter'])
        ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['paid_on', 'method']]]);
});

it('lists overdue charges and changes only the due date and note', function () {
    manager();
    $charge = activeLease()->charges()->firstOrFail();

    // January, February and March are past due on 15 March.
    $this->getJson('/api/v1/rent-charges?status=overdue')->assertOk()->assertJsonPath('meta.total', 3);
    $this->patchJson("/api/v1/rent-charges/{$charge->id}", ['due_date' => '2026-04-01', 'amount' => 1])->assertOk()
        ->assertJsonPath('data.overdue', false)->assertJsonPath('data.amount', 2_500_000);
});

it('runs a maintenance request through its statuses', function () {
    manager();
    $lease = activeLease();

    $this->postJson('/api/v1/maintenance', ['property_id' => rentFlat()->id, 'lease_id' => $lease->id, 'title' => 'Leak'])
        ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['lease_id']]]);

    $id = $this->postJson('/api/v1/maintenance', ['property_id' => $lease->property_id, 'lease_id' => $lease->id, 'title' => 'Leak'])
        ->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.priority', 'normal')->json('data.id');

    $this->patchJson("/api/v1/maintenance/{$id}", ['property_id' => rentFlat()->id])->assertUnprocessable();
    $this->postJson("/api/v1/maintenance/{$id}/status", ['status' => 'resolved'])->assertOk();
    $this->postJson("/api/v1/maintenance/{$id}/status", ['status' => 'cancelled'])->assertUnprocessable();
    $this->postJson("/api/v1/maintenance/{$id}/status", ['status' => 'open'])->assertOk()->assertJsonPath('data.resolved_at', null);
    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.open_maintenance', 1)->assertJsonPath('data.overdue_rent', 3);
});

it('builds the rent roll, arrears and collections reports', function () {
    manager();
    $charge = activeLease()->charges()->firstOrFail();
    $this->postJson("/api/v1/rent-charges/{$charge->id}/payments", ['amount' => 500_000, 'paid_on' => '2026-02-01', 'method' => 'bank', 'reference' => '=cmd'])->assertOk();

    $this->getJson('/api/v1/rental-reports/rent-roll')->assertOk()->assertJsonPath('data.rows.0.2', '25000.00')->assertJsonPath('data.rows.1.5', '70000.00');
    $this->getJson('/api/v1/rental-reports/arrears')->assertOk()->assertJsonPath('data.rows.0.5', '20000.00')->assertJsonCount(4, 'data.rows');
    $this->getJson('/api/v1/rental-reports/collections?from=2026-02-02')->assertOk()->assertJsonCount(1, 'data.rows');
    $this->getJson('/api/v1/rental-reports/nope')->assertNotFound();

    $csv = $this->get('/api/v1/rental-reports/collections/export')->assertOk()->streamedContent();
    expect($csv)->toContain("'=cmd")->toContain('5000.00');
});

it('the daily job ends expired leases and repairs property statuses', function () {
    $expiring = activeLease(['end_date' => '2026-03-20']);
    $drifted = activeLease();
    $drifted->property->forceFill(['status' => PropertyStatus::Available])->save();

    $this->travelTo(Carbon::parse('2026-03-21 00:15:00'));
    $this->artisan('rentals:run')->expectsOutputToContain('leases ended: 1, properties marked rented: 1')->assertSuccessful();

    expect($expiring->refresh()->status)->toBe(LeaseStatus::Ended)
        ->and($expiring->ended_on->toDateString())->toBe('2026-03-20')
        ->and($expiring->property->refresh()->status)->toBe(PropertyStatus::Available)
        ->and($drifted->property->refresh()->status)->toBe(PropertyStatus::Rented);
});

it('switched off, the module answers 404, does nothing daily and leaves property statuses free', function () {
    manager();
    $lease = activeLease(['end_date' => '2026-03-15']);
    config(['rentals.enabled' => false]);

    foreach (['tenants', 'leases', 'rent-charges', 'maintenance', 'rental-reports/arrears'] as $path) {
        $this->getJson("/api/v1/{$path}")->assertNotFound();
    }

    $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.overdue_rent');
    $this->travelTo(Carbon::parse('2026-03-20'));
    $this->artisan('rentals:run')->assertSuccessful();
    expect($lease->refresh()->status)->toBe(LeaseStatus::Active);

    $this->postJson("/api/v1/properties/{$lease->property_id}/status", ['status' => 'available'])->assertOk();
    // The records are kept, so the property still cannot be deleted.
    $this->deleteJson("/api/v1/properties/{$lease->property_id}")->assertConflict();
});

it('seeds menus and permissions, and removes them when switched off', function () {
    $this->seed([RolesSeeder::class, RentalsModuleSeeder::class]);
    $this->seed(RentalsModuleSeeder::class);

    expect(Permission::query()->where('name', 'like', 'leases.%')->count())->toBe(4)
        ->and(Menu::query()->where('title', 'Rentals')->count())->toBe(1)
        ->and(Menu::query()->where('permission_name', 'leases.view')->exists())->toBeTrue();

    config(['rentals.enabled' => false]);
    $this->seed(RentalsModuleSeeder::class);

    expect(Permission::query()->where('name', 'like', 'leases.%')->count())->toBe(0)
        ->and(Menu::query()->where('title', 'Rentals')->exists())->toBeFalse()
        ->and(Menu::query()->where('permission_name', 'leases.view')->exists())->toBeFalse();
});

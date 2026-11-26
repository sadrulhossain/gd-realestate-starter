<?php

use App\Models\Property;
use Illuminate\Support\Facades\Http;

it('refuses to seed generated rows into a database that already has properties', function () {
    Property::factory()->create();

    $this->artisan('bench:search', ['--seed' => 5])
        ->expectsOutputToContain('refusing to seed')
        ->assertFailed();

    expect(Property::query()->count())->toBe(1);
});

it('seeds an empty table, times every scenario and fails over the budget', function () {
    $this->seed();
    Http::fake(['*' => Http::response(['meta' => ['total' => 1]])]);

    $this->artisan('bench:search', ['--seed' => 20, '--runs' => 2, '--url' => 'http://api.test/api'])
        ->expectsOutputToContain('every scenario is under 200 ms')
        ->assertSuccessful();

    expect(Property::query()->count())->toBe(20);

    $this->artisan('bench:search', ['--runs' => 1, '--url' => 'http://api.test/api', '--budget' => -1])->assertFailed();
});

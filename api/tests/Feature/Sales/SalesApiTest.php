<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\Menu;
use App\Models\Property;
use App\Models\User;
use App\Models\Viewing;
use App\Notifications\NewLead;
use App\Notifications\ViewingScheduled;
use Database\Seeders\AgentsModuleSeeder;
use Database\Seeders\LeadsModuleSeeder;
use Database\Seeders\ViewingsModuleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

// This API wraps validation errors in `error.details`, so the stock assertion does not apply.
if (! TestResponse::hasMacro('assertDetails')) {
    TestResponse::macro('assertDetails', function (array $keys) {
        /** @var TestResponse $this */
        expect(array_keys($this->json('error.details') ?? []))->toContain(...$keys);

        return $this;
    });
}

beforeEach(function () {
    $this->seed([AgentsModuleSeeder::class, LeadsModuleSeeder::class, ViewingsModuleSeeder::class]);

    $this->captcha = function () {
        $data = $this->getJson('/api/v1/public/captcha')->assertOk()->json('data');
        [$a, $b] = explode(' + ', $data['question']);

        return ['captcha_id' => $data['id'], 'captcha_answer' => (int) $a + (int) $b];
    };
});

/** A user holding the seeded `agent` role, linked to a fresh agent profile. */
function actingAsAgent(): array
{
    $user = User::factory()->create()->assignRole(Role::findByName('agent', 'api'));
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    Passport::actingAs($user, [], 'api');

    return [$user, $agent];
}

function nextWeek(int $hour = 10): string
{
    return now()->addDays(7)->setTime($hour, 0)->toIso8601String();
}

it('requires authentication and permissions on every admin route', function () {
    $agent = Agent::factory()->create();
    $lead = Lead::factory()->create();
    $viewing = Viewing::factory()->create();
    $urls = ['/api/v1/agents', "/api/v1/agents/{$agent->id}", '/api/v1/leads', "/api/v1/leads/{$lead->id}", '/api/v1/viewings', "/api/v1/viewings/{$viewing->id}"];

    foreach ($urls as $url) {
        $this->getJson($url)->assertUnauthorized();
    }

    actingAsRole('nobody');
    foreach ($urls as $url) {
        $this->getJson($url)->assertForbidden();
    }

    $this->postJson('/api/v1/agents', ['name' => 'X'])->assertForbidden();
    $this->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '1'])->assertForbidden();
    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'contacted'])->assertForbidden();
    $this->postJson("/api/v1/leads/{$lead->id}/assign", ['agent_id' => $agent->id])->assertForbidden();
    $this->postJson("/api/v1/viewings/{$viewing->id}/status", ['status' => 'confirmed'])->assertForbidden();
    $this->deleteJson("/api/v1/viewings/{$viewing->id}")->assertForbidden();
});

it('seeds permissions, menus and the agent role idempotently', function () {
    $this->seed([AgentsModuleSeeder::class, LeadsModuleSeeder::class, ViewingsModuleSeeder::class]);

    expect(Menu::query()->whereIn('path', ['/agents', '/leads', '/viewings'])->count())->toBe(3);
    $agent = Role::findByName('agent', 'api');
    expect($agent->hasPermissionTo('leads.view'))->toBeTrue()
        ->and($agent->hasPermissionTo('leads.view_all'))->toBeFalse()
        ->and($agent->hasPermissionTo('viewings.view_all'))->toBeFalse();
});

it('manages agents and refuses to delete one that is in use', function () {
    actingAsRole('manager', ['agents.view', 'agents.create', 'agents.update', 'agents.delete']);
    $user = User::factory()->create();

    $id = $this->postJson('/api/v1/agents', ['name' => 'Amina Rahman', 'phone' => '0171', 'user_id' => $user->id])
        ->assertCreated()->assertJsonPath('data.slug', 'amina-rahman')->json('data.id');
    $this->postJson('/api/v1/agents', ['name' => 'Amina Rahman'])->assertCreated()->assertJsonPath('data.slug', 'amina-rahman-2');

    $this->postJson('/api/v1/agents', ['name' => 'Other', 'user_id' => $user->id])->assertUnprocessable()->assertDetails(['user_id']);
    $this->postJson('/api/v1/agents', ['phone' => '1'])->assertUnprocessable()->assertDetails(['name']);
    $this->patchJson("/api/v1/agents/{$id}", ['title' => 'Senior agent'])->assertOk()->assertJsonPath('data.title', 'Senior agent');

    Property::factory()->create(['agent_id' => $id]);
    $this->deleteJson("/api/v1/agents/{$id}")->assertStatus(409);

    $free = Agent::factory()->create();
    $this->deleteJson("/api/v1/agents/{$free->id}")->assertNoContent();
});

it('rejects a property agent that does not exist', function () {
    actingAsRole('editor', ['properties.view', 'properties.create', 'properties.update']);
    $property = Property::factory()->create();

    $this->patchJson("/api/v1/properties/{$property->id}", ['agent_id' => 9999])->assertUnprocessable()->assertDetails(['agent_id']);
    $this->patchJson("/api/v1/properties/{$property->id}", ['agent_id' => Agent::factory()->create()->id])->assertOk();
});

it('lists only active agents publicly without private fields', function () {
    $live = Agent::factory()->create(['name' => 'Live Agent', 'user_id' => User::factory()->create()->id]);
    Agent::factory()->create(['name' => 'Hidden', 'is_active' => false]);
    Property::factory()->available()->create(['agent_id' => $live->id]);
    Property::factory()->create(['agent_id' => $live->id]); // draft: not counted

    $payload = $this->getJson('/api/v1/public/agents')->assertOk()->assertJsonCount(1, 'data')->json('data.0');
    expect($payload['listings_count'])->toBe(1)->and($payload)->not->toHaveKeys(['user_id', 'is_active', 'created_at']);

    $this->getJson("/api/v1/public/agents/{$live->slug}")->assertOk()->assertJsonPath('data.name', 'Live Agent');
    $this->getJson('/api/v1/public/agents/hidden')->assertNotFound();
});

it('accepts a public lead, routes it to the property agent and notifies by queued mail', function () {
    Notification::fake();
    $agent = Agent::factory()->create(['email' => 'agent@example.com']);
    $property = Property::factory()->available()->create(['agent_id' => $agent->id]);

    $this->postJson('/api/v1/public/leads', ['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Is it free?', 'property_id' => $property->id] + ($this->captcha)())
        ->assertCreated()->assertExactJson(['data' => ['received' => true]]);

    $lead = Lead::query()->firstOrFail();
    expect($lead->agent_id)->toBe($agent->id)->and($lead->source)->toBe('web')->and($lead->status->value)->toBe('new');
    Notification::assertSentOnDemand(NewLead::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'agent@example.com');
    expect(new NewLead($lead))->toBeInstanceOf(ShouldQueue::class);
});

it('validates the public lead: captcha, honeypot, contact method and public property', function () {
    $draft = Property::factory()->create();

    $this->postJson('/api/v1/public/leads', ['name' => 'Jane', 'email' => 'jane@example.com', 'captcha_id' => 'x', 'captcha_answer' => 1])
        ->assertUnprocessable()->assertDetails(['captcha_answer']);
    $this->postJson('/api/v1/public/leads', ['name' => 'Jane', 'email' => 'jane@example.com', 'website' => 'spam'] + ($this->captcha)())
        ->assertUnprocessable()->assertDetails(['website']);
    $this->postJson('/api/v1/public/leads', ['name' => 'Jane'] + ($this->captcha)())->assertUnprocessable()->assertDetails(['email', 'phone']);
    $this->postJson('/api/v1/public/leads', ['name' => 'Jane', 'phone' => '1', 'property_id' => $draft->id] + ($this->captcha)())
        ->assertUnprocessable()->assertDetails(['property_id']);
    expect(Lead::count())->toBe(0);
});

it('throttles public leads per IP', function () {
    config(['contact.per_minute' => 2]);
    $last = null;

    foreach (range(1, 3) as $_) {
        $last = $this->postJson('/api/v1/public/leads', ['name' => 'Jane', 'phone' => '1'] + ($this->captcha)());
    }

    $last->assertStatus(429);
    expect(Lead::count())->toBe(2);
});

it('runs the lead pipeline and writes the timeline', function () {
    actingAsRole('sales', ['leads.view', 'leads.update', 'leads.view_all']);
    $lead = Lead::factory()->create();

    $this->patchJson("/api/v1/leads/{$lead->id}", ['status' => 'won'])->assertUnprocessable()->assertDetails(['status']);
    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'won'])->assertUnprocessable()->assertDetails(['status']);
    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'lost'])->assertUnprocessable()->assertDetails(['lost_reason']);

    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'contacted'])->assertOk()->assertJsonPath('data.status', 'contacted');
    expect($lead->refresh()->last_contacted_at)->not->toBeNull();

    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'lost', 'lost_reason' => 'Bought elsewhere'])
        ->assertOk()->assertJsonPath('data.lost_reason', 'Bought elsewhere');
    $this->postJson("/api/v1/leads/{$lead->id}/notes", ['body' => 'Called back'])->assertOk();
    $this->postJson("/api/v1/leads/{$lead->id}/status", ['status' => 'new'])->assertOk()->assertJsonPath('data.lost_reason', null);

    $timeline = $this->getJson("/api/v1/leads/{$lead->id}")->assertOk()->json('data.notes');
    expect(array_column($timeline, 'kind'))->toBe(['status', 'status', 'note', 'status']);
});

it('assigns a lead, notifies the agent and lets only assigners do it', function () {
    Notification::fake();
    $agent = Agent::factory()->create(['email' => 'a@example.com']);
    $lead = Lead::factory()->create();

    actingAsRole('sales', ['leads.view', 'leads.update', 'leads.view_all']);
    $this->postJson("/api/v1/leads/{$lead->id}/assign", ['agent_id' => $agent->id])->assertForbidden();

    actingAsRole('lead-manager', ['leads.view', 'leads.assign', 'leads.view_all']);
    $this->postJson("/api/v1/leads/{$lead->id}/assign", ['agent_id' => 9999])->assertUnprocessable()->assertDetails(['agent_id']);
    $this->postJson("/api/v1/leads/{$lead->id}/assign", ['agent_id' => $agent->id])->assertOk()->assertJsonPath('data.agent.id', $agent->id);
    Notification::assertSentOnDemand(NewLead::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'a@example.com');

    $this->postJson("/api/v1/leads/{$lead->id}/assign", ['agent_id' => null])->assertOk()->assertJsonPath('data.agent_id', null);
});

it('scopes an agent to their own leads', function () {
    [, $me] = actingAsAgent();
    $mine = Lead::factory()->create(['agent_id' => $me->id]);
    $theirs = Lead::factory()->create(['agent_id' => Agent::factory()->create()->id]);
    $unassigned = Lead::factory()->create();

    $this->getJson('/api/v1/leads')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    $this->getJson("/api/v1/leads/{$mine->id}")->assertOk();
    $this->getJson("/api/v1/leads/{$theirs->id}")->assertForbidden();
    $this->getJson("/api/v1/leads/{$unassigned->id}")->assertForbidden();
    $this->patchJson("/api/v1/leads/{$theirs->id}", ['name' => 'Nope'])->assertForbidden();
    $this->postJson("/api/v1/leads/{$theirs->id}/status", ['status' => 'contacted'])->assertForbidden();
    $this->deleteJson("/api/v1/leads/{$mine->id}")->assertForbidden(); // no leads.delete

    // A lead an agent creates lands on their own profile; they cannot pick another agent.
    $this->postJson('/api/v1/leads', ['name' => 'Walk in', 'phone' => '1', 'source' => 'walk_in'])->assertCreated()->assertJsonPath('data.agent_id', $me->id);
    $this->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '1', 'agent_id' => $theirs->agent_id])->assertUnprocessable()->assertDetails(['agent_id']);
});

it('filters the lead list for people who see everything', function () {
    actingAsRole('sales', ['leads.view', 'leads.view_all']);
    $agent = Agent::factory()->create();
    Lead::factory()->create(['name' => 'Karim Uddin', 'status' => 'contacted', 'agent_id' => $agent->id]);
    Lead::factory()->create(['name' => 'Someone Else']);

    expect($this->getJson('/api/v1/leads')->assertOk()->json('data'))->toHaveCount(2);
    $this->getJson('/api/v1/leads?status=contacted')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/leads?agent_id={$agent->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/leads?q=karim')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/leads?status=bogus')->assertUnprocessable();
});

it('books a viewing, resolves the agent and sends queued mails', function () {
    Notification::fake();
    $agent = Agent::factory()->create(['email' => 'agent@example.com']);
    $property = Property::factory()->available()->create(['agent_id' => $agent->id]);
    $lead = Lead::factory()->create(['email' => 'lead@example.com']);
    actingAsRole('sales', ['viewings.view', 'viewings.create', 'viewings.view_all']);

    $this->postJson('/api/v1/viewings', ['property_id' => $property->id, 'lead_id' => $lead->id, 'starts_at' => nextWeek(), 'ends_at' => nextWeek(11)])
        ->assertCreated()->assertJsonPath('data.agent_id', $agent->id)->assertJsonPath('data.status', 'scheduled');

    Notification::assertSentOnDemand(ViewingScheduled::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'agent@example.com');
    Notification::assertSentOnDemand(ViewingScheduled::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'lead@example.com');

    $noAgent = Property::factory()->available()->create();
    $this->postJson('/api/v1/viewings', ['property_id' => $noAgent->id, 'starts_at' => nextWeek(14), 'ends_at' => nextWeek(15)])
        ->assertUnprocessable()->assertDetails(['agent_id']);
});

it('validates viewing times', function () {
    $property = Property::factory()->available()->create(['agent_id' => Agent::factory()->create()->id]);
    actingAsRole('sales', ['viewings.view', 'viewings.create', 'viewings.view_all']);
    $url = '/api/v1/viewings';

    $this->postJson($url, ['property_id' => $property->id, 'starts_at' => now()->subDay()->toIso8601String(), 'ends_at' => nextWeek()])->assertUnprocessable()->assertDetails(['starts_at']);
    $this->postJson($url, ['property_id' => $property->id, 'starts_at' => nextWeek(11), 'ends_at' => nextWeek(10)])->assertUnprocessable()->assertDetails(['ends_at']);
    $this->postJson($url, ['property_id' => $property->id, 'starts_at' => nextWeek(6), 'ends_at' => now()->addDays(7)->setTime(20, 0)->toIso8601String()])->assertUnprocessable()->assertDetails(['ends_at']);
    $this->postJson($url, ['property_id' => $property->id, 'status' => 'completed', 'starts_at' => nextWeek(), 'ends_at' => nextWeek(11)])->assertUnprocessable()->assertDetails(['status']);
    $this->postJson($url, ['starts_at' => nextWeek(), 'ends_at' => nextWeek(11)])->assertUnprocessable()->assertDetails(['property_id']);
});

it('detects agent conflicts on create and reschedule, and frees the slot when cancelled', function () {
    $agent = Agent::factory()->create();
    $property = Property::factory()->available()->create(['agent_id' => $agent->id]);
    actingAsRole('sales', ['viewings.view', 'viewings.create', 'viewings.update', 'viewings.view_all']);

    $first = $this->postJson('/api/v1/viewings', ['property_id' => $property->id, 'starts_at' => nextWeek(10), 'ends_at' => nextWeek(11)])->assertCreated()->json('data.id');

    // Overlap (starts inside the first one) conflicts; back-to-back does not.
    $overlap = ['property_id' => $property->id, 'starts_at' => now()->addDays(7)->setTime(10, 30)->toIso8601String(), 'ends_at' => nextWeek(12)];
    $this->postJson('/api/v1/viewings', $overlap)->assertUnprocessable()->assertDetails(['starts_at']);
    $second = $this->postJson('/api/v1/viewings', ['property_id' => $property->id, 'starts_at' => nextWeek(11), 'ends_at' => nextWeek(12)])->assertCreated()->json('data.id');

    // Another agent is free at the same time.
    $other = Agent::factory()->create();
    $this->postJson('/api/v1/viewings', ['agent_id' => $other->id] + $overlap)->assertCreated();

    // Rescheduling into the first slot conflicts; rescheduling a viewing onto itself does not.
    $this->patchJson("/api/v1/viewings/{$second}", ['starts_at' => nextWeek(10), 'ends_at' => nextWeek(11)])->assertUnprocessable()->assertDetails(['starts_at']);
    $this->patchJson("/api/v1/viewings/{$first}", ['ends_at' => now()->addDays(7)->setTime(10, 45)->toIso8601String()])->assertOk();

    // Cancelling frees the agent.
    $this->postJson("/api/v1/viewings/{$first}/status", ['status' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->postJson('/api/v1/viewings', ['ends_at' => nextWeek(11)] + $overlap)->assertCreated(); // 10:30-11:00, the 11:00 viewing is untouched
});

it('moves viewings only along allowed transitions', function () {
    actingAsRole('sales', ['viewings.view', 'viewings.update', 'viewings.view_all']);
    $viewing = Viewing::factory()->create();

    $this->postJson("/api/v1/viewings/{$viewing->id}/status", ['status' => 'completed'])->assertUnprocessable()->assertDetails(['status']);
    $this->postJson("/api/v1/viewings/{$viewing->id}/status", ['status' => 'confirmed'])->assertOk();
    $this->postJson("/api/v1/viewings/{$viewing->id}/status", ['status' => 'completed'])->assertOk();
    $this->postJson("/api/v1/viewings/{$viewing->id}/status", ['status' => 'cancelled'])->assertUnprocessable();
    $this->patchJson("/api/v1/viewings/{$viewing->id}", ['status' => 'scheduled'])->assertUnprocessable()->assertDetails(['status']);
});

it('scopes an agent to their own viewings and forces their own agent profile', function () {
    [, $me] = actingAsAgent();
    $property = Property::factory()->available()->create();
    $mine = Viewing::factory()->create(['agent_id' => $me->id]);
    $theirs = Viewing::factory()->create();

    $this->getJson('/api/v1/viewings')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    $this->getJson("/api/v1/viewings/{$theirs->id}")->assertForbidden();
    $this->patchJson("/api/v1/viewings/{$theirs->id}", ['notes' => 'x'])->assertForbidden();
    $this->postJson("/api/v1/viewings/{$theirs->id}/status", ['status' => 'confirmed'])->assertForbidden();

    $this->postJson('/api/v1/viewings', ['property_id' => $property->id, 'agent_id' => $theirs->agent_id, 'starts_at' => nextWeek(), 'ends_at' => nextWeek(11)])
        ->assertUnprocessable()->assertDetails(['agent_id']);
    $this->postJson('/api/v1/viewings', ['property_id' => $property->id, 'starts_at' => nextWeek(), 'ends_at' => nextWeek(11)])
        ->assertCreated()->assertJsonPath('data.agent_id', $me->id);
});

it('filters the agenda by date range, agent and status', function () {
    actingAsRole('sales', ['viewings.view', 'viewings.view_all']);
    $agent = Agent::factory()->create();
    Viewing::factory()->create(['agent_id' => $agent->id, 'starts_at' => '2030-01-10 10:00:00', 'ends_at' => '2030-01-10 11:00:00']);
    Viewing::factory()->create(['starts_at' => '2030-01-20 10:00:00', 'ends_at' => '2030-01-20 11:00:00']);

    $this->getJson('/api/v1/viewings?from=2030-01-10&to=2030-01-10')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/viewings?from=2030-01-01&to=2030-01-31')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/viewings?agent_id={$agent->id}")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/viewings?status=cancelled')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/viewings?from=2030-02-01&to=2030-01-01')->assertUnprocessable();
});

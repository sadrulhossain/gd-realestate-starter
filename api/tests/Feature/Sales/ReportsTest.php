<?php

use App\Models\Agent;
use App\Models\Lead;
use App\Models\Menu;
use App\Models\Property;
use App\Models\User;
use App\Models\Viewing;
use Database\Seeders\AgentsModuleSeeder;
use Database\Seeders\LeadsModuleSeeder;
use Database\Seeders\ReportsModuleSeeder;
use Database\Seeders\ViewingsModuleSeeder;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([AgentsModuleSeeder::class, LeadsModuleSeeder::class, ViewingsModuleSeeder::class, ReportsModuleSeeder::class]);
});

/** @return array<string, array<string, mixed>> rows of a report keyed by the first cell */
function reportRows(array $data): array
{
    return collect($data['rows'])->mapWithKeys(fn ($row) => [$row[0] => array_combine($data['columns'], $row)])->all();
}

it('requires authentication and reports.view', function () {
    $this->getJson('/api/v1/reports/leads')->assertUnauthorized();
    $this->getJson('/api/v1/reports/leads/export')->assertUnauthorized();

    actingAsRole('nobody');
    $this->getJson('/api/v1/reports/leads')->assertForbidden();
    $this->getJson('/api/v1/reports/viewings/export')->assertForbidden();
});

it('keeps reports away from the agent role and seeds the menu idempotently', function () {
    $this->seed(ReportsModuleSeeder::class);

    expect(Role::findByName('agent', 'api')->hasPermissionTo('reports.view'))->toBeFalse()
        ->and(Role::findByName('admin', 'api')->hasPermissionTo('reports.view'))->toBeTrue()
        ->and(Menu::query()->where('permission_name', 'reports.view')->count())->toBe(1);
});

it('answers 404 for an unknown report', function () {
    actingAsRole('analyst', ['reports.view']);

    $this->getJson('/api/v1/reports/bogus')->assertNotFound();
    $this->getJson('/api/v1/reports/bogus/export')->assertNotFound();
});

it('counts leads per agent and status with a won share and a total row', function () {
    actingAsRole('analyst', ['reports.view']);
    $amina = Agent::factory()->create(['name' => 'Amina']);
    $karim = Agent::factory()->create(['name' => 'Karim']);
    Lead::factory()->count(2)->create(['agent_id' => $amina->id, 'status' => 'won']);
    Lead::factory()->count(2)->create(['agent_id' => $amina->id, 'status' => 'new']);
    Lead::factory()->create(['agent_id' => $karim->id, 'status' => 'lost']);
    Lead::factory()->create(['status' => 'new']);

    $data = $this->getJson('/api/v1/reports/leads')->assertOk()->json('data');
    $rows = reportRows($data);

    expect($data['columns'])->toBe(['Agent', 'Leads', 'New', 'Contacted', 'Qualified', 'Viewing', 'Negotiation', 'Won', 'Lost', 'Won %'])
        ->and($rows['Amina'])->toMatchArray(['Leads' => 4, 'New' => 2, 'Won' => 2, 'Won %' => 50])
        ->and($rows['Karim'])->toMatchArray(['Leads' => 1, 'Lost' => 1, 'Won %' => 0])
        ->and($rows['Unassigned'])->toMatchArray(['Leads' => 1, 'New' => 1])
        ->and($rows['Total'])->toMatchArray(['Leads' => 6, 'New' => 3, 'Won' => 2, 'Lost' => 1])
        ->and(array_key_last($rows))->toBe('Total')
        ->and(array_keys($rows)[count($rows) - 2])->toBe('Unassigned');
});

it('limits leads and viewings by date range and validates it', function () {
    actingAsRole('analyst', ['reports.view']);
    $agent = Agent::factory()->create(['name' => 'Amina']);
    Lead::factory()->create(['agent_id' => $agent->id, 'created_at' => '2030-01-10 12:00:00']);
    Lead::factory()->create(['agent_id' => $agent->id, 'created_at' => '2030-02-10 12:00:00']);
    Viewing::factory()->create(['agent_id' => $agent->id, 'starts_at' => '2030-01-10 10:00:00', 'ends_at' => '2030-01-10 11:00:00']);
    Viewing::factory()->create(['agent_id' => $agent->id, 'starts_at' => '2030-03-10 10:00:00', 'ends_at' => '2030-03-10 11:00:00']);

    $total = fn (string $url) => reportRows($this->getJson($url)->assertOk()->json('data'))['Total'];

    expect($total('/api/v1/reports/leads')['Leads'])->toBe(2)
        ->and($total('/api/v1/reports/leads?from=2030-01-10&to=2030-01-10')['Leads'])->toBe(1)
        ->and($total('/api/v1/reports/leads?from=2030-02-01')['Leads'])->toBe(1)
        ->and($total('/api/v1/reports/viewings')['Viewings'])->toBe(2)
        ->and($total('/api/v1/reports/viewings?to=2030-01-31')['Viewings'])->toBe(1)
        ->and($total('/api/v1/reports/viewings?to=2030-01-31')['Scheduled'])->toBe(1);

    $this->getJson('/api/v1/reports/leads?from=2030-02-01&to=2030-01-01')->assertUnprocessable();
    $this->getJson('/api/v1/reports/leads?from=yesterday')->assertUnprocessable();
});

it('reports properties by status and listing type', function () {
    actingAsRole('analyst', ['reports.view']);
    Property::factory()->count(2)->available()->create();
    Property::factory()->available()->rent()->create();
    Property::factory()->create();

    $rows = reportRows($this->getJson('/api/v1/reports/properties')->assertOk()->json('data'));

    expect($rows['Available'])->toMatchArray(['For sale' => 2, 'For rent' => 1, 'Total' => 3])
        ->and($rows['Draft']['Total'])->toBe(1)
        ->and($rows['Sold']['Total'])->toBe(0)
        ->and($rows['Total']['Total'])->toBe(4);
});

it('exports the same table as CSV and neutralises spreadsheet formulas', function () {
    actingAsRole('analyst', ['reports.view']);
    $agent = Agent::factory()->create(['name' => '=HYPERLINK("http://evil.test")']);
    Lead::factory()->create(['agent_id' => $agent->id, 'status' => 'won']);

    $response = $this->get('/api/v1/reports/leads/export')->assertOk();
    $csv = $response->streamedContent();
    $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('leads-report-'.now()->format('Y-m-d').'.csv')
        ->and($lines[0])->toBe(['Agent', 'Leads', 'New', 'Contacted', 'Qualified', 'Viewing', 'Negotiation', 'Won', 'Lost', 'Won %'])
        ->and($lines[1][0])->toStartWith("'=")
        ->and($lines[1][7])->toBe('1')
        ->and(end($lines)[0])->toBe('Total');
});

it('counts new leads and upcoming viewings on the dashboard within the own-records scope', function () {
    $user = User::factory()->create()->assignRole(Role::findByName('agent', 'api'));
    $me = Agent::factory()->create(['user_id' => $user->id]);
    Passport::actingAs($user, [], 'api');
    Role::findByName('agent', 'api')->givePermissionTo(Permission::findOrCreate('dashboard.view', 'api'));

    Lead::factory()->create(['agent_id' => $me->id]);
    Lead::factory()->create(['agent_id' => $me->id, 'status' => 'contacted']);
    Lead::factory()->create(); // somebody else's
    Viewing::factory()->create(['agent_id' => $me->id]);
    Viewing::factory()->create(['agent_id' => $me->id, 'status' => 'cancelled']);
    Viewing::factory()->create(); // somebody else's

    $this->getJson('/api/v1/dashboard')->assertOk()
        ->assertJsonPath('data.new_leads', 1)
        ->assertJsonPath('data.upcoming_viewings', 1)
        ->assertJsonMissingPath('data.properties');
});

<?php

namespace App\Console\Commands;

use App\Models\Amenity;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyType;
use App\Services\PropertySearch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Release check for the public property list: times each search over HTTP and prints what PostgreSQL does for it
 * (EXPLAIN ANALYZE). `--seed=10000` fills an EMPTY properties table first, so use a throwaway database
 * (scripts/bench-search.sh). Exits 1 when a scenario's 95th percentile is over the budget.
 */
class BenchSearch extends Command
{
    protected $signature = 'bench:search
        {--seed=0 : Insert this many generated properties first (refused unless the table is empty)}
        {--url=http://nginx:8080/api : API base URL to time}
        {--runs=20 : Timed requests per scenario}
        {--budget=200 : Allowed 95th percentile in milliseconds}
        {--plans : Print the full EXPLAIN ANALYZE plans}';

    protected $description = 'Time the public property search and EXPLAIN its queries';

    public function handle(): int
    {
        $seed = (int) $this->option('seed');

        if ($seed > 0 && ! $this->seed($seed)) {
            return self::FAILURE;
        }

        $total = Property::query()->public()->count();

        if ($total === 0) {
            $this->error('no public properties: run with --seed=10000 on a throwaway database');

            return self::FAILURE;
        }

        $this->info(sprintf('%d properties, %d public', Property::query()->count(), $total));

        $slow = 0;
        $rows = [];
        $plans = [];

        foreach ($this->scenarios() as $name => $path) {
            [$median, $p95, $found] = $this->time($path);
            [$sql, $scans, $text] = $this->explain($path);
            $slow += $p95 > (float) $this->option('budget') ? 1 : 0;
            $rows[] = [$name, $found, $median, $p95, $sql, implode(', ', $scans)];
            $plans[$name] = $text;
        }

        $this->table(['scenario', 'matches', 'median ms', 'p95 ms', 'slowest query ms', 'scans on properties'], $rows);

        if ($this->option('plans')) {
            foreach ($plans as $name => $text) {
                $this->line("== {$name}\n{$text}");
            }
        }

        $budget = $this->option('budget');
        $slow === 0 ? $this->info("every scenario is under {$budget} ms (p95)") : $this->error("{$slow} scenario(s) over {$budget} ms (p95)");

        return $slow === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, string> name => path with query string, below the API base URL */
    private function scenarios(): array
    {
        $city = Location::query()->whereNotNull('parent_id')->orderBy('id')->value('id');
        $country = Location::query()->whereNull('parent_id')->orderBy('id')->value('id');
        $amenities = Amenity::query()->orderBy('id')->limit(2)->pluck('id')->implode(',');
        $slug = Property::query()->public()->orderByDesc('id')->value('slug');

        $queries = [
            'default (newest)' => [],
            'page 100' => ['page' => 100],
            '48 per page' => ['per_page' => 48],
            'filters' => ['listing_type' => 'sale', 'property_type' => 'apartment', 'location' => $city, 'price_min' => 2_000_000, 'price_max' => 60_000_000, 'beds_min' => 2],
            'whole country' => ['location' => $country],
            'price, low first' => ['sort' => 'price_asc'],
            'area, high first' => ['sort' => 'area_desc'],
            'featured' => ['featured' => 1],
            'two amenities' => ['amenities' => $amenities],
            'keyword' => ['q' => 'lakeview apartment'],
            'keyword with typo' => ['q' => 'lakeveiw'],
            'radius 3 km' => ['lat' => 23.78, 'lng' => 90.40, 'radius_km' => 3],
        ];

        $paths = array_map(fn (array $q): string => '/v1/public/properties'.($q === [] ? '' : '?'.http_build_query($q)), $queries);

        return [...$paths, 'detail by slug' => '/v1/public/properties/'.$slug];
    }

    /** @return array{float, float, int|string} median ms, p95 ms, matches */
    private function time(string $path): array
    {
        $url = rtrim((string) $this->option('url'), '/').$path;
        $times = [];
        $found = '-';

        // The first request is a warm-up and is not counted.
        for ($i = 0; $i <= max(1, (int) $this->option('runs')); $i++) {
            $started = hrtime(true);
            $response = Http::acceptJson()->timeout(30)->get($url)->throw();

            if ($i > 0) {
                $times[] = (hrtime(true) - $started) / 1e6;
            }

            $found = $response->json('meta.total', '-');
        }

        sort($times);
        $at = fn (float $p): float => round($times[(int) ceil($p * count($times)) - 1], 1);

        return [$at(0.5), $at(0.95), $found];
    }

    /** @return array{float, list<string>, string} slowest query in ms, scan nodes on properties, plans as text */
    private function explain(string $path): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(Kernel::class)->handle(Request::create('/api'.$path, 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $slowest = 0.0;
        $scans = [];
        $text = '';

        foreach ($queries as $query) {
            if (! str_starts_with($query['query'], 'select') || ! str_contains($query['query'], 'from "properties"')) {
                continue;
            }

            // The typo query runs with its own planner setting (PropertySearch), so explain it the same way.
            DB::beginTransaction();

            if (str_contains($query['query'], '<% title')) {
                DB::statement(PropertySearch::USE_TRIGRAM_INDEX);
            }

            $lines = array_map(
                fn (object $row): string => (string) $row->{'QUERY PLAN'},
                DB::select('explain (analyze, buffers) '.$query['query'], $query['bindings']),
            );
            DB::rollBack();
            $plan = implode("\n", $lines);

            if (preg_match('/Execution Time: ([\d.]+) ms/', $plan, $m)) {
                $slowest = max($slowest, (float) $m[1]);
            }

            preg_match_all('/(Seq Scan on properties|Index (?:Only )?Scan(?: Backward)? using \w+ on properties|Bitmap Index Scan on properties_\w+)/', $plan, $found);
            $scans = array_merge($scans, $found[1]);
            $text .= $query['query']."\n".$plan."\n\n";
        }

        $short = array_map(fn (string $s): string => preg_replace('/^.*(?:using|Index Scan on) (\w+).*$/', '$1', $s) ?? $s, $scans);

        return [round($slowest, 1), array_values(array_unique($short)), $text];
    }

    /** Set-based inserts: 10,000 rows take seconds, factories would take minutes. */
    private function seed(int $count): bool
    {
        if (Property::query()->exists()) {
            $this->error('refusing to seed: the properties table is not empty');

            return false;
        }

        $country = Location::query()->firstOrCreate(['slug' => 'bench-country', 'parent_id' => null], ['name' => 'Bench Country']);

        foreach (range(1, 5) as $c) {
            $city = Location::query()->firstOrCreate(['slug' => "bench-city-{$c}", 'parent_id' => $country->id], ['name' => "Bench City {$c}"]);

            foreach (range(1, 10) as $a) {
                Location::query()->firstOrCreate(['slug' => "bench-area-{$a}", 'parent_id' => $city->id], ['name' => "Bench Area {$c}-{$a}"]);
            }
        }

        if (! PropertyType::query()->exists() || ! Amenity::query()->exists()) {
            $this->error('run the module seeders first: php artisan db:seed --force');

            return false;
        }

        // Same rows on every run.
        DB::select('select setseed(0.42)');

        DB::insert(<<<'SQL'
            with t as (select array_agg(id order by id) ids, array_agg(lower(name) order by id) names from property_types),
                 l as (select array_agg(id order by id) ids, array_agg(name order by id) names from locations a
                       where not exists (select 1 from locations b where b.parent_id = a.id)),
                 w as (select array['Lakeview','Garden','Sunny','Corner','Modern','Quiet','Central','Riverside','Hillside','Classic'] words),
                 g as (select i, 1 + floor(random() * cardinality(t.ids))::int ti, 1 + floor(random() * cardinality(l.ids))::int li from generate_series(1, ?::int) i, t, l)
            insert into properties (ref_code, slug, title, description, listing_type, property_type_id, location_id, address_line,
                lat, lng, price, rent_period, area, beds, baths, furnished, status, featured, published_at, created_at, updated_at)
            select 'B-' || lpad(i::text, 6, '0'), 'bench-' || i,
                w.words[1 + i % 10] || ' ' || t.names[ti] || ' in ' || l.names[li],
                w.words[1 + (i / 10) % 10] || ' home with ' || w.words[1 + (i / 100) % 10] || ' surroundings, close to schools, shops and transport. ' || repeat('Well maintained building. ', 5),
                case when i % 3 = 0 then 'rent' else 'sale' end, t.ids[ti], l.ids[li], 'Road ' || (i % 90 + 1) || ', ' || l.names[li],
                23.65 + random() * 0.3, 90.30 + random() * 0.25,
                (500000 + floor(random() * 90000000))::bigint, case when i % 3 = 0 then 'month' end,
                400 + floor(random() * 3600), 1 + i % 5, 1 + i % 4,
                (array['unfurnished','semi','furnished'])[1 + i % 3],
                case when i % 20 = 0 then 'draft' when i % 10 = 0 then 'under_offer' when i % 31 = 0 then 'sold' else 'available' end,
                i % 25 = 0, now() - (i || ' minutes')::interval, now(), now()
            from g, t, l, w
        SQL, [$count]);

        DB::insert(<<<'SQL'
            insert into property_amenities (property_id, amenity_id)
            select p.id, a.id from properties p cross join amenities a where random() < 0.3
        SQL);

        DB::insert(<<<'SQL'
            insert into property_media (property_id, kind, path, sort, is_cover, created_at, updated_at)
            select id, 'image', 'properties/bench/original.jpg', 0, true, now(), now() from properties
        SQL);

        DB::statement('analyze');
        $this->info("seeded {$count} properties");

        return true;
    }
}

<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The sales reports behind `GET reports/{report}` and its CSV export. Every report is a table:
 * `columns` plus `rows` of scalars, so the screen and the CSV share one shape.
 */
class SalesReports
{
    public const KEYS = ['leads', 'viewings', 'properties'];

    private const LEAD_STATUSES = ['new', 'contacted', 'qualified', 'viewing', 'negotiation', 'won', 'lost'];

    private const VIEWING_STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];

    /**
     * @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    public function build(string $key, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return match ($key) {
            'leads' => $this->leads($from, $to),
            'viewings' => $this->viewings($from, $to),
            'properties' => $this->properties(),
            default => throw new InvalidArgumentException("Unknown report {$key}"),
        };
    }

    /**
     * Leads created in the range, per agent and status, with the share that was won.
     *
     * @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    private function leads(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $counts = DB::table('leads')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->selectRaw('agent_id, status, count(*) as total')->groupBy('agent_id', 'status')->get();

        $rows = $this->perAgent($counts, self::LEAD_STATUSES, fn (array $c, int $total) => [$total > 0 ? round(($c['won'] ?? 0) / $total * 100, 1) : 0.0]);

        return [
            'key' => 'leads',
            'title' => 'Leads by agent',
            'columns' => ['Agent', 'Leads', ...array_map($this->label(...), self::LEAD_STATUSES), 'Won %'],
            'rows' => $rows,
        ];
    }

    /**
     * Viewings starting in the range, per agent and status.
     *
     * @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    private function viewings(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $counts = DB::table('viewings')
            ->when($from, fn ($q) => $q->where('starts_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('starts_at', '<=', $to->copy()->endOfDay()))
            ->selectRaw('agent_id, status, count(*) as total')->groupBy('agent_id', 'status')->get();

        return [
            'key' => 'viewings',
            'title' => 'Viewings by agent',
            'columns' => ['Agent', 'Viewings', ...array_map($this->label(...), self::VIEWING_STATUSES)],
            'rows' => $this->perAgent($counts, self::VIEWING_STATUSES, fn () => []),
        ];
    }

    /**
     * Every property by status and listing type (the date range does not apply).
     *
     * @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    private function properties(): array
    {
        $counts = DB::table('properties')->selectRaw('status, listing_type, count(*) as total')->groupBy('status', 'listing_type')->get();
        $by = [];

        foreach ($counts as $row) {
            $by[$row->status][$row->listing_type] = (int) $row->total;
        }

        $rows = [];
        $sale = $rent = 0;

        foreach (['draft', 'available', 'under_offer', 'sold', 'rented', 'archived'] as $status) {
            $s = $by[$status]['sale'] ?? 0;
            $r = $by[$status]['rent'] ?? 0;
            $rows[] = [$this->label($status), $s, $r, $s + $r];
            $sale += $s;
            $rent += $r;
        }

        $rows[] = ['Total', $sale, $rent, $sale + $rent];

        return ['key' => 'properties', 'title' => 'Properties by status', 'columns' => ['Status', 'For sale', 'For rent', 'Total'], 'rows' => $rows];
    }

    /**
     * One row per agent (unassigned last) and a total row. `$extra` adds trailing cells from a row's counts and total.
     *
     * @param  iterable<object{agent_id: int|null, status: string, total: int|string}>  $counts
     * @param  list<string>  $statuses
     * @param  callable(array<string, int>, int): list<float|int>  $extra
     * @return list<list<string|int|float>>
     */
    private function perAgent(iterable $counts, array $statuses, callable $extra): array
    {
        $grid = [];

        foreach ($counts as $row) {
            $grid[$row->agent_id ?? 0][$row->status] = (int) $row->total;
        }

        $names = DB::table('agents')->whereIn('id', array_keys($grid))->pluck('name', 'id');
        $ids = array_keys($grid);
        usort($ids, fn ($a, $b) => $a === 0 ? 1 : ($b === 0 ? -1 : strcasecmp((string) $names[$a], (string) $names[$b])));

        $rows = [];
        $sum = array_fill_keys($statuses, 0);

        foreach ($ids as $id) {
            $c = $grid[$id];
            $total = array_sum($c);
            $rows[] = [$id === 0 ? 'Unassigned' : (string) $names[$id], $total, ...array_map(fn ($s) => $c[$s] ?? 0, $statuses), ...$extra($c, $total)];

            foreach ($statuses as $s) {
                $sum[$s] += $c[$s] ?? 0;
            }
        }

        $all = array_sum($sum);
        $rows[] = ['Total', $all, ...array_values($sum), ...$extra($sum, $all)];

        return $rows;
    }

    private function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SalesReports;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Show a sales report
     *
     * `report` is `leads` (per agent and status, by creation date), `viewings` (per agent and status, by start time)
     * or `properties` (by status and listing type; the dates do not apply). Optional `from` and `to` (dates, inclusive).
     * Needs `reports.view`. Counts cover every record, not just your own.
     */
    public function show(Request $request, string $report, SalesReports $reports): JsonResponse
    {
        abort_unless(in_array($report, SalesReports::KEYS, true), 404);
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $reports->build($report, $from, $to)]);
    }

    /**
     * Download a sales report as CSV
     *
     * The same table as `reports/{report}`, one header row then the data rows. Cells that could be read as a
     * spreadsheet formula are prefixed with an apostrophe.
     */
    public function export(Request $request, string $report, SalesReports $reports): StreamedResponse
    {
        abort_unless(in_array($report, SalesReports::KEYS, true), 404);
        [$from, $to] = $this->range($request);
        $table = $reports->build($report, $from, $to);

        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'w');

            foreach ([$table['columns'], ...$table['rows']] as $row) {
                fputcsv($out, array_map($this->safe(...), $row), ',', '"', '');
            }

            fclose($out);
        }, "{$report}-report-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function range(Request $request): array
    {
        $f = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return [
            filled($f['from'] ?? null) ? CarbonImmutable::parse($f['from']) : null,
            filled($f['to'] ?? null) ? CarbonImmutable::parse($f['to']) : null,
        ];
    }

    /** Stops a spreadsheet from running a cell as a formula (names are typed by people). */
    private function safe(mixed $cell): mixed
    {
        return is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }
}

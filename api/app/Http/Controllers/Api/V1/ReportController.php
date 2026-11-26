<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ExportsReports;
use App\Http\Controllers\Controller;
use App\Services\SalesReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsReports;

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

        return $this->csv($report, $reports->build($report, $from, $to));
    }
}

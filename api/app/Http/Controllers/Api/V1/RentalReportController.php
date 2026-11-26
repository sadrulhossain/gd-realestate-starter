<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ExportsReports;
use App\Http\Controllers\Controller;
use App\Services\RentalReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalReportController extends Controller
{
    use ExportsReports;

    /**
     * Show a rental report
     *
     * `report` is `rent-roll` (active leases and what they owe), `arrears` (every overdue charge) or `collections`
     * (payments between `from` and `to`). Money is in major units. Needs `rental_reports.view`.
     */
    public function show(Request $request, string $report, RentalReports $reports): JsonResponse
    {
        abort_unless(in_array($report, RentalReports::KEYS, true), 404);
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $reports->build($report, $from, $to)]);
    }

    /** Download a rental report as CSV */
    public function export(Request $request, string $report, RentalReports $reports): StreamedResponse
    {
        abort_unless(in_array($report, RentalReports::KEYS, true), 404);
        [$from, $to] = $this->range($request);

        return $this->csv($report, $reports->build($report, $from, $to));
    }
}

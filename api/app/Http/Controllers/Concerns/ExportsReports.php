<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Date range input and CSV download shared by the report controllers. */
trait ExportsReports
{
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

    /**
     * One header row, then the data rows.
     *
     * @param  array{columns: list<string>, rows: list<list<string|int|float>>}  $table
     */
    private function csv(string $report, array $table): StreamedResponse
    {
        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'w');

            foreach ([$table['columns'], ...$table['rows']] as $row) {
                fputcsv($out, array_map($this->safe(...), $row), ',', '"', '');
            }

            fclose($out);
        }, "{$report}-report-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a spreadsheet from running a cell as a formula (names are typed by people). */
    private function safe(mixed $cell): mixed
    {
        return is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }
}

<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Setting;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/** The rental reports, in the same table shape as SalesReports. Money cells are major units of the site currency. */
class RentalReports
{
    public const KEYS = ['rent-roll', 'arrears', 'collections'];

    private const NO_DECIMALS = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    private const THREE_DECIMALS = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    private int $digits = 2;

    /**
     * @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    public function build(string $key, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $currency = Setting::query()->value('currency');
        $this->digits = match (true) {
            in_array($currency, self::NO_DECIMALS, true) => 0,
            in_array($currency, self::THREE_DECIMALS, true) => 3,
            default => 2,
        };

        return match ($key) {
            'rent-roll' => $this->rentRoll(),
            'arrears' => $this->arrears(),
            'collections' => $this->collections($from, $to),
            default => throw new InvalidArgumentException("Unknown report {$key}"),
        };
    }

    /** @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>} */
    private function rentRoll(): array
    {
        $leases = Lease::query()->where('status', 'active')->with(['property:id,ref_code,title', 'tenant:id,name'])
            ->orderBy('end_date')->orderBy('id')->get();

        // Overdue includes charges of finished leases only in the arrears report; here it is the running lease's own.
        $owed = RentCharge::query()->overdue()->whereIn('lease_id', $leases->modelKeys())
            ->selectRaw('lease_id, sum(amount - paid_amount) as owed')->groupBy('lease_id')->pluck('owed', 'lease_id');

        $rows = [];

        foreach ($leases as $lease) {
            $rows[] = [
                $lease->property->ref_code.' '.$lease->property->title, $lease->tenant->name, $this->money($lease->rent_amount),
                ucfirst($lease->rent_period), $lease->end_date->toDateString(), $this->money((int) ($owed[$lease->id] ?? 0)),
            ];
        }

        $rows[] = ['Total', '', '', '', '', $this->money((int) $owed->sum())];

        return ['key' => 'rent-roll', 'title' => 'Rent roll', 'columns' => ['Property', 'Tenant', 'Rent', 'Per', 'Lease ends', 'Overdue'], 'rows' => $rows];
    }

    /** @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>} */
    private function arrears(): array
    {
        $charges = RentCharge::query()->overdue()->with(['lease.property:id,ref_code,title', 'lease.tenant:id,name,phone'])
            ->orderBy('due_date')->orderBy('id')->get();

        $rows = [];
        $total = 0;

        foreach ($charges as $charge) {
            $owed = $charge->amount - $charge->paid_amount;
            $total += $owed;
            $rows[] = [
                $charge->lease->tenant->name, (string) $charge->lease->tenant->phone,
                $charge->lease->property->ref_code.' '.$charge->lease->property->title,
                $charge->due_date->toDateString(), (int) $charge->due_date->diffInDays(today()), $this->money($owed),
            ];
        }

        $rows[] = ['Total', '', '', '', '', $this->money($total)];

        return ['key' => 'arrears', 'title' => 'Arrears', 'columns' => ['Tenant', 'Phone', 'Property', 'Due', 'Days late', 'Owed'], 'rows' => $rows];
    }

    /** @return array{key: string, title: string, columns: list<string>, rows: list<list<string|int|float>>} */
    private function collections(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $payments = RentPayment::query()->with(['charge.lease.property:id,ref_code,title', 'charge.lease.tenant:id,name'])
            ->when($from, fn ($q) => $q->where('paid_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('paid_on', '<=', $to->toDateString()))
            ->orderBy('paid_on')->orderBy('id')->get();

        $rows = [];

        foreach ($payments as $payment) {
            $lease = $payment->charge->lease;
            $rows[] = [
                $payment->paid_on->toDateString(), $lease->tenant->name, $lease->property->ref_code.' '.$lease->property->title,
                ucfirst($payment->method), (string) $payment->reference, $this->money($payment->amount),
            ];
        }

        $rows[] = ['Total', '', '', '', '', $this->money((int) $payments->sum('amount'))];

        return ['key' => 'collections', 'title' => 'Rent collected', 'columns' => ['Paid on', 'Tenant', 'Property', 'Method', 'Reference', 'Amount'], 'rows' => $rows];
    }

    private function money(int $minor): string
    {
        return number_format($minor / 10 ** $this->digits, $this->digits, '.', '');
    }
}

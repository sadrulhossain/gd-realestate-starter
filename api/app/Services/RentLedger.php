<?php

namespace App\Services;

use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The only writer of rent payments: keeps each charge's `paid_amount` and `status` in step with them. */
class RentLedger
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the charge is void or the amount is more than what is owed
     */
    public function pay(RentCharge $charge, array $data, ?User $actor = null): RentPayment
    {
        return DB::transaction(function () use ($charge, $data, $actor): RentPayment {
            $charge = RentCharge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($charge->status === 'void') {
                throw ValidationException::withMessages(['amount' => ['This charge is void.']]);
            }

            if ($data['amount'] > $charge->amount - $charge->paid_amount) {
                throw ValidationException::withMessages(['amount' => ['The amount is more than what is still owed.']]);
            }

            $payment = $charge->payments()->create([...$data, 'user_id' => $actor?->id]);
            $this->settle($charge);

            return $payment;
        });
    }

    /** Remove a payment entered by mistake. */
    public function reverse(RentPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $charge = RentCharge::query()->lockForUpdate()->findOrFail($payment->rent_charge_id);
            $payment->delete();
            $this->settle($charge);
        });
    }

    private function settle(RentCharge $charge): void
    {
        $paid = (int) $charge->payments()->sum('amount');

        $charge->forceFill([
            'paid_amount' => $paid,
            // A void charge takes no payments, so it only gets here by reversing one: it stays void.
            'status' => match (true) {
                $charge->status === 'void' => 'void',
                $paid >= $charge->amount => 'paid',
                $paid > 0 => 'partial',
                default => 'pending',
            },
        ])->save();
    }
}

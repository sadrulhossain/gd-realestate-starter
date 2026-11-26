<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $paid_on */
#[Fillable(['user_id', 'amount', 'paid_on', 'method', 'reference'])]
class RentPayment extends Model
{
    public const METHODS = ['cash', 'bank', 'cheque', 'online', 'other'];

    /** @return BelongsTo<RentCharge, $this> */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class, 'rent_charge_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['paid_on' => 'date:Y-m-d', 'amount' => 'integer', 'rent_charge_id' => 'integer'];
    }
}

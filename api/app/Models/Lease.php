<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A tenancy of one property. `status` and `ended_on` are not fillable: they move through LeaseService.
 * Amounts are in minor units.
 *
 * @property LeaseStatus $status
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $ended_on
 */
#[Fillable(['property_id', 'tenant_id', 'start_date', 'end_date', 'rent_amount', 'rent_period', 'deposit_amount', 'notes'])]
class Lease extends Model
{
    public const RENT_PERIODS = ['month', 'year'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'rent_period' => 'month', 'deposit_amount' => 0];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<RentCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(RentCharge::class)->orderBy('period_start');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LeaseStatus::class,
            'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'ended_on' => 'date:Y-m-d',
            'property_id' => 'integer', 'tenant_id' => 'integer', 'rent_amount' => 'integer', 'deposit_amount' => 'integer',
        ];
    }
}

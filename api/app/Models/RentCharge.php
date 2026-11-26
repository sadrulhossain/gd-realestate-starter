<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One period of a lease's rent schedule. `paid_amount` and `status` follow the payments (RentLedger).
 *
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $due_date
 */
#[Fillable(['period_start', 'period_end', 'due_date', 'amount', 'note'])]
class RentCharge extends Model
{
    public const STATUSES = ['pending', 'partial', 'paid', 'void'];

    /** Statuses that still owe money. */
    public const OPEN = ['pending', 'partial'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'paid_amount' => 0];

    /**
     * Past the due date and not fully paid.
     *
     * @param  Builder<RentCharge>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', self::OPEN)->where('due_date', '<', today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->due_date->lt(today());
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return HasMany<RentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d',
            'amount' => 'integer', 'paid_amount' => 'integer',
        ];
    }
}

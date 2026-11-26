<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A repair or upkeep job on a property. `status` is not fillable (MaintenanceStatus transitions).
 *
 * @property MaintenanceStatus $status
 * @property Carbon|null $resolved_at
 */
#[Fillable(['property_id', 'lease_id', 'title', 'description', 'priority', 'cost'])]
class MaintenanceRequest extends Model
{
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => MaintenanceStatus::class, 'cost' => 'integer', 'resolved_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\ViewingStatus;
use Database\Factories\ViewingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A property viewing. `status` is not fillable (ViewingStatus transitions); times are checked
 * for agent conflicts by ViewingScheduler.
 *
 * @property ViewingStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['property_id', 'lead_id', 'agent_id', 'starts_at', 'ends_at', 'notes'])]
class Viewing extends Model
{
    /** @use HasFactory<ViewingFactory> */
    use HasFactory;

    /**
     * Viewings a user may see: everything with `viewings.view_all`, otherwise only the linked agent's own.
     *
     * @param  Builder<Viewing>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->checkPermissionTo('viewings.view_all')) {
            return;
        }

        $query->where('agent_id', $user->agent()->value('id') ?? 0);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ViewingStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}

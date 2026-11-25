<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales enquiry. `status` and `agent_id` are not fillable: status moves through LeadStatusService,
 * assignment through the assign endpoint.
 *
 * @property LeadStatus $status
 */
#[Fillable(['name', 'email', 'phone', 'message', 'source', 'property_id', 'project_id'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const SOURCES = ['web', 'phone', 'walk_in', 'referral', 'other'];

    /**
     * Leads a user may see: everything with `leads.view_all`, otherwise only the linked agent's own.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->checkPermissionTo('leads.view_all')) {
            return;
        }

        $query->where('agent_id', $user->agent()->value('id') ?? 0);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<LeadNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->orderBy('id');
    }

    /** @return HasMany<Viewing, $this> */
    public function viewings(): HasMany
    {
        return $this->hasMany(Viewing::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => LeadStatus::class, 'last_contacted_at' => 'datetime'];
    }
}

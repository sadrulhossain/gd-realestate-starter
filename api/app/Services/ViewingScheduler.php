<?php

namespace App\Services;

use App\Enums\ViewingStatus;
use App\Models\Agent;
use App\Models\Property;
use App\Models\User;
use App\Models\Viewing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and moves viewings. An agent cannot be in two places: a viewing that overlaps another
 * scheduled or confirmed viewing of the same agent answers 422 on `starts_at`.
 * The agent row is locked for the check so two requests cannot both pass.
 */
class ViewingScheduler
{
    /**
     * Who runs the viewing: a user without `viewings.view_all` always runs their own; otherwise the
     * requested agent, falling back to the property's agent.
     *
     * @throws ValidationException when no agent can be resolved
     */
    public function resolveAgentId(User $user, Property $property, ?int $requested): int
    {
        $agentId = $user->checkPermissionTo('viewings.view_all')
            ? ($requested ?? $property->agent_id)
            : $user->agent?->id;

        if ($agentId === null) {
            throw ValidationException::withMessages(['agent_id' => ['An agent is required for this viewing.']]);
        }

        return (int) $agentId;
    }

    /**
     * @param  array<string, mixed>  $data  property_id, lead_id?, starts_at, ends_at, notes?
     *
     * @throws ValidationException on a conflict
     */
    public function create(array $data, int $agentId): Viewing
    {
        return DB::transaction(function () use ($data, $agentId): Viewing {
            $this->assertFree($agentId, $data['starts_at'], $data['ends_at']);

            $viewing = new Viewing($data);
            $viewing->agent_id = $agentId;
            $viewing->save();

            return $viewing;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException on a conflict
     */
    public function update(Viewing $viewing, array $data): Viewing
    {
        return DB::transaction(function () use ($viewing, $data): Viewing {
            $viewing->fill($data);

            if ($viewing->status->isActive() && $viewing->isDirty(['starts_at', 'ends_at'])) {
                $this->assertFree($viewing->agent_id, $viewing->starts_at, $viewing->ends_at, $viewing->id);
            }

            $viewing->save();

            return $viewing;
        });
    }

    /** @throws ValidationException when the transition is not allowed */
    public function transition(Viewing $viewing, ViewingStatus $to): Viewing
    {
        if (! in_array($to, $viewing->status->next(), true)) {
            throw ValidationException::withMessages([
                'status' => ["A viewing cannot move from {$viewing->status->value} to {$to->value}."],
            ]);
        }

        $viewing->status = $to;
        $viewing->save();

        return $viewing;
    }

    private function assertFree(int $agentId, mixed $start, mixed $end, ?int $ignoreId = null): void
    {
        Agent::query()->whereKey($agentId)->lockForUpdate()->first();

        $clash = Viewing::query()
            ->where('agent_id', $agentId)
            ->whereIn('status', ViewingStatus::activeValues())
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages(['starts_at' => ['The agent already has a viewing at this time.']]);
        }
    }
}

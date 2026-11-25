<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The only place a lead's status changes. Writes one timeline entry per change. */
class LeadStatusService
{
    /** @throws ValidationException when the transition is not allowed */
    public function transition(Lead $lead, LeadStatus $to, ?User $actor = null, ?string $reason = null): Lead
    {
        $from = $lead->status;

        if (! in_array($to, $from->next(), true)) {
            throw ValidationException::withMessages([
                'status' => ["A lead cannot move from {$from->value} to {$to->value}."],
            ]);
        }

        if ($to === LeadStatus::Lost && blank($reason)) {
            throw ValidationException::withMessages(['lost_reason' => ['A lost lead needs a reason.']]);
        }

        DB::transaction(function () use ($lead, $from, $to, $actor, $reason): void {
            $lead->status = $to;
            $lead->lost_reason = $to === LeadStatus::Lost ? $reason : null;

            if ($to === LeadStatus::Contacted) {
                $lead->last_contacted_at = now();
            }

            $lead->save();
            $lead->notes()->create([
                'user_id' => $actor?->id,
                'kind' => 'status',
                'body' => "Status: {$from->value} to {$to->value}".($to === LeadStatus::Lost ? " ({$reason})" : ''),
            ]);
        });

        return $lead;
    }
}

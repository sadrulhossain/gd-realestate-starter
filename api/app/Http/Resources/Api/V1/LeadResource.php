<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'source' => $this->source,
            'status' => $this->status,
            'lost_reason' => $this->lost_reason,
            'property_id' => $this->property_id,
            'property' => $this->whenLoaded('property', fn () => $this->property ? [
                'id' => $this->property->id,
                'ref_code' => $this->property->ref_code,
                'title' => $this->property->title,
                'slug' => $this->property->slug,
            ] : null),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'slug' => $this->project->slug,
            ] : null),
            'agent_id' => $this->agent_id,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? ['id' => $this->agent->id, 'name' => $this->agent->name] : null),
            'viewing_requested_at' => $this->viewing_requested_at,
            'last_contacted_at' => $this->last_contacted_at,
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($note) => [
                'id' => $note->id,
                'kind' => $note->kind,
                'body' => $note->body,
                'user' => $note->user ? ['id' => $note->user->id, 'name' => $note->user->name] : null,
                'created_at' => $note->created_at,
            ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

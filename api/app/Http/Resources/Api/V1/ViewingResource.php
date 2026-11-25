<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Viewing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Viewing */
class ViewingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property' => $this->whenLoaded('property', fn () => [
                'id' => $this->property->id,
                'ref_code' => $this->property->ref_code,
                'title' => $this->property->title,
                'slug' => $this->property->slug,
            ]),
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? ['id' => $this->lead->id, 'name' => $this->lead->name] : null),
            'agent_id' => $this->agent_id,
            'agent' => $this->whenLoaded('agent', fn () => ['id' => $this->agent->id, 'name' => $this->agent->name]),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

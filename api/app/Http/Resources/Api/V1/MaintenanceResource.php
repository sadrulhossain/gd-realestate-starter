<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceRequest */
class MaintenanceResource extends JsonResource
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
            ]),
            'lease_id' => $this->lease_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'cost' => $this->cost,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

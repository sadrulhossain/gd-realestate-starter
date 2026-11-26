<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lease */
class LeaseResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => ['id' => $this->tenant->id, 'name' => $this->tenant->name]),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'rent_amount' => $this->rent_amount,
            'rent_period' => $this->rent_period,
            'deposit_amount' => $this->deposit_amount,
            'status' => $this->status,
            'ended_on' => $this->ended_on?->toDateString(),
            'notes' => $this->notes,
            'charges' => RentChargeResource::collection($this->whenLoaded('charges')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

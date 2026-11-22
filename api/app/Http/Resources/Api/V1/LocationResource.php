<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Location */
class LocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'name_2nd' => $this->name_2nd,
            'slug' => $this->slug,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Amenity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Amenity */
class AmenityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_2nd' => $this->name_2nd,
            'icon' => $this->icon,
            'group' => $this->group,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at,
        ];
    }
}

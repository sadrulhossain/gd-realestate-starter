<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProjectUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A unit. `property_id` is admin only; the visitor subclass hides it.
 *
 * @mixin ProjectUnit
 */
class ProjectUnitResource extends JsonResource
{
    /** Hide the admin-only fields (see PublicProjectUnitResource). */
    protected bool $public = false;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'code' => $this->code,
            'name' => $this->name,
            'name_2nd' => $this->name_2nd,
            'property_type' => PropertyTypeResource::make($this->whenLoaded('propertyType')),
            'property_type_id' => $this->property_type_id,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'area' => $this->area,
            'area_unit' => $this->area_unit,
            'floor' => $this->floor,
            'price' => $this->price,
            'status' => $this->status,
            ...($this->public ? [] : [
                'property_id' => $this->whenLoaded('property', fn () => $this->property?->id),
                'property_slug' => $this->whenLoaded('property', fn () => $this->property?->slug),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project. Admin sees every field and inactive rows; PublicProjectResource is the visitor view
 * (public developer fields and units).
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    protected bool $public = false;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_2nd' => $this->name_2nd,
            'slug' => $this->slug,
            'description' => $this->description,
            'description_2nd' => $this->description_2nd,
            'developer_id' => $this->developer_id,
            'developer' => $this->public
                ? PublicDeveloperResource::make($this->whenLoaded('developer'))
                : DeveloperResource::make($this->whenLoaded('developer')),
            'location_id' => $this->location_id,
            'location' => LocationResource::make($this->whenLoaded('location')),
            'address_line' => $this->address_line,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'handover_date' => $this->handover_date?->format('Y-m-d'),
            'featured' => $this->featured,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'units_count' => $this->whenCounted('units'),
            'from_price' => $this->when(isset($this->resource->from_price), fn () => (int) $this->resource->from_price),
            'units' => $this->whenLoaded('units', fn () => $this->units->map(
                fn ($unit) => $this->public ? new PublicProjectUnitResource($unit) : new ProjectUnitResource($unit),
            )->all()),
            ...($this->public ? [] : [
                'is_active' => $this->is_active,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
        ];
    }
}

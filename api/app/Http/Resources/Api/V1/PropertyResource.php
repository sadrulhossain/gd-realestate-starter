<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of a property: every column, including owner and workflow fields.
 * Never used for visitors (see PublicPropertyResource).
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ref_code' => $this->ref_code,
            'slug' => $this->slug,
            'title' => $this->title,
            'title_2nd' => $this->title_2nd,
            'description' => $this->description,
            'description_2nd' => $this->description_2nd,
            'listing_type' => $this->listing_type,
            'property_type_id' => $this->property_type_id,
            'location_id' => $this->location_id,
            'address_line' => $this->address_line,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'price' => $this->price,
            'rent_period' => $this->rent_period,
            'area' => $this->area,
            'area_unit' => $this->area_unit,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'floor' => $this->floor,
            'total_floors' => $this->total_floors,
            'facing' => $this->facing,
            'year_built' => $this->year_built,
            'furnished' => $this->furnished,
            'status' => $this->status,
            'featured' => $this->featured,
            'agent_id' => $this->agent_id,
            'project_id' => $this->project_id,
            'unit_id' => $this->unit_id,
            'owner_id' => $this->owner_id,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'published_at' => $this->published_at,
            'property_type' => PropertyTypeResource::make($this->whenLoaded('propertyType')),
            'location' => LocationResource::make($this->whenLoaded('location')),
            'amenity_ids' => $this->whenLoaded('amenities', fn () => $this->amenities->pluck('id')->all()),
            'cover' => PropertyMediaResource::make($this->whenLoaded('coverMedia')),
            'media' => PropertyMediaResource::collection($this->whenLoaded('media')),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($row) => [
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'user_id' => $row->user_id,
                'note' => $row->note,
                'created_at' => $row->created_at,
            ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

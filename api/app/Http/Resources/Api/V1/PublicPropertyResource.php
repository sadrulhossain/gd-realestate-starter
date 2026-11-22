<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a visitor sees on a listing card. No owner, agent, project or workflow fields:
 * public resources are separate classes from the admin ones.
 *
 * @mixin Property
 */
class PublicPropertyResource extends JsonResource
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
            'listing_type' => $this->listing_type,
            'status' => $this->status,
            'featured' => $this->featured,
            'property_type' => PropertyTypeResource::make($this->whenLoaded('propertyType')),
            'location' => LocationResource::make($this->whenLoaded('location')),
            'address_line' => $this->address_line,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'price' => $this->price,
            'rent_period' => $this->rent_period,
            'area' => $this->area,
            'area_unit' => $this->area_unit,
            'beds' => $this->beds,
            'baths' => $this->baths,
            'furnished' => $this->furnished,
            'cover' => PropertyMediaResource::make($this->whenLoaded('coverMedia')),
            'published_at' => $this->published_at,
        ];
    }
}

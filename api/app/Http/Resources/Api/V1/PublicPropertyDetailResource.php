<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Property;
use Illuminate\Http\Request;

/**
 * A property page: the card fields plus description, facts, amenities and media.
 *
 * @mixin Property
 */
class PublicPropertyDetailResource extends PublicPropertyResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'description_2nd' => $this->description_2nd,
            'floor' => $this->floor,
            'total_floors' => $this->total_floors,
            'facing' => $this->facing,
            'year_built' => $this->year_built,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'amenities' => AmenityResource::collection($this->whenLoaded('amenities')),
            'media' => PropertyMediaResource::collection($this->whenLoaded('media')),
            'agent' => PublicAgentResource::make($this->whenLoaded('agent')),
        ];
    }
}

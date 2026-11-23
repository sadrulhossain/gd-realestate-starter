<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Developer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a visitor sees of a developer: no contact email or phone, no admin fields.
 *
 * @mixin Developer
 */
class PublicDeveloperResource extends JsonResource
{
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
            'website' => $this->website,
        ];
    }
}

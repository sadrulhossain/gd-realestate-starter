<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Developer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Developer */
class DeveloperResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

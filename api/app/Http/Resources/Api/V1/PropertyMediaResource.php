<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PropertyMedia;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PropertyMedia */
class PropertyMediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'file' => $this->isFile() ? app(MediaService::class)->describe($this->path) : null,
            'url' => $this->isFile() ? null : $this->path,
            'sort' => $this->sort,
            'is_cover' => $this->is_cover,
        ];
    }
}

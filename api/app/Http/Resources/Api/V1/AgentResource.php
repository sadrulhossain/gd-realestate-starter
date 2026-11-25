<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Agent;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An agent. Admin sees every field; PublicAgentResource is the visitor view (no user link, no
 * inactive flag, no timestamps).
 *
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    protected bool $public = false;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->title,
            'title_2nd' => $this->title_2nd,
            'bio' => $this->bio,
            'bio_2nd' => $this->bio_2nd,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'license_no' => $this->license_no,
            'image' => app(MediaService::class)->describe($this->image),
            'listings_count' => $this->whenCounted('listings'),
            ...($this->public ? [] : [
                'user_id' => $this->user_id,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
        ];
    }
}

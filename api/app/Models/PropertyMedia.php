<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One image, floor plan, brochure (stored files) or video link of a property. Only an image can be the cover. */
#[Fillable(['kind', 'path', 'sort', 'is_cover'])]
class PropertyMedia extends Model
{
    protected $table = 'property_media';

    public const KINDS = ['image', 'floorplan', 'brochure', 'video_url'];

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** True when `path` is a file on the uploads disk (a video link is not). */
    public function isFile(): bool
    {
        return $this->kind !== 'video_url';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort' => 'integer', 'is_cover' => 'boolean'];
    }
}

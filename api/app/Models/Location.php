<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place in the tree country > city > area. Slugs are unique per parent. */
#[Fillable(['parent_id', 'name', 'name_2nd', 'slug', 'lat', 'lng', 'sort_order', 'is_active'])]
class Location extends Model
{
    use HasSecondLanguage;

    public const MAX_DEPTH = 3;

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Location, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Number of ancestors above a node whose parent is `$parentId` (a root has 0). */
    public static function depthOf(?int $parentId): int
    {
        $parents = self::query()->pluck('parent_id', 'id');
        $depth = 0;

        while ($parentId !== null && $depth <= self::MAX_DEPTH + 1) {
            $depth++;
            $parentId = $parents[$parentId] ?? null;
        }

        return $depth;
    }

    /**
     * Ids of every node below this one.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $parents = self::query()->pluck('parent_id', 'id');
        $found = [];
        $level = [$this->id];

        while ($level) {
            $next = [];
            foreach ($parents as $id => $parent) {
                if (in_array($parent, $level, true)) {
                    $next[] = $id;
                }
            }
            $found = [...$found, ...$next];
            $level = $next;
        }

        return $found;
    }

    /** Levels of nodes below this one (0 for a leaf). */
    public function subtreeHeight(): int
    {
        $parents = self::query()->pluck('parent_id', 'id');
        $height = 0;
        $level = [$this->id];

        while ($level) {
            $next = [];
            foreach ($parents as $id => $parent) {
                if (in_array($parent, $level, true)) {
                    $next[] = $id;
                }
            }
            $height += $next ? 1 : 0;
            $level = $next;
        }

        return $height;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}

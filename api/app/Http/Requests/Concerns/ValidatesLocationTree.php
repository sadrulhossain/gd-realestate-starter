<?php

namespace App\Http\Requests\Concerns;

use App\Models\Location;
use Illuminate\Validation\Validator;

/** Tree rules shared by the location create and update requests. */
trait ValidatesLocationTree
{
    /** @return array<string, mixed> */
    protected function locationRules(bool $creating): array
    {
        $required = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'slug' => [...$required, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Slug unique among siblings, no cycles, at most Location::MAX_DEPTH levels. */
    protected function checkLocationTree(Validator $validator, ?Location $current): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $parentId = $this->has('parent_id') ? $this->integer('parent_id') ?: null : $current?->parent_id;
        $slug = $this->input('slug', $current?->slug);

        $taken = Location::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->exists();

        if ($taken) {
            $validator->errors()->add('slug', 'The slug is already used by a sibling location.');
        }

        if ($current !== null && $parentId !== null
            && ($parentId === $current->id || in_array($parentId, $current->descendantIds(), true))) {
            $validator->errors()->add('parent_id', 'A location cannot be moved under itself or one of its descendants.');

            return;
        }

        $levels = Location::depthOf($parentId) + 1 + ($current?->subtreeHeight() ?? 0);

        if ($levels > Location::MAX_DEPTH) {
            $validator->errors()->add('parent_id', 'Locations can be nested at most '.Location::MAX_DEPTH.' levels deep (country, city, area).');
        }
    }
}

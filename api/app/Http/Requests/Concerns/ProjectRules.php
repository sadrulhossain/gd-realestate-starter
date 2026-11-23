<?php

namespace App\Http\Requests\Concerns;

use App\Models\Project;
use App\Support\Bilingual;
use Illuminate\Validation\Rule;

/** Field rules shared by the project create and update requests. */
trait ProjectRules
{
    /** @return array<string, mixed> */
    protected function projectRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            ...Bilingual::rules([
                'name' => [...$req, 'string', 'max:200'],
                'description' => [...$opt, 'string', 'max:10000'],
            ]),
            'slug' => ['sometimes', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects', 'slug')->ignore($this->route('project'))],
            'developer_id' => [...$opt, 'integer', Rule::exists('developers', 'id')],
            'location_id' => [...$req, 'integer', Rule::exists('locations', 'id')],
            'address_line' => [...$opt, 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'status' => ['sometimes', Rule::in(Project::STATUSES)],
            'handover_date' => [...$opt, 'date_format:Y-m-d'],
            'featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'seo_title' => [...$opt, 'string', 'max:255'],
            'seo_description' => [...$opt, 'string', 'max:320'],
        ];
    }
}

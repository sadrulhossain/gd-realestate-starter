<?php

namespace App\Http\Requests\Concerns;

use App\Models\ProjectUnit;
use App\Models\Property;
use App\Support\Bilingual;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Field rules shared by the property create and update requests. */
trait PropertyRules
{
    /** @return array<string, mixed> */
    protected function propertyRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            ...Bilingual::rules([
                'title' => [...$req, 'string', 'max:200'],
                'description' => [...$opt, 'string', 'max:10000'],
            ]),
            'slug' => ['sometimes', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('properties', 'slug')->ignore($this->route('property'))],
            'listing_type' => [...$req, Rule::in(Property::LISTING_TYPES)],
            'property_type_id' => [...$req, 'integer', Rule::exists('property_types', 'id')],
            'location_id' => [...$req, 'integer', Rule::exists('locations', 'id')],
            'address_line' => [...$opt, 'string', 'max:255'],
            // No `sometimes` here: it would skip required_with when the other coordinate is sent alone.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'price' => [...$req, 'integer', 'min:0', 'max:999999999999999'],
            'rent_period' => [...$opt, Rule::in(Property::RENT_PERIODS)],
            'area' => [...$opt, 'numeric', 'min:0', 'max:99999999'],
            'area_unit' => ['sometimes', Rule::in(Property::AREA_UNITS)],
            'beds' => [...$opt, 'integer', 'min:0', 'max:99'],
            'baths' => [...$opt, 'integer', 'min:0', 'max:99'],
            'floor' => [...$opt, 'integer', 'min:-5', 'max:200'],
            'total_floors' => [...$opt, 'integer', 'min:0', 'max:200'],
            'facing' => [...$opt, Rule::in(Property::FACINGS)],
            'year_built' => [...$opt, 'integer', 'min:1800', 'max:'.((int) date('Y') + 10)],
            'furnished' => [...$opt, Rule::in(Property::FURNISHED)],
            'featured' => ['sometimes', 'boolean'],
            'agent_id' => [...$opt, 'integer', 'min:1'],
            'project_id' => [...$opt, 'integer', Rule::exists('projects', 'id')],
            'unit_id' => [...$opt, 'integer', Rule::exists('project_units', 'id'), Rule::unique('properties', 'unit_id')->ignore($this->route('property'))],
            'owner_id' => [...$opt, 'integer', 'min:1'],
            'seo_title' => [...$opt, 'string', 'max:255'],
            'seo_description' => [...$opt, 'string', 'max:320'],
            'amenity_ids' => ['sometimes', 'array', 'max:100'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
        ];
    }

    /** A linked unit must belong to the linked project. */
    private function checkUnitBelongsToProject(Validator $validator, ?Property $current): void
    {
        $unitId = $this->has('unit_id') ? $this->input('unit_id') : $current?->unit_id;

        if (blank($unitId)) {
            return;
        }

        $projectId = $this->has('project_id') ? $this->input('project_id') : $current?->project_id;
        $unitProject = ProjectUnit::query()->whereKey($unitId)->value('project_id');

        if ($unitProject !== null && (int) $unitProject !== (int) $projectId) {
            $validator->errors()->add('unit_id', 'The unit does not belong to the selected project.');
        }
    }

    /** A rent listing needs a rent period; a sale listing must not have one. */
    protected function checkRentPeriod(Validator $validator, ?Property $current): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->checkUnitBelongsToProject($validator, $current);

        $type = $this->input('listing_type', $current?->listing_type);
        $period = $this->has('rent_period') ? $this->input('rent_period') : $current?->rent_period;

        if ($type === 'rent' && blank($period)) {
            $validator->errors()->add('rent_period', 'A rent listing needs a rent period (month, year or day).');
        }

        if ($type === 'sale' && $this->filled('rent_period')) {
            $validator->errors()->add('rent_period', 'A sale listing has no rent period.');
        }
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Models\ProjectUnit;
use App\Models\Property;
use App\Support\Bilingual;
use Illuminate\Validation\Rule;

/** Field rules shared by the unit create and update requests. */
trait ProjectUnitRules
{
    /** @return array<string, mixed> */
    protected function unitRules(bool $creating, ?int $projectId, ?int $ignoreId): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            ...Bilingual::rules([
                'name' => [...$opt, 'string', 'max:150'],
            ]),
            'code' => [...$req, 'string', 'max:40', Rule::unique('project_units', 'code')->where('project_id', $projectId)->ignore($ignoreId)],
            'property_type_id' => [...$req, 'integer', Rule::exists('property_types', 'id')],
            'beds' => [...$opt, 'integer', 'min:0', 'max:99'],
            'baths' => [...$opt, 'integer', 'min:0', 'max:99'],
            'area' => [...$opt, 'numeric', 'min:0', 'max:99999999'],
            'area_unit' => ['sometimes', Rule::in(Property::AREA_UNITS)],
            'floor' => [...$opt, 'integer', 'min:-5', 'max:200'],
            'price' => [...$req, 'integer', 'min:0', 'max:999999999999999'],
            'status' => ['sometimes', Rule::in(ProjectUnit::STATUSES)],
        ];
    }
}

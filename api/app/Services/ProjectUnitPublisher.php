<?php

namespace App\Services;

use App\Models\ProjectUnit;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Turns a project unit into a draft property. A unit is published at most once. */
class ProjectUnitPublisher
{
    /** @throws ValidationException when the unit already has a property */
    public function publish(ProjectUnit $unit, string $listingType = 'sale', ?string $rentPeriod = null): Property
    {
        return DB::transaction(function () use ($unit, $listingType, $rentPeriod): Property {
            // Lock the unit row so two concurrent publishes cannot both pass the check.
            $unit = ProjectUnit::query()->lockForUpdate()->with('project')->findOrFail($unit->id);

            if ($unit->property()->exists()) {
                throw ValidationException::withMessages(['unit' => ['This unit is already published as a property.']]);
            }

            $project = $unit->project;
            $label = $unit->name ?: $unit->code;

            return Property::query()->create([
                'title' => $project->name.' - '.$label,
                'title_2nd' => ($project->name_2nd || $unit->name_2nd) ? ($project->name_2nd ?: $project->name).' - '.($unit->name_2nd ?: $label) : null,
                'listing_type' => $listingType,
                'rent_period' => $listingType === 'rent' ? $rentPeriod : null,
                'property_type_id' => $unit->property_type_id,
                'location_id' => $project->location_id,
                'address_line' => $project->address_line,
                'lat' => $project->lat,
                'lng' => $project->lng,
                'price' => $unit->price,
                'area' => $unit->area,
                'area_unit' => $unit->area_unit,
                'beds' => $unit->beds,
                'baths' => $unit->baths,
                'floor' => $unit->floor,
                'project_id' => $project->id,
                'unit_id' => $unit->id,
            ])->refresh();
        });
    }
}

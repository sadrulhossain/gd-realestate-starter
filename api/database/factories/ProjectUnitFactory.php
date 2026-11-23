<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectUnit> */
class ProjectUnitFactory extends Factory
{
    protected $model = ProjectUnit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'property_type_id' => fn () => PropertyType::query()->firstOrCreate(['key' => 'apartment'], ['name' => 'Apartment', 'group' => 'residential'])->id,
            'code' => fake()->unique()->bothify('A-###'),
            'beds' => 3,
            'baths' => 2,
            'area' => 1450,
            'price' => 15_000_000,
        ];
    }
}

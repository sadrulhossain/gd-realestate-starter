<?php

namespace Database\Factories;

use App\Models\Developer;
use App\Models\Location;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Residences',
            'developer_id' => fn () => Developer::factory()->create()->id,
            'location_id' => fn () => Location::query()->firstOrCreate(['slug' => 'dhaka', 'parent_id' => null], ['name' => 'Dhaka'])->id,
        ];
    }
}

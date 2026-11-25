<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Property;
use App\Models\Viewing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Viewing> */
class ViewingFactory extends Factory
{
    protected $model = Viewing::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->addDays(2)->setTime(10, 0);

        return [
            'property_id' => fn () => Property::factory()->available()->create()->id,
            'agent_id' => fn () => Agent::factory()->create()->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ];
    }
}

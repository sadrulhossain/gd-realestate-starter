<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->name(), 'email' => fake()->unique()->safeEmail()];
    }
}

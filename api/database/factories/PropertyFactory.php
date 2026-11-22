<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'listing_type' => 'sale',
            'property_type_id' => fn () => PropertyType::query()->firstOrCreate(['key' => 'apartment'], ['name' => 'Apartment', 'group' => 'residential'])->id,
            'location_id' => fn () => Location::query()->firstOrCreate(['slug' => 'dhaka', 'parent_id' => null], ['name' => 'Dhaka'])->id,
            'price' => fake()->numberBetween(1_000_000, 90_000_000),
            'area' => fake()->numberBetween(400, 3000),
            'beds' => fake()->numberBetween(1, 5),
            'baths' => fake()->numberBetween(1, 4),
        ];
    }

    /** Create the property already in the given status (history is not written; use the service in workflow tests). */
    public function status(PropertyStatus $status): static
    {
        return $this->afterCreating(function (Property $property) use ($status): void {
            $property->forceFill([
                'status' => $status,
                'published_at' => in_array($status, [PropertyStatus::Draft, PropertyStatus::Archived], true) ? null : now(),
            ])->save();
        });
    }

    public function available(): static
    {
        return $this->status(PropertyStatus::Available);
    }

    public function rent(string $period = 'month'): static
    {
        return $this->state(['listing_type' => 'rent', 'rent_period' => $period]);
    }
}

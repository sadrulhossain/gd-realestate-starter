<?php

namespace Database\Seeders;

use App\Models\Amenity;

class AmenitiesModuleSeeder extends ModuleSeeder
{
    private const DEFAULTS = [
        ['Lift', 'arrow-up-down', 'building'],
        ['Parking', 'car', 'building'],
        ['Generator', 'zap', 'building'],
        ['Security', 'shield', 'safety'],
        ['Gym', 'dumbbell', 'lifestyle'],
        ['Pool', 'waves', 'lifestyle'],
        ['Gas', 'flame', 'utilities'],
        ['Balcony', 'door-open', 'interior'],
    ];

    protected function permissions(): array
    {
        return ['amenities.view', 'amenities.create', 'amenities.update', 'amenities.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Amenities', 'permission' => 'amenities.view', 'path' => '/amenities', 'icon' => 'sparkles', 'sort' => 30],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }

    public function run(): void
    {
        parent::run();

        foreach (self::DEFAULTS as $i => [$name, $icon, $group]) {
            Amenity::query()->firstOrCreate(['name' => $name], ['icon' => $icon, 'group' => $group, 'sort_order' => ($i + 1) * 10]);
        }
    }
}

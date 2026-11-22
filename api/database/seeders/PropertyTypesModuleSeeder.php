<?php

namespace Database\Seeders;

use App\Models\PropertyType;

class PropertyTypesModuleSeeder extends ModuleSeeder
{
    private const DEFAULTS = [
        ['apartment', 'Apartment', 'residential'],
        ['house', 'House', 'residential'],
        ['duplex', 'Duplex', 'residential'],
        ['plot', 'Plot', 'land'],
        ['shop', 'Shop', 'commercial'],
        ['office', 'Office', 'commercial'],
        ['warehouse', 'Warehouse', 'commercial'],
    ];

    protected function permissions(): array
    {
        return ['property_types.view', 'property_types.create', 'property_types.update', 'property_types.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Property types', 'permission' => 'property_types.view', 'path' => '/property-types', 'icon' => 'home', 'sort' => 20],
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

        foreach (self::DEFAULTS as $i => [$key, $name, $group]) {
            PropertyType::query()->firstOrCreate(['key' => $key], ['name' => $name, 'group' => $group, 'sort_order' => ($i + 1) * 10]);
        }
    }
}

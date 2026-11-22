<?php

namespace Database\Seeders;

class LocationsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['locations.view', 'locations.create', 'locations.update', 'locations.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Locations', 'permission' => 'locations.view', 'path' => '/locations', 'icon' => 'map-pin', 'sort' => 10],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

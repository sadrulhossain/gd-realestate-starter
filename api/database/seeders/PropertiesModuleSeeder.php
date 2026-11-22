<?php

namespace Database\Seeders;

class PropertiesModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['properties.view', 'properties.create', 'properties.update', 'properties.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Properties', 'permission' => 'properties.view', 'path' => '/properties', 'icon' => 'building', 'sort' => 5],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

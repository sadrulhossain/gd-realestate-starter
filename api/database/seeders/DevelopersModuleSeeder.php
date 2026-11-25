<?php

namespace Database\Seeders;

class DevelopersModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['developers.view', 'developers.create', 'developers.update', 'developers.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Developers', 'permission' => 'developers.view', 'path' => '/developers', 'icon' => 'briefcase', 'sort' => 40],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

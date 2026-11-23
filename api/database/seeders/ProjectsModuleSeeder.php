<?php

namespace Database\Seeders;

/** Projects and their units share one menu entry; units have their own permissions. */
class ProjectsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return [
            'projects.view', 'projects.create', 'projects.update', 'projects.delete',
            'project_units.view', 'project_units.create', 'project_units.update', 'project_units.delete',
        ];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Catalogue', 'sort' => 15, 'children' => [
                ['title' => 'Projects', 'permission' => 'projects.view', 'path' => '/projects', 'icon' => 'building-2', 'sort' => 50],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

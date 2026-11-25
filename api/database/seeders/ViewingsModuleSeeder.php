<?php

namespace Database\Seeders;

/** `viewings.view_all` lifts the own-records scope; the `agent` role keeps only its own viewings. */
class ViewingsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['viewings.view', 'viewings.create', 'viewings.update', 'viewings.delete', 'viewings.view_all'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Sales', 'sort' => 17, 'children' => [
                ['title' => 'Viewings', 'permission' => 'viewings.view', 'path' => '/viewings', 'icon' => 'calendar', 'sort' => 30],
            ]],
        ];
    }

    protected function grants(): array
    {
        return [
            'admin' => $this->permissions(),
            'agent' => ['viewings.view', 'viewings.create', 'viewings.update'],
        ];
    }
}

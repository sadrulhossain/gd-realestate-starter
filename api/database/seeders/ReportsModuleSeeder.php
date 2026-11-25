<?php

namespace Database\Seeders;

/** Reports cover every record, so only `admin` gets them; the `agent` role does not. */
class ReportsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['reports.view'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Sales', 'sort' => 17, 'children' => [
                ['title' => 'Reports', 'permission' => 'reports.view', 'path' => '/reports', 'icon' => 'list', 'sort' => 40],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

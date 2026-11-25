<?php

namespace Database\Seeders;

/**
 * `leads.view_all` lifts the own-records scope. The `agent` role is created here and sees only the leads
 * assigned to its linked agent profile.
 */
class LeadsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.assign', 'leads.view_all'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Sales', 'sort' => 17, 'children' => [
                ['title' => 'Leads', 'permission' => 'leads.view', 'path' => '/leads', 'icon' => 'inbox', 'sort' => 20],
            ]],
        ];
    }

    protected function grants(): array
    {
        return [
            'admin' => $this->permissions(),
            'agent' => ['leads.view', 'leads.create', 'leads.update'],
        ];
    }
}

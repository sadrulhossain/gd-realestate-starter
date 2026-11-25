<?php

namespace Database\Seeders;

class AgentsModuleSeeder extends ModuleSeeder
{
    protected function permissions(): array
    {
        return ['agents.view', 'agents.create', 'agents.update', 'agents.delete'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Sales', 'sort' => 17, 'children' => [
                ['title' => 'Agents', 'permission' => 'agents.view', 'path' => '/agents', 'icon' => 'users', 'sort' => 10],
            ]],
        ];
    }

    /** Agents (the role) can read the agent list, e.g. to pick a colleague. */
    protected function grants(): array
    {
        return ['admin' => $this->permissions(), 'agent' => ['agents.view']];
    }
}

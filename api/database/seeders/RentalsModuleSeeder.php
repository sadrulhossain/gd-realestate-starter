<?php

namespace Database\Seeders;

use App\Models\Menu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The optional rental module. With RENTALS_ENABLED=false, seeding removes its menus and permissions (data stays).
 * The `agent` role gets none of it.
 */
class RentalsModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        if (config('rentals.enabled')) {
            parent::run();

            return;
        }

        Menu::query()->whereIn('permission_name', $this->permissions())->delete();

        // The group goes too, unless someone moved other entries into it.
        Menu::query()->whereNull('permission_name')->where('title', 'Rentals')->get()
            ->reject(fn (Menu $group) => Menu::query()->where('parent_id', $group->id)->exists())
            ->each->delete();

        Permission::query()->where('guard_name', self::GUARD)->whereIn('name', $this->permissions())->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function permissions(): array
    {
        return [
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
            'leases.view', 'leases.create', 'leases.update', 'leases.delete',
            'rent_charges.view', 'rent_charges.update',
            'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.delete',
            'rental_reports.view',
        ];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Rentals', 'sort' => 18, 'children' => [
                ['title' => 'Tenants', 'permission' => 'tenants.view', 'path' => '/tenants', 'icon' => 'users', 'sort' => 10],
                ['title' => 'Leases', 'permission' => 'leases.view', 'path' => '/leases', 'icon' => 'file-text', 'sort' => 20],
                ['title' => 'Rent', 'permission' => 'rent_charges.view', 'path' => '/rent-charges', 'icon' => 'calendar', 'sort' => 30],
                ['title' => 'Maintenance', 'permission' => 'maintenance.view', 'path' => '/maintenance', 'icon' => 'briefcase', 'sort' => 40],
                ['title' => 'Rental reports', 'permission' => 'rental_reports.view', 'path' => '/rental-reports', 'icon' => 'list', 'sort' => 50],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => $this->permissions()];
    }
}

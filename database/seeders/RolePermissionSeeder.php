<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the core ERP-HRIS roles + permissions.
     *
     * Roles: administrator (top-level) + hr / supervisor / manager / employee (created here,
     * granted their hris.* matrix in HrisRoleSeeder). HRIS-specific permissions live in HrisRoleSeeder.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Core (non-HRIS) permissions used for navigation + user management.
        $permissions = [
            'dashboard view',
            'master view',
            'user view',
            'user create',
            'user edit',
            'user delete',
            'user-log view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = ['administrator', 'hr', 'supervisor', 'manager', 'employee'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Administrator holds every core permission (and every hris.* permission via HrisRoleSeeder).
        // The other roles get their core nav perms folded into HrisRoleSeeder's syncPermissions().
        Role::findByName('administrator')->givePermissionTo($permissions);
    }
}

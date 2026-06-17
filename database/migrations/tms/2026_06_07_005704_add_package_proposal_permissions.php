<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            'package-proposal view',
            'package-proposal create',
            'package-proposal edit',
            'package-proposal delete',
            'package-proposal approve',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $rolePermissions = [
            'superadmin' => $permissions,
            'admin' => [
                'package-proposal view',
                'package-proposal create',
                'package-proposal edit',
                'package-proposal delete',
            ],
            'sales' => [
                'package-proposal view',
                'package-proposal create',
                'package-proposal edit',
                'package-proposal delete',
            ],
            'operations' => [
                'package-proposal view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            'package-proposal view',
            'package-proposal create',
            'package-proposal edit',
            'package-proposal delete',
            'package-proposal approve',
        ];

        foreach (['superadmin', 'admin', 'sales', 'operations'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role) {
                $role->revokePermissionTo($permissions);
            }
        }

        Permission::whereIn('name', $permissions)
            ->where('guard_name', $guard)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

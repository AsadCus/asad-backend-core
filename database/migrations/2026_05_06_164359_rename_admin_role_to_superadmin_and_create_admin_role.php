<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $existingAdmin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', $guard)
            ->first();

        $superadmin = Role::query()
            ->where('name', 'superadmin')
            ->where('guard_name', $guard)
            ->first();

        if ($existingAdmin && ! $superadmin) {
            $existingAdmin->update(['name' => 'superadmin']);
            $superadmin = $existingAdmin;
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $salesRole = Role::query()
            ->where('name', 'sales')
            ->where('guard_name', $guard)
            ->first();

        if ($salesRole) {
            $permissionNames = $salesRole->permissions->pluck('name')->all();
            $permissionNames = array_values(array_filter(
                $permissionNames,
                static fn (string $permission): bool => $permission !== 'dashboard view',
            ));

            $adminRole->syncPermissions($permissionNames);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $superadmin = Role::query()
            ->where('name', 'superadmin')
            ->where('guard_name', $guard)
            ->first();

        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', $guard)
            ->first();

        if ($adminRole && $superadmin) {
            $adminRole->delete();
            $superadmin->update(['name' => 'admin']);

            return;
        }

        if ($superadmin) {
            $superadmin->update(['name' => 'admin']);
        }
    }
};

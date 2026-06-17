<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $administrator = Role::firstOrCreate(
            ['name' => 'administrator', 'guard_name' => $guard],
        );

        $legacyRoles = Role::query()
            ->whereIn('name', ['admin', 'superadmin'])
            ->where('guard_name', $guard)
            ->get();

        foreach ($legacyRoles as $legacy) {
            $permissions = $legacy->permissions()->pluck('id')->all();
            if (! empty($permissions)) {
                $administrator->permissions()->syncWithoutDetaching($permissions);
            }
        }

        $legacyRoleIds = $legacyRoles->pluck('id')->all();

        if (! empty($legacyRoleIds)) {
            $userIds = DB::table(config('permission.table_names.model_has_roles'))
                ->whereIn('role_id', $legacyRoleIds)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id')
                ->unique()
                ->all();

            $rows = [];
            foreach ($userIds as $userId) {
                $rows[] = [
                    'role_id' => $administrator->id,
                    'model_type' => \App\Models\User::class,
                    'model_id' => $userId,
                ];
            }

            if (! empty($rows)) {
                DB::table(config('permission.table_names.model_has_roles'))
                    ->upsert($rows, ['role_id', 'model_type', 'model_id']);
            }
        }

        foreach ($legacyRoles as $legacy) {
            $legacy->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => $guard]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

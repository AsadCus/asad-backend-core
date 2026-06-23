<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_core_roles_plus_starter_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HrisRoleSeeder::class);

        $roles = Role::query()->pluck('name')->sort()->values()->all();

        // 5 core roles + Director/Finance editable starters.
        $this->assertSame(
            ['administrator', 'director', 'employee', 'finance', 'hr', 'manager', 'supervisor'],
            $roles,
        );

        // Administrator holds every permission explicitly (no full-access flag).
        $admin = Role::findByName('administrator', 'web');
        $this->assertSame(Permission::query()->count(), $admin->permissions()->count());
    }

    public function test_administrator_has_core_and_hris_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HrisRoleSeeder::class);

        $administrator = Role::findByName('administrator', 'web');

        $this->assertTrue($administrator->hasPermissionTo('master view'));
        $this->assertTrue($administrator->hasPermissionTo('user create'));
        $this->assertTrue($administrator->hasPermissionTo('hris.employee view-all'));
    }

    public function test_employee_only_has_self_service_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HrisRoleSeeder::class);

        $employee = Role::findByName('employee', 'web');

        $this->assertTrue($employee->hasPermissionTo('hris.attendance check-in'));
        $this->assertFalse($employee->hasPermissionTo('user create'));
        $this->assertFalse($employee->hasPermissionTo('hris.employee view-all'));
    }
}

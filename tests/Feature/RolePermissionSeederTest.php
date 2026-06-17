<?php

namespace Tests\Feature;

use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_exactly_the_five_hris_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HrisRoleSeeder::class);

        $roles = Role::query()->pluck('name')->sort()->values()->all();

        $this->assertSame(
            ['administrator', 'employee', 'hr', 'manager', 'supervisor'],
            $roles,
        );
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

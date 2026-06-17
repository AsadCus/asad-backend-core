<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_role_has_manifest_permissions_after_seeding(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $salesRole = Role::findByName('sales');

        $this->assertTrue($salesRole->hasPermissionTo('manifest view'));
        $this->assertFalse($salesRole->permissions->pluck('name')->contains('manifest create'));
        $this->assertFalse($salesRole->permissions->pluck('name')->contains('manifest edit'));
        $this->assertFalse($salesRole->permissions->pluck('name')->contains('manifest delete'));
    }
}

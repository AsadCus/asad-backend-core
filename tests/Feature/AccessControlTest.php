<?php

namespace Tests\Feature;

use App\Models\GhostUser;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_grants_exactly_its_assigned_permissions(): void
    {
        Permission::findOrCreate('hris.employee view-all', 'web');
        $role = Role::create(['name' => 'data-team', 'guard_name' => 'web']);
        $role->givePermissionTo('hris.employee view-all');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue($user->can('hris.employee view-all'));

        // A permission created AFTER assignment is NOT auto-granted — no magic full-access flag.
        Permission::findOrCreate('hris.brand-new thing', 'web');
        $this->assertFalse($user->fresh()->can('hris.brand-new thing'));
    }

    public function test_ghost_bypasses_every_permission_even_with_no_role_permissions(): void
    {
        $role = Role::create(['name' => 'empty', 'guard_name' => 'web']); // zero permissions
        $user = User::factory()->create();
        $user->assignRole($role);
        GhostUser::create(['user_id' => (int) $user->id]);

        Permission::findOrCreate('hris.employee view-all', 'web');

        $this->assertTrue($user->fresh()->can('hris.employee view-all'));
        $this->assertTrue($user->fresh()->can('anything.at.all'));
    }

    public function test_any_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'disposable', 'guard_name' => 'web']);

        $this->assertTrue(app(RoleService::class)->delete($role->id));
        $this->assertNull(Role::find($role->id));
    }
}

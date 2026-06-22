<?php

namespace Tests\Feature;

use App\Models\GhostUser;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_access_role_grants_all_permissions_dynamically(): void
    {
        $role = Role::create(['name' => 'super', 'guard_name' => 'web', 'is_full_access' => true]);
        $user = User::factory()->create();
        $user->assignRole($role);

        // A permission created AFTER the role still resolves — no snapshot.
        Permission::findOrCreate('hris.brand-new thing', 'web');

        $this->assertTrue($user->can('hris.brand-new thing'));
        $this->assertTrue($user->effectivePermissionNames()->contains('hris.brand-new thing'));
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

    public function test_system_role_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'locked', 'guard_name' => 'web', 'is_system' => true]);

        $this->expectException(ValidationException::class);
        app(RoleService::class)->delete($role->id);
    }

    public function test_non_ghost_cannot_edit_a_full_access_role(): void
    {
        $role = Role::create(['name' => 'top', 'guard_name' => 'web', 'is_full_access' => true]);

        $admin = User::factory()->create(); // not a ghost
        $this->actingAs($admin);

        $this->expectException(ValidationException::class);
        app(RoleService::class)->update(['label' => 'Hacked', 'permissions' => []], $role->id);
    }

    public function test_ghost_can_edit_a_full_access_role(): void
    {
        $role = Role::create(['name' => 'top2', 'guard_name' => 'web', 'is_full_access' => true]);

        $ghost = User::factory()->create();
        GhostUser::create(['user_id' => (int) $ghost->id]);
        $this->actingAs($ghost);

        $updated = app(RoleService::class)->update(['label' => 'Top Tier', 'permissions' => []], $role->id);
        $this->assertSame('Top Tier', $updated->label);
    }
}

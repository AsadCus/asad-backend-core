<?php

namespace Tests\Feature;

use App\Models\GhostUser;
use App\Models\User;
use App\Services\UserRoles\AdminUserService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GhostUserVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ghost_admin_is_hidden_from_admin_datatable_and_admin_role_count(): void
    {
        Role::findOrCreate('superadmin', 'web');

        $visibleAdmin = User::factory()->create();
        $visibleAdmin->assignRole('superadmin');

        $ghostAdmin = User::factory()->create();
        $ghostAdmin->assignRole('superadmin');

        GhostUser::create([
            'user_id' => (int) $ghostAdmin->id,
        ]);

        $rows = app(AdminUserService::class)->getForDataTable();

        $this->assertTrue($rows->contains(fn ($row): bool => (int) $row->id === (int) $visibleAdmin->id));
        $this->assertFalse($rows->contains(fn ($row): bool => (int) $row->id === (int) $ghostAdmin->id));
        $this->assertSame(1, app(UserService::class)->countByRole('superadmin'));
    }

    public function test_change_summary_visibility_flag_is_true_only_for_ghost_user(): void
    {
        $superadminRole = Role::findOrCreate('superadmin', 'web');
        Permission::findOrCreate('user-log view', 'web');
        $superadminRole->givePermissionTo('user-log view');

        $ghostAdmin = User::factory()->create();
        $ghostAdmin->assignRole('superadmin');

        GhostUser::create([
            'user_id' => (int) $ghostAdmin->id,
        ]);

        $normalAdmin = User::factory()->create();
        $normalAdmin->assignRole('superadmin');

        Activity::query()->create([
            'log_name' => 'default',
            'description' => 'Ghost visibility test log',
            'subject_type' => User::class,
            'subject_id' => (int) $ghostAdmin->id,
            'causer_type' => User::class,
            'causer_id' => (int) $ghostAdmin->id,
            'properties' => [
                'old' => ['foo' => 'bar'],
                'attributes' => ['foo' => 'baz'],
            ],
            'event' => 'updated',
            'batch_uuid' => null,
        ]);

        $this->actingAs($ghostAdmin)
            ->get(route('user-logs.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('user-logs/index')
                    ->where('canViewChangeSummary', true)
            );

        $this->actingAs($normalAdmin)
            ->get(route('user-logs.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('user-logs/index')
                    ->where('canViewChangeSummary', false)
            );
    }

    public function test_ghost_admin_can_see_own_row_in_admin_datatable(): void
    {
        Role::findOrCreate('superadmin', 'web');

        $ghostAdmin = User::factory()->create();
        $ghostAdmin->assignRole('superadmin');

        GhostUser::create([
            'user_id' => (int) $ghostAdmin->id,
        ]);

        $normalAdmin = User::factory()->create();
        $normalAdmin->assignRole('superadmin');

        $this->actingAs($ghostAdmin);

        $rows = app(AdminUserService::class)->getForDataTable();

        $this->assertTrue($rows->contains(fn ($row): bool => (int) $row->id === (int) $ghostAdmin->id));
        $this->assertTrue($rows->contains(fn ($row): bool => (int) $row->id === (int) $normalAdmin->id));
    }

    public function test_non_admin_user_cannot_be_marked_as_ghost(): void
    {
        Role::findOrCreate('sales', 'web');

        $salesUser = User::factory()->create();
        $salesUser->assignRole('sales');

        $this->expectException(ValidationException::class);

        GhostUser::create([
            'user_id' => (int) $salesUser->id,
        ]);
    }

    public function test_documentation_visibility_follows_config_and_ghost_status(): void
    {
        Role::findOrCreate('superadmin', 'web');

        Config::set('documentation.visible_to_all_users', false);

        $ghostAdmin = User::factory()->create();
        $ghostAdmin->assignRole('superadmin');

        GhostUser::create([
            'user_id' => (int) $ghostAdmin->id,
        ]);

        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('superadmin');

        $this->actingAs($ghostAdmin)
            ->get(route('documentations.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('auth.can_view_documentation', true)
            );

        $this->actingAs($regularAdmin)
            ->get(route('documentations.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('auth.can_view_documentation', false)
            );

        Config::set('documentation.visible_to_all_users', true);

        $this->actingAs($regularAdmin)
            ->get(route('documentations.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('auth.can_view_documentation', true)
            );
    }
}

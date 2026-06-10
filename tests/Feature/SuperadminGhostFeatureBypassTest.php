<?php

namespace Tests\Feature;

use App\Models\GhostUser;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperadminGhostFeatureBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['customer view', 'quotation view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['sales', 'operations', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Role::findOrCreate('superadmin', 'web')->givePermissionTo(['customer view', 'quotation view']);
        Role::findOrCreate('admin', 'web')->givePermissionTo(['customer view', 'quotation view']);
    }

    private function createSuperadminGhost(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        GhostUser::create([
            'user_id' => (int) $user->id,
        ]);

        return $user;
    }

    /**
     * The GhostUser model only allows creating ghosts for superadmins, so a
     * non-superadmin ghost can only exist when the role is removed later.
     */
    private function createNonSuperadminGhost(): User
    {
        $user = $this->createSuperadminGhost();
        $user->removeRole('superadmin');
        $user->assignRole('admin');

        return $user;
    }

    public function test_disabled_flag_blocks_normal_superadmin(): void
    {
        config(['customer_history.enabled' => false]);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get(route('customer-history.index'))
            ->assertNotFound();
    }

    public function test_disabled_flag_allows_superadmin_ghost(): void
    {
        config(['customer_history.enabled' => false]);

        $this->actingAs($this->createSuperadminGhost())
            ->get(route('customer-history.index'))
            ->assertOk();
    }

    public function test_disabled_flag_blocks_non_superadmin_ghost(): void
    {
        config(['customer_history.enabled' => false]);

        $this->actingAs($this->createNonSuperadminGhost())
            ->get(route('customer-history.index'))
            ->assertNotFound();
    }

    public function test_shared_feature_props_reflect_superadmin_ghost_bypass(): void
    {
        config([
            'email.send_enabled' => false,
            'customer_history.enabled' => false,
            'package_proposal.enabled' => false,
        ]);

        $this->actingAs($this->createSuperadminGhost())
            ->get(route('quotation.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('features.send_email', true)
                    ->where('features.customer_history', true)
                    ->where('features.package_pnl', true)
            );

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get(route('quotation.index'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('features.send_email', false)
                    ->where('features.customer_history', false)
                    ->where('features.package_pnl', false)
            );
    }

    public function test_documentation_visibility_requires_superadmin_ghost_when_flag_disabled(): void
    {
        config(['documentation.visible_to_all_users' => false]);

        $this->actingAs($this->createNonSuperadminGhost())
            ->get(route('quotation.index'))
            ->assertInertia(
                fn (Assert $page) => $page->where('auth.can_view_documentation', false)
            );

        $this->actingAs($this->createSuperadminGhost())
            ->get(route('quotation.index'))
            ->assertInertia(
                fn (Assert $page) => $page->where('auth.can_view_documentation', true)
            );
    }

    public function test_combine_feature_bypass_for_superadmin_ghost(): void
    {
        config(['customer_confirmation.combine_feature_enabled' => false]);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin);
        $this->assertFalse(app(CustomerConfirmationService::class)->isCombineFeatureEnabled());

        $this->actingAs($this->createSuperadminGhost());
        $this->assertTrue(app(CustomerConfirmationService::class)->isCombineFeatureEnabled());
    }
}

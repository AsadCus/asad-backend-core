<?php

namespace Tests\Feature\Tms;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TmsTestCase as TestCase;

class FeatureFlagRouteAccessTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['customer view', 'package-proposal view', 'quotation view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('superadmin', 'web');
        $role->givePermissionTo(['customer view', 'package-proposal view', 'quotation view']);

        $this->user = User::factory()->create();
        $this->user->assignRole('superadmin');
    }

    public function test_customer_history_is_blocked_when_flag_disabled(): void
    {
        config(['customer_history.enabled' => false]);

        $this->actingAs($this->user)
            ->get(route('customer-history.index'))
            ->assertNotFound();
    }

    public function test_customer_history_is_reachable_when_flag_enabled(): void
    {
        config(['customer_history.enabled' => true]);

        $this->actingAs($this->user)
            ->get(route('customer-history.index'))
            ->assertOk();
    }

    public function test_package_proposals_are_blocked_when_flag_disabled(): void
    {
        config(['package_proposal.enabled' => false]);

        $this->actingAs($this->user)
            ->get(route('package-proposals.index'))
            ->assertNotFound();
    }

    public function test_package_proposals_are_reachable_when_flag_enabled(): void
    {
        config(['package_proposal.enabled' => true]);

        $this->actingAs($this->user)
            ->get(route('package-proposals.index'))
            ->assertOk();
    }

    public function test_send_email_route_is_blocked_when_flag_disabled(): void
    {
        config(['email.send_enabled' => false]);

        $this->actingAs($this->user)
            ->get(route('quotation.bulk-email-data', ['ids' => '1']))
            ->assertNotFound();
    }

    public function test_send_email_route_is_reachable_when_flag_enabled(): void
    {
        config(['email.send_enabled' => true]);

        // Passing the feature middleware reaches the controller, which validates
        // the supplied IDs (400 for none) — proving the route is not feature-blocked.
        $this->actingAs($this->user)
            ->get(route('quotation.bulk-email-data', ['ids' => '']))
            ->assertStatus(400);
    }
}

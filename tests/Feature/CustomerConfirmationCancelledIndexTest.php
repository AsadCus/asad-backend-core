<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerConfirmationCancelledIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_customer_index_only_lists_non_holding_groups_with_all_members_cancelled(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('customer view', 'web');
        $user->givePermissionTo('customer view');

        $this->actingAs($user);

        $country = Country::factory()->create([
            'name' => 'Malaysia',
        ]);

        $openPackage = Package::create([
            'package_number' => 'PKG-CANCELLED-INDEX-001',
            'name' => 'Cancelled Index Package',
            'status' => 'open',
            'price_single' => 3500,
            'country_id' => $country->id,
        ]);

        $cancelledNonHoldingGroup = CustomerConfirmation::create([
            'package_id' => $openPackage->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $activeNonHoldingGroup = CustomerConfirmation::create([
            'package_id' => $openPackage->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $cancelledHoldingGroup = CustomerConfirmation::create([
            'package_id' => null,
            'is_holding' => true,
            'created_by' => $user->id,
        ]);

        $createMember = function (CustomerConfirmation $group, string $status, bool $isLeader = true): CustomerConfirmationMember {
            $memberUser = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $memberUser->id,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $group->id,
                'customer_id' => $customer->id,
                'is_leader' => $isLeader,
                'status' => $status,
                'sharing_plan' => 'single',
            ]);
        };

        $cancelledMember = $createMember($cancelledNonHoldingGroup, 'cancelled', true);
        $createMember($activeNonHoldingGroup, 'pending_payment', true);
        $createMember($cancelledHoldingGroup, 'cancelled', true);

        $cancelledMember->forceFill([
            'updated_at' => '2026-04-12 10:00:00',
        ])->save();

        $response = $this->get(route('cancelled-customer.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('confirmed-customer/index')
                ->where('pageTitle', 'Cancelled Customers')
                ->where('indexUrl', route('cancelled-customer.index'))
                ->has('dataGroups', 1)
                ->where('dataGroups.0.id', $cancelledNonHoldingGroup->id)
                ->where('dataGroups.0.package_country', 'Malaysia')
                ->where('dataGroups.0.refund_cancel_date', '12 April 2026')
            );
    }
}

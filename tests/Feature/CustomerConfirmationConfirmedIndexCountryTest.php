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

class CustomerConfirmationConfirmedIndexCountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_customer_index_includes_package_country_in_data_groups(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('customer view', 'web');
        $user->givePermissionTo('customer view');

        $this->actingAs($user);

        $country = Country::factory()->create([
            'name' => 'Indonesia',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-CONFIRMED-COUNTRY-001',
            'name' => 'Confirmed Country Package',
            'status' => 'open',
            'price_single' => 4000,
            'country_id' => $country->id,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'is_holding' => false,
            'created_by' => $user->id,
        ]);

        $memberUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $memberUser->id,
        ]);

        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $this->get(route('confirmed-customer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('confirmed-customer/index')
                ->has('dataGroups', 1)
                ->where('dataGroups.0.id', $group->id)
                ->where('dataGroups.0.package_country', 'Indonesia')
            );
    }
}

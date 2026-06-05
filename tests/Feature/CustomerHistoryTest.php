<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('customer view', 'web');
        $role = Role::findOrCreate('superadmin', 'web');
        $role->givePermissionTo('customer view');
        Role::findOrCreate('customer', 'web');

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->assignRole('superadmin');

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_index_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('customer-history.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('customer-history/index'));
    }

    public function test_index_search_filters_customers(): void
    {
        $customerUser = User::factory()->create([
            'name' => 'John Doe Travel',
            'email' => 'johndoe@example.com',
        ]);
        $customerUser->assignRole('customer');
        Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-2026-0001',
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);
        $otherUser->assignRole('customer');
        Customer::create([
            'user_id' => $otherUser->id,
            'customer_number' => 'CUST-2026-0002',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('customer-history.index', ['search' => 'John Doe']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customer-history/index')
            ->has('customers', 1)
            ->where('customers.0.name', 'John Doe Travel')
        );
    }

    public function test_show_returns_customer_travel_history(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-2026-0010',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-HIST-001',
            'name' => 'Umrah Gold History',
            'status' => 'completed',
            'total_seats' => 30,
            'seats_left' => 0,
            'departure_date' => '2026-01-10',
            'return_date' => '2026-01-20',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => '2025-12-01',
            'created_by' => $this->authorizedUser->id,
        ]);

        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'sharing_plan' => 'double',
            'relationship' => 'self',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson(route('customer-history.show', $customer->id));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'package_name' => 'Umrah Gold History',
            'package_number' => 'PKG-HIST-001',
            'package_status' => 'completed',
            'is_leader' => true,
            'member_status' => 'confirmed',
            'sharing_plan' => 'double',
            'relationship' => 'self',
        ]);
    }

    public function test_show_returns_records_sorted_newest_first(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-2026-0020',
        ]);

        $olderPackage = Package::create([
            'package_number' => 'PKG-OLD-001',
            'name' => 'Older Package',
            'status' => 'completed',
            'total_seats' => 20,
            'seats_left' => 0,
        ]);

        $newerPackage = Package::create([
            'package_number' => 'PKG-NEW-001',
            'name' => 'Newer Package',
            'status' => 'open',
            'total_seats' => 20,
            'seats_left' => 10,
        ]);

        $this->travel(-1)->years();
        $olderConfirmation = CustomerConfirmation::create([
            'package_id' => $olderPackage->id,
            'date_of_application' => '2025-06-01',
            'created_by' => $this->authorizedUser->id,
        ]);
        $this->travelBack();

        $newerConfirmation = CustomerConfirmation::create([
            'package_id' => $newerPackage->id,
            'date_of_application' => '2026-03-01',
            'created_by' => $this->authorizedUser->id,
        ]);

        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $olderConfirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
        ]);

        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $newerConfirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => false,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson(route('customer-history.show', $customer->id));

        $response->assertStatus(200);
        $response->assertJsonCount(2);

        $records = $response->json();
        $this->assertEquals('Newer Package', $records[0]['package_name']);
        $this->assertEquals('Older Package', $records[1]['package_name']);
    }

    public function test_show_returns_empty_for_customer_with_no_history(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-2026-0030',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson(route('customer-history.show', $customer->id));

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_unauthorized_user_cannot_access_index(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('customer-history.index'));

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_access_show(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-2026-0040',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->getJson(route('customer-history.show', $customer->id));

        $response->assertStatus(403);
    }
}

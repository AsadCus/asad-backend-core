<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Enquiry;
use App\Models\EnquiryRemark;
use App\Models\GeneralEnquiry;
use App\Models\PrivateEnquiry;
use App\Models\User;
use App\Services\GeneralEnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions and role
        $permissions = [
            'general-enquiry view',
            'general-enquiry create',
            'general-enquiry edit',
            'general-enquiry delete',
            'private-enquiry view',
            'private-enquiry create',
            'private-enquiry edit',
            'private-enquiry delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo($permissions);

        Role::findOrCreate('customer', 'web');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');
    }

    /**
     * Helper to build a valid member payload with all required biodata fields.
     */
    private function memberPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Member',
            'email' => 'member@example.com',
            'contact_number' => '0123456789',
            'nric_number' => 'S1234567A',
            'address' => '123 Test Street, 12345',
            'is_leader' => true,
            'nationality' => 'Malaysian',
            'passport_number' => 'A12345678',
            'passport_issue_date' => '2023-01-01',
            'passport_expiry_date' => '2033-01-01',
            'passport_place_of_issue' => 'Kuala Lumpur',
            'gender' => 'male',
            'marital_status' => 'single',
            'date_of_birth' => '1990-05-15',
            'place_of_birth' => 'Kuala Lumpur',
            'first_time_umrah' => true,
            'has_chronic_disease' => false,
            'chronic_disease_details' => null,
        ], $overrides);
    }

    public function test_creating_general_enquiry_creates_parent_enquiry(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('general-enquiries.store'), [
            'name' => 'John Doe',
            'contact_number' => '0123456789',
            'email' => 'john@example.com',
            'preferred_destinations' => 'Japan, Korea',
            'preferred_travelling_date' => '2026-06-01',
            'no_of_adults' => 2,
            'no_of_children' => 1,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('enquiries', [
            'type' => 'general',
            'status' => 'new_lead',
            'name' => 'John Doe',
            'contact_number' => '0123456789',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('general_enquiries', [
            'preferred_destinations' => 'Japan, Korea',
            'no_of_adults' => 2,
            'no_of_children' => 1,
        ]);

        $generalEnquiry = GeneralEnquiry::first();
        $this->assertNotNull($generalEnquiry->enquiry_id);
        $this->assertNotNull($generalEnquiry->enquiry);
        $this->assertEquals('new_lead', $generalEnquiry->enquiry->status->value);
    }

    public function test_creating_private_enquiry_creates_parent_enquiry(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('private-enquiries.store'), [
            'name' => 'Jane Smith',
            'contact_number' => '0198765432',
            'email' => 'jane@example.com',
            'passport_expiry_date' => '2027-12-31',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-15',
            'no_of_pax' => 4,
            'no_of_children' => 0,
            'airline' => 'MAS',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'Makkah',
            'no_of_nights_makkah' => '5',
            'hotel_makkah' => 'Grand Hotel',
            'meals_makkah' => 'Full Board',
            'no_of_nights_madinah' => '5',
            'hotel_madinah' => 'Madinah Hotel',
            'meals_madinah' => 'Half Board',
            'land_transfer' => 'VIP Bus',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => 'No',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('enquiries', [
            'type' => 'private',
            'status' => 'new_lead',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $privateEnquiry = PrivateEnquiry::first();
        $this->assertNotNull($privateEnquiry->enquiry_id);
    }

    public function test_all_enquiries_index_page_loads(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('enquiries.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('enquiries/index')
                ->has('data.enquiriesForDatatable')
                ->has('data.statusOptions')
        );
    }

    public function test_general_enquiries_index_includes_status(): void
    {
        $this->actingAs($this->adminUser);

        // Create one via the service
        $service = app(GeneralEnquiryService::class);
        $service->store([
            'name' => 'Test User',
            'contact_number' => '0111111111',
            'email' => 'test@test.com',
            'preferred_destinations' => 'Japan',
            'preferred_travelling_date' => '2026-06-01',
            'no_of_adults' => 2,
            'no_of_children' => 0,
        ]);

        $response = $this->get(route('general-enquiries.index'));
        $response->assertOk();

        $response->assertInertia(
            fn ($page) => $page
                ->component('general-enquiries/index')
                ->has('data.enquiriesForDatatable', 1)
                ->where('data.enquiriesForDatatable.0.status', 'new_lead')
                ->where('data.enquiriesForDatatable.0.status_label', 'New Lead')
        );
    }

    public function test_enquiry_status_can_transition_forward(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Test',
            'contact_number' => '012345',
            'email' => 'test@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->put(route('enquiries.transition-status', $enquiry->id), [
            'status' => 'contacted',
        ]);

        $response->assertRedirect();
        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Contacted, $enquiry->status);
        $this->assertSame($this->adminUser->id, $enquiry->handled_by);

        $this->assertDatabaseHas('enquiry_remarks', [
            'enquiry_id' => $enquiry->id,
            'created_by' => $this->adminUser->id,
            'status_at_time' => EnquiryStatus::Contacted->value,
            'remark' => 'Status updated from New Lead to Contacted.',
        ]);
    }

    public function test_enquiry_status_cannot_skip_steps(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Test',
            'contact_number' => '012345',
            'email' => 'test@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        // Try to skip from new_lead to confirmed
        $response = $this->put(route('enquiries.transition-status', $enquiry->id), [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(422);
    }

    public function test_enquiry_status_full_workflow(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Workflow Test',
            'contact_number' => '012345',
            'email' => 'flow@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        // new_lead -> contacted
        $this->put(route('enquiries.transition-status', $enquiry->id), ['status' => 'contacted'])
            ->assertRedirect();

        // contacted -> negotiating
        $this->put(route('enquiries.transition-status', $enquiry->id), ['status' => 'negotiating'])
            ->assertRedirect();

        // negotiating -> confirmed (must use the confirm endpoint with member data)
        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Workflow Test',
                    'email' => 'flow@test.com',
                    'contact_number' => '012345',
                ]),
            ],
        ])->assertRedirect();

        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Confirmed, $enquiry->status);
        $this->assertEquals(3, EnquiryRemark::where('enquiry_id', $enquiry->id)->count());
    }

    public function test_confirm_endpoint_creates_customer_group(): void
    {
        $this->actingAs($this->adminUser);

        // Create enquiry in negotiating state (so it can transition to confirmed)
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'Group Leader',
            'contact_number' => '012345',
            'email' => 'leader@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Group Leader',
                    'email' => 'leader@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Participant One',
                    'email' => 'participant1@test.com',
                    'contact_number' => '098765',
                    'nric_number' => 'S9876543B',
                    'passport_number' => 'B87654321',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $response->assertRedirect();

        // Check customer group was created
        $this->assertDatabaseHas('customer_groups', [
            'enquiry_id' => $enquiry->id,
        ]);

        // Check leader
        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($group);
        $leader = $group->leader();
        $this->assertNotNull($leader);
        $this->assertTrue($leader->is_leader);

        // Check participant
        $this->assertEquals(2, $group->members()->count());

        // Check enquiry status moved to confirmed
        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Confirmed, $enquiry->status);
    }

    public function test_confirm_endpoint_overwrites_handled_by_with_confirming_user(): void
    {
        $this->actingAs($this->adminUser);

        $previousHandler = User::factory()->create();

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'Handled By Confirm Test',
            'contact_number' => '012345',
            'email' => 'handled-confirm@test.com',
            'created_by' => $this->adminUser->id,
            'handled_by' => $previousHandler->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Handled By Confirm Test',
                    'email' => 'handled-confirm@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
            ],
        ])->assertRedirect();

        $enquiry->refresh();

        $this->assertEquals(EnquiryStatus::Confirmed, $enquiry->status);
        $this->assertSame($this->adminUser->id, $enquiry->handled_by);

        $this->assertDatabaseHas('enquiry_remarks', [
            'enquiry_id' => $enquiry->id,
            'created_by' => $this->adminUser->id,
            'status_at_time' => EnquiryStatus::Confirmed->value,
            'remark' => 'Status updated from Negotiating to Confirmed.',
        ]);
    }

    public function test_confirm_endpoint_does_not_fail_when_already_confirmed(): void
    {
        $this->actingAs($this->adminUser);

        // Enquiry already in confirmed state
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Already Confirmed',
            'contact_number' => '012345',
            'email' => 'alreadyconfirmed@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Already Confirmed',
                    'email' => 'alreadyconfirmed@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $response->assertRedirect();

        // Status should remain confirmed
        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Confirmed, $enquiry->status);

        // Customer group should still be created
        $this->assertDatabaseHas('customer_groups', [
            'enquiry_id' => $enquiry->id,
        ]);
    }

    public function test_standalone_group_creation_with_optional_enquiry_id(): void
    {
        $this->actingAs($this->adminUser);

        // Create a confirmed enquiry without a group
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Link Test',
            'contact_number' => '012345',
            'email' => 'linktest@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->post(route('enquiries.create-customer-group'), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Link Test Leader',
                    'email' => 'linkleader@test.com',
                    'contact_number' => '0123456789',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $response->assertRedirect();

        // Customer group should be linked to the enquiry
        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($group);
        $this->assertEquals($enquiry->id, $group->enquiry_id);
    }

    public function test_available_enquiries_endpoint_returns_confirmed_without_group(): void
    {
        $this->actingAs($this->adminUser);

        // Confirmed enquiry without group — should appear
        $confirmedNoGroup = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Available',
            'contact_number' => '012345',
            'email' => 'available@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        // Confirmed enquiry with group — should NOT appear
        $confirmedWithGroup = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Has Group',
            'contact_number' => '012345',
            'email' => 'hasgroup@test.com',
            'created_by' => $this->adminUser->id,
        ]);
        CustomerGroup::create([
            'enquiry_id' => $confirmedWithGroup->id,
            'created_by' => $this->adminUser->id,
        ]);

        // Non-confirmed enquiry — should NOT appear
        Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Not Confirmed',
            'contact_number' => '012345',
            'email' => 'notconfirmed@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->get(route('enquiries.available-enquiries'));
        $response->assertOk();

        $data = $response->json();
        $values = array_column($data, 'value');

        $this->assertContains($confirmedNoGroup->id, $values);
        $this->assertNotContains($confirmedWithGroup->id, $values);
    }

    public function test_list_customers_endpoint_returns_users_with_customers(): void
    {
        $this->actingAs($this->adminUser);

        // User with customer record — should appear
        $customerUser = User::factory()->create([
            'name' => 'Has Customer',
            'email' => 'hascustomer@test.com',
        ]);
        Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'C-LIST-001',
        ]);

        // User without customer record — should NOT appear
        User::factory()->create([
            'name' => 'No Customer',
            'email' => 'nocustomer@test.com',
        ]);

        $response = $this->get(route('enquiries.list-customers'));
        $response->assertOk();

        $data = $response->json();
        $emails = array_map(fn ($item) => $item['email'] ?? '', $data);

        $this->assertContains('hascustomer@test.com', $emails);
        $this->assertNotContains('nocustomer@test.com', $emails);
    }

    public function test_confirm_creates_user_and_customer_if_not_exists(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'New Customer',
            'contact_number' => '012345',
            'email' => 'newcust@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'New Customer',
                    'email' => 'newcust@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
            ],
        ]);

        // A user with this email should now exist
        $this->assertDatabaseHas('users', [
            'email' => 'newcust@test.com',
        ]);

        // A customer linked to that user should also exist
        $user = User::where('email', 'newcust@test.com')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
        ]);
    }

    public function test_confirm_reuses_existing_customer(): void
    {
        $this->actingAs($this->adminUser);

        // Pre-create a user + customer
        $existingUser = User::factory()->create(['email' => 'existing@test.com']);
        $existingCustomer = Customer::create([
            'user_id' => $existingUser->id,
            'customer_number' => 'C00001',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'Existing Customer',
            'contact_number' => '012345',
            'email' => 'existing@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Existing Customer',
                    'email' => 'existing@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
            ],
        ]);

        // Should reuse the existing customer, not create a new one
        $this->assertEquals(1, Customer::where('user_id', $existingUser->id)->count());

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $leader = $group->leader();
        $this->assertEquals($existingCustomer->id, $leader->customer_id);
    }

    public function test_get_for_show_returns_enquiry_data(): void
    {
        $this->actingAs($this->adminUser);

        // Create via service
        $service = app(GeneralEnquiryService::class);
        $service->store([
            'name' => 'Show Test',
            'contact_number' => '0111111111',
            'email' => 'show@test.com',
            'preferred_destinations' => 'Japan',
            'preferred_travelling_date' => '2026-06-01',
            'no_of_adults' => 2,
            'no_of_children' => 0,
        ]);

        $enquiry = Enquiry::first();

        $response = $this->get(route('enquiries.get-for-show', $enquiry->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'enquiry' => ['id', 'type', 'status', 'status_label'],
            'child',
            'customerGroup',
        ]);
    }

    public function test_general_enquiry_get_for_show_returns_json(): void
    {
        $this->actingAs($this->adminUser);

        $service = app(GeneralEnquiryService::class);
        $service->store([
            'name' => 'GE Show',
            'contact_number' => '012',
            'email' => 'ge@test.com',
            'preferred_destinations' => 'Korea',
            'preferred_travelling_date' => '2026-07-01',
            'no_of_adults' => 1,
            'no_of_children' => 0,
        ]);

        $ge = GeneralEnquiry::first();

        $response = $this->get(route('general-enquiries.get-for-show', $ge->id));
        $response->assertOk();
        $response->assertJsonFragment(['name' => 'GE Show']);
    }

    public function test_private_enquiry_package_prefill_maps_only_non_empty_fields(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'Private Prefill',
            'contact_number' => '0170001111',
            'email' => 'private-prefill@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        PrivateEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'passport_expiry_date' => '2030-12-31',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'no_of_pax' => 3,
            'no_of_children' => 1,
            'airline' => '',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'makkah',
            'no_of_nights_makkah' => '4',
            'hotel_makkah' => 'Makkah Grand',
            'meals_makkah' => 'Full Board',
            'no_of_nights_madinah' => '3',
            'hotel_madinah' => '',
            'meals_madinah' => '',
            'land_transfer' => '',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => 'No',
            'other_remarks' => '',
        ]);

        $response = $this->get(route('enquiries.package-prefill', $enquiry->id));

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Private - Private Prefill',
            'status' => 'open',
            'total_seats' => 4,
            'seats_left' => 4,
        ]);
        $response->assertJsonMissingPath('airline');
        $response->assertJsonMissingPath('vehicle_type');
        $response->assertJsonPath('not_included', "Mutawif service not requested\nUmrah course not requested\nUmrah official not requested\nMeet & greet not requested\nMutawiffah/Ustazah Rawdah not requested");
        $response->assertJsonPath('remarks', "Private enquiry details:\nClass: Economy\nMakkah/Madinah first: makkah\nNights in Makkah: 4\nNights in Madinah: 3\nWheelchair support: No");
        $response->assertJsonPath('accommodations.0.location', 'Makkah');
    }

    public function test_search_customers_returns_results(): void
    {
        $this->actingAs($this->adminUser);

        // Create a user with customer role
        $user = User::factory()->create([
            'name' => 'Customer Search Test',
            'email' => 'searchtest@test.com',
        ]);
        $customerRole = Role::findOrCreate('customer', 'web');
        $user->assignRole('customer');
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C00099',
        ]);

        $response = $this->get(route('enquiries.search-customers', ['q' => 'searchtest']));
        $response->assertOk();
        $response->assertJsonFragment(['email' => 'searchtest@test.com']);
    }

    public function test_guests_cannot_access_enquiry_pages(): void
    {
        $this->get(route('enquiries.index'))->assertRedirect(route('login'));
        $this->get(route('general-enquiries.index'))->assertRedirect(route('login'));
    }

    public function test_enquiry_enum_has_correct_transitions(): void
    {
        $this->assertTrue(EnquiryStatus::NewLead->canTransitionTo(EnquiryStatus::Contacted));
        $this->assertTrue(EnquiryStatus::Contacted->canTransitionTo(EnquiryStatus::Negotiating));
        $this->assertTrue(EnquiryStatus::Negotiating->canTransitionTo(EnquiryStatus::Confirmed));

        $this->assertFalse(EnquiryStatus::NewLead->canTransitionTo(EnquiryStatus::Negotiating));
        $this->assertFalse(EnquiryStatus::NewLead->canTransitionTo(EnquiryStatus::Confirmed));
        $this->assertFalse(EnquiryStatus::Contacted->canTransitionTo(EnquiryStatus::Confirmed));
    }

    public function test_deleting_general_enquiry_deletes_parent(): void
    {
        $this->actingAs($this->adminUser);

        $service = app(GeneralEnquiryService::class);
        $service->store([
            'name' => 'Delete Test',
            'contact_number' => '012',
            'email' => 'del@test.com',
            'preferred_destinations' => 'Maldives',
            'preferred_travelling_date' => '2026-08-01',
            'no_of_adults' => 2,
            'no_of_children' => 1,
        ]);

        $ge = GeneralEnquiry::first();
        $enquiryId = $ge->enquiry_id;

        $this->delete(route('general-enquiries.destroy', $ge->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('general_enquiries', ['id' => $ge->id]);
        $this->assertDatabaseMissing('enquiries', ['id' => $enquiryId]);
    }

    public function test_standalone_customer_group_creation_without_enquiry(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('enquiries.create-customer-group'), [
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Standalone Leader',
                    'email' => 'standalone@test.com',
                    'contact_number' => '0123456789',
                    'nric_number' => '901231-14-5678',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Standalone Member',
                    'email' => 'member@test.com',
                    'contact_number' => '0198765432',
                    'nric_number' => 'S9876543C',
                    'passport_number' => 'C11111111',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $response->assertRedirect();

        // Customer group created without enquiry_id
        $group = CustomerGroup::whereNull('enquiry_id')->first();
        $this->assertNotNull($group);
        $this->assertNull($group->enquiry_id);
        $this->assertCount(2, $group->members);

        // Leader created as user + customer
        $leaderUser = User::where('email', 'standalone@test.com')->first();
        $this->assertNotNull($leaderUser);
        $this->assertEquals('Standalone Leader', $leaderUser->name);

        $leaderCustomer = Customer::where('user_id', $leaderUser->id)->first();
        $this->assertNotNull($leaderCustomer);

        // Member created as user + customer
        $memberUser = User::where('email', 'member@test.com')->first();
        $this->assertNotNull($memberUser);
    }

    public function test_standalone_group_validates_member_required_fields(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('enquiries.create-customer-group'), [
            'members' => [
                [
                    'name' => '',
                    'email' => '',
                    'contact_number' => '',
                    'is_leader' => true,
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'members.0.name',
            'members.0.email',
            'members.0.contact_number',
        ]);
    }

    public function test_standalone_group_updates_existing_customer(): void
    {
        $this->actingAs($this->adminUser);

        // Pre-create a user and customer
        $existingUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'existing@test.com',
            'contact' => '000000',
        ]);
        $existingUser->assignRole('customer');
        Customer::create([
            'user_id' => $existingUser->id,
            'customer_number' => 'C-TEST-001',
        ]);

        // Create a standalone group with same email - should update, not duplicate
        $this->post(route('enquiries.create-customer-group'), [
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Updated Name',
                    'email' => 'existing@test.com',
                    'contact_number' => '999999',
                    'nric_number' => '901231-14-5678',
                    'is_leader' => true,
                ]),
            ],
        ]);

        // Should NOT create a new user
        $this->assertEquals(
            1,
            User::where('email', 'existing@test.com')->count()
        );

        // Existing user should be updated
        $existingUser->refresh();
        $this->assertEquals('Updated Name', $existingUser->name);
        $this->assertEquals('999999', $existingUser->contact);

        // Customer NRIC should be updated
        $customer = Customer::where('user_id', $existingUser->id)->first();
        $this->assertEquals('901231-14-5678', $customer->nric_number);
    }

    public function test_customer_index_loads_without_data_groups(): void
    {
        $this->actingAs($this->adminUser);

        // Create required roles and permissions
        Role::findOrCreate('sales', 'web');
        foreach (['customer view', 'customer create', 'customer edit', 'customer delete'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $this->adminUser->givePermissionTo(['customer view', 'customer create', 'customer edit', 'customer delete']);

        $response = $this->get(route('customer.index'));
        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('customer/index')
                ->missing('dataGroups')
        );
    }

    public function test_confirmed_customer_index_includes_data_groups(): void
    {
        $this->actingAs($this->adminUser);

        // Create required roles and permissions
        foreach (['customer view'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $this->adminUser->givePermissionTo(['customer view']);

        $response = $this->get(route('confirmed-customer.index'));
        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('confirmed-customer/index')
                ->has('dataGroups')
                ->has('packageOptions')
        );
    }
}

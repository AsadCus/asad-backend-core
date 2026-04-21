<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\Enquiry;
use App\Models\GeneralEnquiry;
use App\Models\Package;
use App\Models\PrivateEnquiry;
use App\Models\User;
use App\Services\GeneralEnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

        Role::findOrCreate('sales', 'web');
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
        $country = Country::factory()->create();

        $response = $this->post(route('general-enquiries.store'), [
            'name' => 'John Doe',
            'contact_number' => '0123456789',
            'email' => 'john@example.com',
            'country_id' => $country->id,
            'preferred_destinations' => 'Japan, Korea',
            'preferred_travelling_date' => '2026-06-01',
            'no_of_adults' => 2,
            'no_of_children' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

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
        $country = Country::factory()->create();

        $response = $this->post(route('private-enquiries.store'), [
            'name' => 'Jane Smith',
            'contact_number' => '0198765432',
            'email' => 'jane@example.com',
            'country_id' => $country->id,
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
            'need_wheelchair' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('enquiries', [
            'type' => 'private',
            'status' => 'new_lead',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $privateEnquiry = PrivateEnquiry::first();
        $this->assertNotNull($privateEnquiry->enquiry_id);
    }

    public function test_creating_private_enquiry_without_passport_expiry_date_is_allowed(): void
    {
        $this->actingAs($this->adminUser);
        $country = Country::factory()->create();

        $response = $this->post(route('private-enquiries.store'), [
            'name' => 'Optional Passport Date',
            'contact_number' => '0181234567',
            'email' => 'optional-passport@example.com',
            'country_id' => $country->id,
            'departure_date' => '2026-07-01',
            'return_date' => '2026-07-15',
            'no_of_pax' => 2,
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
            'need_wheelchair' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $createdPrivateEnquiry = PrivateEnquiry::query()->latest('id')->first();

        $this->assertNotNull($createdPrivateEnquiry);
        $this->assertNull($createdPrivateEnquiry?->passport_expiry_date);
    }

    public function test_public_private_enquiry_submission_allows_missing_passport_expiry_date(): void
    {
        Config::set('data_scope.mode', 'country');
        $country = Country::factory()->create(['name' => 'Malaysia']);

        $response = $this->post(route('private-enquiries.public.store'), [
            'country_slug' => 'malaysia',
            'name' => 'Public Optional Passport Date',
            'contact_number' => '0177654321',
            'email' => 'public-optional-passport@example.com',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-12',
            'no_of_pax' => 3,
            'no_of_children' => 1,
            'airline' => 'MAS',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'Makkah',
            'no_of_nights_makkah' => '4',
            'hotel_makkah' => 'Makkah Tower',
            'meals_makkah' => 'Half Board',
            'no_of_nights_madinah' => '4',
            'hotel_madinah' => 'Madinah Suites',
            'meals_madinah' => 'Half Board',
            'land_transfer' => 'Sedan (2 Pax)',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $createdPrivateEnquiry = PrivateEnquiry::query()->latest('id')->first();

        $this->assertNotNull($createdPrivateEnquiry);
        $this->assertNull($createdPrivateEnquiry?->passport_expiry_date);
        $this->assertSame($country->id, $createdPrivateEnquiry?->enquiry?->country_id);
    }

    public function test_public_general_enquiry_form_without_country_slug_shows_country_selector(): void
    {
        Country::factory()->create(['name' => 'Malaysia']);
        Country::factory()->create(['name' => 'Indonesia']);

        $response = $this->get(route('general-enquiries.public.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('general-enquiries/public/index')
            ->where('showCountrySelector', true)
            ->where('selectedCountry', null)
            ->has('countryOptions', 2));
    }

    public function test_public_general_enquiry_form_with_invalid_country_slug_redirects_to_selector(): void
    {
        $response = $this->get(route('general-enquiries.public.create', ['country' => 'invalid-country']));

        $response->assertRedirect(route('general-enquiries.public.create'));
    }

    public function test_public_private_enquiry_store_with_invalid_country_slug_redirects_to_selector(): void
    {
        $response = $this->post(route('private-enquiries.public.store'), [
            'country_slug' => 'invalid-country',
            'name' => 'Invalid Country Slug',
            'contact_number' => '0177000000',
            'email' => 'invalid-country@example.com',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-12',
            'no_of_pax' => 3,
            'no_of_children' => 1,
            'airline' => 'MAS',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'Makkah',
            'no_of_nights_makkah' => '4',
            'hotel_makkah' => 'Makkah Tower',
            'meals_makkah' => 'Half Board',
            'no_of_nights_madinah' => '4',
            'hotel_madinah' => 'Madinah Suites',
            'meals_madinah' => 'Half Board',
            'land_transfer' => 'Sedan (2 Pax)',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => false,
        ]);

        $response->assertRedirect(route('private-enquiries.public.create'));
        $this->assertDatabaseCount('private_enquiries', 0);
    }

    public function test_all_enquiries_index_page_loads(): void
    {
        $this->actingAs($this->adminUser);
        $country = Country::factory()->create();
        Branch::create([
            'name' => 'Kuala Lumpur',
            'country_id' => $country->id,
        ]);

        $response = $this->get(route('enquiries.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('enquiries/index')
                ->has('data.enquiriesForDatatable')
                ->has('data.statusOptions')
                ->has('data.packageOptions')
                ->has('data.countryOptions', 1)
                ->has('data.branchOptions', 1)
                ->has('data.scopeMode')
        );
    }

    public function test_private_enquiries_index_page_includes_country_options(): void
    {
        $this->actingAs($this->adminUser);
        $country = Country::factory()->create();
        Branch::create([
            'name' => 'Penang',
            'country_id' => $country->id,
        ]);

        $response = $this->get(route('private-enquiries.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('private-enquiries/index')
                ->has('data.enquiriesForDatatable')
                ->has('data.countryOptions', 1)
                ->has('data.branchOptions', 1)
                ->has('data.packageOptions')
                ->has('data.scopeMode')
        );
    }

    public function test_general_enquiries_index_includes_status(): void
    {
        $this->actingAs($this->adminUser);
        $country = Country::factory()->create();
        Branch::create([
            'name' => 'Johor Bahru',
            'country_id' => $country->id,
        ]);

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
                ->has('data.packageOptions')
                ->has('data.countryOptions', 1)
                ->has('data.branchOptions', 1)
                ->has('data.scopeMode')
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

        $package = Package::create([
            'package_number' => 'PKG-FLOW-001',
            'name' => 'Workflow Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Workflow Test',
            'contact_number' => '012345',
            'email' => 'flow@test.com',
            'package_id' => $package->id,
            'created_by' => $this->adminUser->id,
        ]);

        // new_lead -> contacted
        $this->put(route('enquiries.transition-status', $enquiry->id), ['status' => 'contacted'])
            ->assertRedirect();

        // contacted -> confirmed (must use the confirm endpoint with member data)
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
    }

    public function test_confirm_endpoint_creates_customer_confirmation(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'package_number' => 'PKG-CONFIRM-001',
            'name' => 'Confirm Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        // Create enquiry in contacted state (so it can transition to confirmed)
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Group Leader',
            'contact_number' => '012345',
            'email' => 'leader@test.com',
            'package_id' => $package->id,
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

        // Check customer confirmation was created
        $this->assertDatabaseHas('customer_confirmations', [
            'enquiry_id' => $enquiry->id,
        ]);

        // Check leader
        $group = CustomerConfirmation::where('enquiry_id', $enquiry->id)->first();
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

    public function test_transition_to_confirmed_creates_customer_confirmation_for_general_enquiry(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'package_number' => 'PKG-TRANSITION-001',
            'name' => 'Transition Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Transition Confirm Test',
            'contact_number' => '0123123123',
            'email' => 'transition-confirm@test.com',
            'package_id' => $package->id,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->put(route('enquiries.transition-status', $enquiry->id), [
            'status' => 'confirmed',
        ]);

        $response->assertRedirect();

        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Confirmed, $enquiry->status);

        $group = CustomerConfirmation::with('members')
            ->where('enquiry_id', $enquiry->id)
            ->first();

        $this->assertNotNull($group);
        $this->assertSame($package->id, (int) $group->package_id);
        $this->assertCount(1, $group->members);
    }

    public function test_general_enquiry_confirmation_requires_package_selection(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'No Package General',
            'contact_number' => '0120000000',
            'email' => 'no-package-general@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->from(route('general-enquiries.index'))
            ->post(route('enquiries.confirm', $enquiry->id), [
                'enquiry_id' => $enquiry->id,
                'date_of_application' => '2026-01-01',
                'members' => [
                    $this->memberPayload([
                        'name' => 'No Package General',
                        'email' => 'no-package-general@test.com',
                        'contact_number' => '0120000000',
                        'is_leader' => true,
                    ]),
                ],
            ]);

        $response->assertRedirect(route('general-enquiries.index'));
        $response->assertSessionHasErrors(['package_id']);

        $this->assertDatabaseMissing('customer_confirmations', [
            'enquiry_id' => $enquiry->id,
        ]);

        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Contacted, $enquiry->status);
    }

    public function test_confirm_endpoint_overwrites_handled_by_with_confirming_user(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'package_number' => 'PKG-HANDLED-CONFIRM-001',
            'name' => 'Handled Confirm Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        $previousHandler = User::factory()->create();

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Handled By Confirm Test',
            'contact_number' => '012345',
            'email' => 'handled-confirm@test.com',
            'created_by' => $this->adminUser->id,
            'handled_by' => $previousHandler->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'package_id' => $package->id,
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
    }

    public function test_confirm_endpoint_does_not_fail_when_already_confirmed(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'package_number' => 'PKG-ALREADY-CONFIRMED-001',
            'name' => 'Already Confirmed Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

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
            'package_id' => $package->id,
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

        // Customer confirmation should still be created
        $this->assertDatabaseHas('customer_confirmations', [
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

        $response = $this->post(route('enquiries.create-customer-confirmation'), [
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

        // Customer confirmation should be linked to the enquiry
        $group = CustomerConfirmation::where('enquiry_id', $enquiry->id)->first();
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
        CustomerConfirmation::create([
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

        $package = Package::create([
            'package_number' => 'PKG-NEW-CUSTOMER-001',
            'name' => 'New Customer Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'New Customer',
            'contact_number' => '012345',
            'email' => 'newcust@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'package_id' => $package->id,
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

        $package = Package::create([
            'package_number' => 'PKG-REUSE-CUSTOMER-001',
            'name' => 'Reuse Customer Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        // Pre-create a user + customer
        $existingUser = User::factory()->create(['email' => 'existing@test.com']);
        $existingCustomer = Customer::create([
            'user_id' => $existingUser->id,
            'customer_number' => 'C00001',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Existing Customer',
            'contact_number' => '012345',
            'email' => 'existing@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'package_id' => $package->id,
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

        $group = CustomerConfirmation::where('enquiry_id', $enquiry->id)->first();
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
            'child' => ['package_id', 'branch_id', 'country_id'],
            'customerConfirmation',
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
        $response->assertJsonStructure([
            'package_id',
            'branch_id',
            'country_id',
        ]);
    }

    public function test_private_enquiry_get_for_show_includes_scope_identifiers(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Private Show',
            'contact_number' => '0130000000',
            'email' => 'private-show@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $privateEnquiry = PrivateEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'passport_expiry_date' => '2030-12-31',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'no_of_pax' => 2,
            'no_of_children' => 0,
            'airline' => 'MAS',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'Makkah',
            'no_of_nights_makkah' => '4',
            'hotel_makkah' => 'Hotel Makkah',
            'meals_makkah' => 'Breakfast',
            'no_of_nights_madinah' => '4',
            'hotel_madinah' => 'Hotel Madinah',
            'meals_madinah' => 'Breakfast',
            'land_transfer' => 'Bus',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => false,
        ]);

        $response = $this->get(route('private-enquiries.get-for-show', $privateEnquiry->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'branch_id',
            'country_id',
        ]);
    }

    public function test_private_enquiry_package_prefill_maps_only_non_empty_fields(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Contacted->value,
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
            'need_wheelchair' => false,
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

    public function test_private_enquiry_package_prefill_sets_country_from_enquiry_country_in_country_mode(): void
    {
        $this->actingAs($this->adminUser);
        Config::set('data_scope.mode', 'country');

        $country = Country::factory()->create();
        $branchCountry = Country::factory()->create();
        $branch = Branch::create([
            'name' => 'Country Mode Branch',
            'country_id' => $branchCountry->id,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Country Mode Prefill',
            'contact_number' => '0170002222',
            'email' => 'country-mode-prefill@test.com',
            'created_by' => $this->adminUser->id,
            'country_id' => $country->id,
            'branch_id' => $branch->id,
        ]);

        PrivateEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'passport_expiry_date' => '2030-12-31',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'no_of_pax' => 2,
            'no_of_children' => 0,
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
            'need_wheelchair' => false,
            'other_remarks' => '',
        ]);

        $response = $this->get(route('enquiries.package-prefill', $enquiry->id));

        $response->assertOk();
        $response->assertJsonPath('country_id', (string) $country->id);
    }

    public function test_private_enquiry_package_prefill_sets_country_from_branch_country_in_branch_mode(): void
    {
        $this->actingAs($this->adminUser);
        Config::set('data_scope.mode', 'branch');

        $enquiryCountry = Country::factory()->create();
        $branchCountry = Country::factory()->create();
        $branch = Branch::create([
            'name' => 'Branch Mode Branch',
            'country_id' => $branchCountry->id,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Branch Mode Prefill',
            'contact_number' => '0170003333',
            'email' => 'branch-mode-prefill@test.com',
            'created_by' => $this->adminUser->id,
            'country_id' => $enquiryCountry->id,
            'branch_id' => $branch->id,
        ]);

        PrivateEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'passport_expiry_date' => '2030-12-31',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'no_of_pax' => 2,
            'no_of_children' => 0,
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
            'need_wheelchair' => false,
            'other_remarks' => '',
        ]);

        $response = $this->get(route('enquiries.package-prefill', $enquiry->id));

        $response->assertOk();
        $response->assertJsonPath('country_id', (string) $branchCountry->id);
    }

    public function test_private_enquiry_confirm_enforces_scope_country_for_package_data_in_branch_mode(): void
    {
        $this->actingAs($this->adminUser);
        Config::set('data_scope.mode', 'branch');

        $scopeCountry = Country::factory()->create();
        $fallbackCountry = Country::factory()->create();
        $branch = Branch::create([
            'name' => 'Scoped Branch',
            'country_id' => $scopeCountry->id,
        ]);

        Admin::create([
            'user_id' => $this->adminUser->id,
            'branch_id' => $branch->id,
            'country_id' => $scopeCountry->id,
            'branch_ids' => [$branch->id],
            'country_ids' => [$scopeCountry->id],
        ]);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Branch Confirm Scope',
            'contact_number' => '0170004444',
            'email' => 'branch-confirm-scope@test.com',
            'created_by' => $this->adminUser->id,
            'country_id' => $fallbackCountry->id,
            'branch_id' => $branch->id,
        ]);

        PrivateEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'passport_expiry_date' => '2030-12-31',
            'departure_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'no_of_pax' => 2,
            'no_of_children' => 0,
            'airline' => 'Airline X',
            'class' => 'Economy',
            'require_mutawif' => false,
            'require_umrah_course' => false,
            'require_umrah_official' => false,
            'makkah_or_madinah_first' => 'makkah',
            'no_of_nights_makkah' => '4',
            'hotel_makkah' => 'Makkah Grand',
            'meals_makkah' => 'Full Board',
            'no_of_nights_madinah' => '3',
            'hotel_madinah' => 'Madinah Grand',
            'meals_madinah' => 'Half Board',
            'land_transfer' => '',
            'add_on_speed_train' => false,
            'require_meet_greet' => false,
            'require_mutawiffah_ustazah_rawdah' => false,
            'madinah_tour_with_mutawif' => false,
            'makkah_tour_with_mutawif' => false,
            'has_chronic_disease' => false,
            'need_wheelchair' => false,
            'other_remarks' => '',
        ]);

        $response = $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Branch Confirm Scope',
                    'email' => 'branch-confirm-scope@test.com',
                    'contact_number' => '0170004444',
                    'is_leader' => true,
                ]),
            ],
            'package_data' => [
                'name' => 'Scoped Package',
                'status' => 'open',
                'country_id' => $fallbackCountry->id,
                'total_seats' => 10,
                'seats_left' => 10,
            ],
        ]);

        $response->assertRedirect();

        $enquiry->refresh();
        $this->assertNotNull($enquiry->package_id);

        $createdPackage = Package::findOrFail($enquiry->package_id);
        $this->assertSame($scopeCountry->id, (int) $createdPackage->country_id);
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
        $this->assertTrue(EnquiryStatus::Contacted->canTransitionTo(EnquiryStatus::Confirmed));

        $this->assertFalse(EnquiryStatus::NewLead->canTransitionTo(EnquiryStatus::Negotiating));
        $this->assertFalse(EnquiryStatus::NewLead->canTransitionTo(EnquiryStatus::Confirmed));
        $this->assertFalse(EnquiryStatus::Contacted->canTransitionTo(EnquiryStatus::Negotiating));
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

    public function test_standalone_customer_confirmation_creation_without_enquiry(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('enquiries.create-customer-confirmation'), [
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

        // Customer confirmation created without enquiry_id
        $group = CustomerConfirmation::whereNull('enquiry_id')->first();
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

        $response = $this->post(route('enquiries.create-customer-confirmation'), [
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
        $this->post(route('enquiries.create-customer-confirmation'), [
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

    public function test_confirmed_and_holding_customer_indexes_are_split_by_holding_flag(): void
    {
        $this->actingAs($this->adminUser);

        Permission::findOrCreate('customer view', 'web');
        $this->adminUser->givePermissionTo(['customer view']);

        $package = Package::create([
            'package_number' => 'PKG-HOLDING-001',
            'name' => 'Holding Split Package',
            'status' => 'open',
            'total_seats' => 30,
            'seats_left' => 30,
        ]);

        $confirmedWithPackage = CustomerConfirmation::create([
            'package_id' => $package->id,
            'is_holding' => false,
            'created_by' => $this->adminUser->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $confirmedWithoutPackage = CustomerConfirmation::create([
            'package_id' => null,
            'is_holding' => false,
            'created_by' => $this->adminUser->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $holdingWithoutPackage = CustomerConfirmation::create([
            'package_id' => null,
            'is_holding' => true,
            'created_by' => $this->adminUser->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $holdingWithPackage = CustomerConfirmation::create([
            'package_id' => $package->id,
            'is_holding' => true,
            'created_by' => $this->adminUser->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $confirmedMemberOneUser = User::factory()->create([
            'name' => 'Confirmed Active One',
            'email' => 'confirmed-active-one@test.com',
        ]);
        $confirmedMemberOneCustomer = Customer::create([
            'user_id' => $confirmedMemberOneUser->id,
        ]);
        $confirmedWithPackage->members()->create([
            'customer_id' => $confirmedMemberOneCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
        ]);

        $confirmedMemberTwoUser = User::factory()->create([
            'name' => 'Confirmed Active Two',
            'email' => 'confirmed-active-two@test.com',
        ]);
        $confirmedMemberTwoCustomer = Customer::create([
            'user_id' => $confirmedMemberTwoUser->id,
        ]);
        $confirmedWithoutPackage->members()->create([
            'customer_id' => $confirmedMemberTwoCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
        ]);

        $confirmedResponse = $this->get(route('confirmed-customer.index'));
        $confirmedResponse->assertStatus(200);
        $confirmedResponse->assertInertia(
            fn ($page) => $page
                ->component('confirmed-customer/index')
                ->where('pageTitle', 'Confirmed Customers')
                ->where('dataGroups', function ($groups) use ($confirmedWithPackage, $confirmedWithoutPackage, $holdingWithPackage, $holdingWithoutPackage) {
                    $ids = collect($groups)->pluck('id')->all();

                    return in_array($confirmedWithPackage->id, $ids, true)
                        && in_array($confirmedWithoutPackage->id, $ids, true)
                        && ! in_array($holdingWithPackage->id, $ids, true)
                        && ! in_array($holdingWithoutPackage->id, $ids, true);
                })
        );

        $holdingResponse = $this->get(route('customer-holding.index'));
        $holdingResponse->assertStatus(200);
        $holdingResponse->assertInertia(
            fn ($page) => $page
                ->component('confirmed-customer/index')
                ->where('pageTitle', 'Customer Holding')
                ->where('dataGroups', function ($groups) use ($confirmedWithPackage, $confirmedWithoutPackage, $holdingWithPackage, $holdingWithoutPackage) {
                    $ids = collect($groups)->pluck('id')->all();

                    return in_array($holdingWithPackage->id, $ids, true)
                        && in_array($holdingWithoutPackage->id, $ids, true)
                        && ! in_array($confirmedWithPackage->id, $ids, true)
                        && ! in_array($confirmedWithoutPackage->id, $ids, true);
                })
        );
    }
}

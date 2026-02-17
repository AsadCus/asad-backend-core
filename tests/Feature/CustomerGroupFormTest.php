<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\CustomerGroup;
use App\Models\Enquiry;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerGroupFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'general-enquiry view',
            'general-enquiry create',
            'general-enquiry edit',
            'customer view',
            'customer create',
            'customer edit',
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
     * Helper to build a valid member payload with all biodata fields.
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

    public function test_enquiry_package_update_stores_package_id(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'group_number' => 'PKG-001',
            'name' => 'Umrah Gold',
            'status' => 'active',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'John',
            'contact_number' => '012',
            'email' => 'john@example.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->put(route('enquiries.update-package', $enquiry->id), [
            'package_id' => $package->id,
        ]);

        $response->assertRedirect();

        $enquiry->refresh();
        $this->assertEquals($package->id, $enquiry->package_id);
    }

    public function test_enquiry_package_can_be_cleared(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'group_number' => 'PKG-002',
            'name' => 'Umrah Silver',
            'status' => 'active',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Jane',
            'contact_number' => '012',
            'email' => 'jane@example.com',
            'created_by' => $this->adminUser->id,
            'package_id' => $package->id,
        ]);

        $response = $this->put(route('enquiries.update-package', $enquiry->id), [
            'package_id' => null,
        ]);

        $response->assertRedirect();

        $enquiry->refresh();
        $this->assertNull($enquiry->package_id);
    }

    public function test_customer_group_creation_with_biodata_fields(): void
    {
        $this->actingAs($this->adminUser);

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
            'date_of_application' => '2026-01-15',
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Group Leader',
                    'email' => 'leader@test.com',
                    'contact_number' => '012345',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Participant',
                    'email' => 'participant@test.com',
                    'contact_number' => '098765',
                    'nric_number' => 'S9876543B',
                    'passport_number' => 'B87654321',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $response->assertRedirect();

        // Check customer group fields
        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($group);
        $this->assertEquals('double', $group->package_room_type);
        $this->assertEquals('classic_umrah', $group->package_category);
        $this->assertEquals('2026-01-15', $group->date_of_application->format('Y-m-d'));

        // Check members
        $this->assertEquals(2, $group->members()->count());

        // Check biodata on the leader's customer record
        $leader = $group->leader();
        $this->assertNotNull($leader);
        $customer = $leader->customer;
        $this->assertNotNull($customer);
        $this->assertEquals('Malaysian', $customer->nationality);
        $this->assertEquals('A12345678', $customer->passport_number);
        $this->assertEquals('male', $customer->gender);
        $this->assertEquals('single', $customer->marital_status);
    }

    public function test_customer_group_creation_with_package_id(): void
    {
        $this->actingAs($this->adminUser);

        $package = Package::create([
            'group_number' => 'PKG-003',
            'name' => 'Umrah Package',
            'status' => 'active',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Negotiating->value,
            'name' => 'Package Leader',
            'contact_number' => '012345',
            'email' => 'pkgleader@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'package_id' => $package->id,
            'date_of_application' => '2026-02-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Package Leader',
                    'email' => 'pkgleader@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $response->assertRedirect();

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($group);
        $this->assertEquals($package->id, $group->package_id);
    }

    public function test_customer_group_show_returns_json(): void
    {
        $this->actingAs($this->adminUser);

        // Create a group manually
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Show Test',
            'contact_number' => '012',
            'email' => 'showtest@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Show Test',
                    'email' => 'showtest@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();

        $response = $this->getJson(route('customer-groups.show', $group->id));
        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'enquiry_id',
            'package_id',
            'package_room_type',
            'package_category',
            'date_of_application',
            'members',
        ]);
    }

    public function test_customer_group_update(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Update Test',
            'contact_number' => '012',
            'email' => 'update@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Update Test',
                    'email' => 'update@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();

        $response = $this->put(route('customer-groups.update', $group->id), [
            'date_of_application' => '2026-06-15',
            'package_room_type' => 'triple',
            'package_category' => 'deluxe_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Updated Name',
                    'email' => 'update@test.com',
                    'is_leader' => true,
                    'nationality' => 'Singaporean',
                ]),
            ],
        ]);

        $response->assertRedirect();

        $group->refresh();
        $this->assertEquals('triple', $group->package_room_type);
        $this->assertEquals('deluxe_umrah', $group->package_category);
        $this->assertEquals('2026-06-15', $group->date_of_application->format('Y-m-d'));
    }

    public function test_public_form_requires_valid_signature(): void
    {
        // Access without valid signature should fail
        $response = $this->get('/customer-groups/public/999');
        $response->assertStatus(403);
    }

    public function test_public_form_accessible_with_valid_signature(): void
    {
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Public Test',
            'contact_number' => '012',
            'email' => 'public@test.com',
            'created_by' => User::factory()->create()->id,
        ]);

        $url = URL::signedRoute('customer-groups.public.form', [
            'enquiryId' => $enquiry->id,
        ]);

        $response = $this->get($url);
        $response->assertOk();
    }

    public function test_public_store_requires_terms_accepted(): void
    {
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Terms Test',
            'contact_number' => '012',
            'email' => 'terms@test.com',
            'created_by' => User::factory()->create()->id,
        ]);

        $url = URL::signedRoute('customer-groups.public.store', [
            'enquiryId' => $enquiry->id,
        ]);

        $response = $this->post($url, [
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Terms Test',
                    'email' => 'terms@test.com',
                    'is_leader' => true,
                ]),
            ],
            'terms_accepted' => false,
        ]);

        $response->assertSessionHasErrors('terms_accepted');
    }

    public function test_public_store_creates_group_when_valid(): void
    {
        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Public Submit',
            'contact_number' => '012',
            'email' => 'pubsub@test.com',
            'created_by' => User::factory()->create()->id,
        ]);

        $url = URL::signedRoute('customer-groups.public.store', [
            'enquiryId' => $enquiry->id,
        ]);

        $response = $this->post($url, [
            'date_of_application' => '2026-03-01',
            'package_room_type' => 'quad',
            'members' => [
                $this->memberPayload([
                    'name' => 'Public Submit',
                    'email' => 'pubsub@test.com',
                    'is_leader' => true,
                ]),
            ],
            'terms_accepted' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('customer_groups', [
            'enquiry_id' => $enquiry->id,
            'package_room_type' => 'quad',
        ]);
    }

    public function test_generate_public_link_returns_signed_url(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Link Test',
            'contact_number' => '012',
            'email' => 'link@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson(route('customer-groups.generate-link', $enquiry->id));
        $response->assertOk();
        $response->assertJsonStructure(['url']);

        // The URL should contain a signature parameter
        $url = $response->json('url');
        $this->assertStringContainsString('signature=', $url);
    }
}

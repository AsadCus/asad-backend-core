<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupMember;
use App\Models\Enquiry;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;
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
            'package_number' => 'PKG-001',
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
            'package_number' => 'PKG-002',
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
            'package_number' => 'PKG-003',
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

    public function test_customer_group_update_with_multiple_members(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Multi Update Test',
            'contact_number' => '012',
            'email' => 'multi@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        // Create with 2 members
        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Leader',
                    'email' => 'leader@test.com',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Member One',
                    'email' => 'member1@test.com',
                    'nric_number' => 'S1111111A',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();
        $this->assertEquals(2, $group->members()->count());

        // Update with 3 members
        $response = $this->put(route('customer-groups.update', $group->id), [
            'date_of_application' => '2026-07-01',
            'package_room_type' => 'quad',
            'package_category' => 'deluxe_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Leader',
                    'email' => 'leader@test.com',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Member One',
                    'email' => 'member1@test.com',
                    'nric_number' => 'S1111111A',
                    'is_leader' => false,
                ]),
                $this->memberPayload([
                    'name' => 'Member Two',
                    'email' => 'member2@test.com',
                    'nric_number' => 'S2222222B',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $response->assertRedirect();

        $group->refresh();
        $this->assertEquals(3, $group->members()->count());
        $this->assertEquals('quad', $group->package_room_type);
        $this->assertEquals('deluxe_umrah', $group->package_category);
        $this->assertEquals('2026-07-01', $group->date_of_application->format('Y-m-d'));
    }

    public function test_customer_group_update_accepts_method_spoofed_post_payload(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Spoof Update Test',
            'contact_number' => '012',
            'email' => 'spoof@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-01-10',
            'members' => [
                $this->memberPayload([
                    'name' => 'Leader',
                    'email' => 'leader-spoof@test.com',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Member One',
                    'email' => 'member-spoof@test.com',
                    'nric_number' => 'S3333333C',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->first();

        $response = $this->post(route('customer-groups.update', $group->id), [
            '_method' => 'put',
            'date_of_application' => '2026-08-12',
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Leader',
                    'email' => 'leader-spoof@test.com',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Member One',
                    'email' => 'member-spoof@test.com',
                    'nric_number' => 'S3333333C',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $response->assertRedirect();

        $group->refresh();
        $this->assertEquals('double', $group->package_room_type);
        $this->assertEquals('classic_umrah', $group->package_category);
        $this->assertEquals('2026-08-12', $group->date_of_application->format('Y-m-d'));
        $this->assertEquals(2, $group->members()->count());
    }

    public function test_private_group_update_rejects_replacing_linked_package(): void
    {
        $this->actingAs($this->adminUser);

        $originalPackage = Package::create([
            'package_number' => 'PKG-PRIVATE-ORIG',
            'name' => 'Private Original',
            'status' => 'open',
        ]);

        $replacementPackage = Package::create([
            'package_number' => 'PKG-PRIVATE-NEW',
            'name' => 'Private Replacement',
            'status' => 'open',
        ]);

        $enquiry = Enquiry::create([
            'type' => 'private',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Private Group',
            'contact_number' => '01234',
            'email' => 'private-group@test.com',
            'created_by' => $this->adminUser->id,
            'package_id' => $originalPackage->id,
        ]);

        $group = CustomerGroup::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => $this->adminUser->id,
            'package_id' => $originalPackage->id,
            'date_of_application' => '2026-10-15',
        ]);

        $customerUser = User::factory()->create([
            'name' => 'Private Leader',
            'email' => 'private-leader@test.com',
        ]);
        $customerUser->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'C-PRIVATE-001',
        ]);

        CustomerGroupMember::create([
            'customer_group_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
        ]);

        $response = $this->put(route('customer-groups.update', $group->id), [
            'date_of_application' => '2026-10-20',
            'package_id' => $replacementPackage->id,
            'members' => [
                $this->memberPayload([
                    'name' => 'Private Leader',
                    'email' => 'private-leader@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $response->assertStatus(422);

        $group->refresh();
        $this->assertEquals($originalPackage->id, $group->package_id);
    }

    public function test_customer_group_destroy_deletes_group_and_members_only_and_reverts_enquiry_status(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Delete Group Test',
            'contact_number' => '01234',
            'email' => 'delete-group@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-09-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Leader Delete',
                    'email' => 'leader-delete@test.com',
                    'is_leader' => true,
                ]),
                $this->memberPayload([
                    'name' => 'Member Delete',
                    'email' => 'member-delete@test.com',
                    'nric_number' => 'S4444444D',
                    'is_leader' => false,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->firstOrFail();
        $memberCustomerIds = $group->members()->pluck('customer_id')->all();
        $memberUserIds = Customer::whereIn('id', $memberCustomerIds)->pluck('user_id')->all();

        $this->assertCount(2, $memberCustomerIds);

        $response = $this->delete(route('customer-groups.destroy', $group->id));
        $response->assertRedirect();

        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('customer_group_members', ['customer_group_id' => $group->id]);

        foreach ($memberCustomerIds as $customerId) {
            $this->assertDatabaseHas('customers', ['id' => $customerId]);
        }

        foreach ($memberUserIds as $userId) {
            $this->assertDatabaseHas('users', ['id' => $userId]);
        }

        $enquiry->refresh();
        $this->assertEquals(EnquiryStatus::Negotiating, $enquiry->status);
    }

    public function test_confirmed_customer_bulk_destroy_deletes_selected_groups(): void
    {
        $this->actingAs($this->adminUser);

        $firstEnquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Bulk Delete One',
            'contact_number' => '01111',
            'email' => 'bulk-one@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $secondEnquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Bulk Delete Two',
            'contact_number' => '02222',
            'email' => 'bulk-two@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $firstEnquiry->id), [
            'enquiry_id' => $firstEnquiry->id,
            'date_of_application' => '2026-10-01',
            'members' => [$this->memberPayload([
                'name' => 'Bulk Leader One',
                'email' => 'bulk-leader-one@test.com',
                'is_leader' => true,
            ])],
        ]);

        $this->post(route('enquiries.confirm', $secondEnquiry->id), [
            'enquiry_id' => $secondEnquiry->id,
            'date_of_application' => '2026-10-02',
            'members' => [$this->memberPayload([
                'name' => 'Bulk Leader Two',
                'email' => 'bulk-leader-two@test.com',
                'is_leader' => true,
            ])],
        ]);

        $firstGroup = CustomerGroup::where('enquiry_id', $firstEnquiry->id)->firstOrFail();
        $secondGroup = CustomerGroup::where('enquiry_id', $secondEnquiry->id)->firstOrFail();

        $response = $this->delete(route('confirmed-customer.destroy', 0), [
            'ids' => [$firstGroup->id, $secondGroup->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('customer_groups', ['id' => $firstGroup->id]);
        $this->assertDatabaseMissing('customer_groups', ['id' => $secondGroup->id]);

        $firstEnquiry->refresh();
        $secondEnquiry->refresh();
        $this->assertEquals(EnquiryStatus::Negotiating, $firstEnquiry->status);
        $this->assertEquals(EnquiryStatus::Negotiating, $secondEnquiry->status);
    }

    public function test_public_form_requires_valid_signature(): void
    {
        // The edit form requires a valid signature (to protect private group data)
        $response = $this->get(route('customer-confirmation.public.edit', ['encryptedId' => 'invalid']));
        $response->assertStatus(403);
    }

    public function test_public_create_form_is_accessible_without_signature(): void
    {
        // The create form is public — anyone can access it directly
        $response = $this->get(route('customer-confirmation.public.create'));
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

        $response = $this->post(route('customer-confirmation.public.store'), [
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

        $response = $this->post(route('customer-confirmation.public.store'), [
            'enquiry_id' => $enquiry->id,
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

    public function test_public_one_time_edit_link_can_only_be_used_once(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'One Time Link',
            'contact_number' => '012',
            'email' => 'one-time@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-04-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'One Time Link',
                    'email' => 'one-time@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->firstOrFail();

        $response = $this->getJson(route('customer-groups.generate-edit-link', [
            'groupId' => $group->id,
            'link_type' => 'one_time',
        ]));

        $response->assertOk();

        $url = $response->json('url');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $encryptedId = basename((string) parse_url($url, PHP_URL_PATH));

        $this->assertSame('one_time', Arr::get($query, 'link_type'));
        $this->assertNotEmpty(Arr::get($query, 'link_token'));
        $this->assertNotEmpty(Arr::get($query, 'expires'));
        $this->assertNotEmpty(Arr::get($query, 'signature'));

        $this->get($url)->assertOk();

        $updateUrl = URL::temporarySignedRoute(
            'customer-confirmation.public.update',
            now()->setTimestamp((int) Arr::get($query, 'expires')),
            [
                'encryptedId' => $encryptedId,
                'link_type' => Arr::get($query, 'link_type'),
                'link_token' => Arr::get($query, 'link_token'),
            ],
        );

        $this->post($updateUrl, [
            'date_of_application' => '2026-05-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'One Time Link Updated',
                    'email' => 'one-time@test.com',
                    'is_leader' => true,
                ]),
            ],
            'terms_accepted' => true,
        ])->assertOk();

        $this->get($url)->assertStatus(403);
    }

    public function test_public_edit_store_requires_terms_accepted(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Public Edit Terms',
            'contact_number' => '012',
            'email' => 'public-edit-terms@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-06-01',
            'members' => [
                $this->memberPayload([
                    'name' => 'Public Edit Terms',
                    'email' => 'public-edit-terms@test.com',
                    'is_leader' => true,
                ]),
            ],
        ]);

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->firstOrFail();
        $encryptedId = Crypt::encrypt($group->id);

        $updateUrl = URL::signedRoute('customer-confirmation.public.update', [
            'encryptedId' => $encryptedId,
            'link_type' => 'continuous',
        ]);

        $this->post($updateUrl, [
            'date_of_application' => '2026-06-05',
            'members' => [
                $this->memberPayload([
                    'name' => 'Public Edit Terms Updated',
                    'email' => 'public-edit-terms@test.com',
                    'is_leader' => true,
                ]),
            ],
            'terms_accepted' => false,
        ])->assertSessionHasErrors('terms_accepted');
    }

    public function test_customer_group_activity_logs_store_detailed_old_and_new_snapshots(): void
    {
        $this->actingAs($this->adminUser);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Confirmed->value,
            'name' => 'Detailed Log Test',
            'contact_number' => '012',
            'email' => 'detailed-log@test.com',
            'created_by' => $this->adminUser->id,
        ]);

        $this->post(route('enquiries.confirm', $enquiry->id), [
            'enquiry_id' => $enquiry->id,
            'date_of_application' => '2026-09-01',
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Detailed Leader',
                    'email' => 'detailed-leader@test.com',
                    'contact_number' => '0191234567',
                    'nric_number' => 'S1234567A',
                    'passport_number' => 'A12345678',
                    'is_leader' => true,
                ]),
            ],
        ])->assertRedirect();

        $group = CustomerGroup::where('enquiry_id', $enquiry->id)->firstOrFail();

        $createActivity = Activity::query()
            ->where('subject_type', CustomerGroup::class)
            ->where('subject_id', $group->id)
            ->where('description', 'like', 'Customer group created%')
            ->latest('id')
            ->first();

        $this->assertNotNull($createActivity);

        $createProperties = $createActivity->properties->toArray();
        $this->assertSame([], $createProperties['old'] ?? null);
        $this->assertSame($group->id, data_get($createProperties, 'attributes.group.id'));
        $this->assertSame('create', data_get($createProperties, 'context.operation'));
        $this->assertSame($this->adminUser->id, data_get($createProperties, 'context.actor.id'));

        $maskedCreateEmail = data_get($createProperties, 'attributes.members.0.email');
        $this->assertIsString($maskedCreateEmail);
        $this->assertNotSame('detailed-leader@test.com', $maskedCreateEmail);
        $this->assertTrue(str_contains((string) $maskedCreateEmail, '*'));

        $this->put(route('customer-groups.update', $group->id), [
            'date_of_application' => '2026-09-02',
            'package_room_type' => 'triple',
            'package_category' => 'deluxe_umrah',
            'members' => [
                $this->memberPayload([
                    'name' => 'Detailed Leader Updated',
                    'email' => 'detailed-leader@test.com',
                    'contact_number' => '0190000000',
                    'nric_number' => 'S1234567A',
                    'passport_number' => 'A12345678',
                    'nationality' => 'Singaporean',
                    'is_leader' => true,
                ]),
            ],
        ])->assertRedirect();

        $updateActivity = Activity::query()
            ->where('subject_type', CustomerGroup::class)
            ->where('subject_id', $group->id)
            ->where('description', 'Customer group #'.$group->id.' updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateActivity);

        $updateProperties = $updateActivity->properties->toArray();
        $this->assertSame('update', data_get($updateProperties, 'context.operation'));
        $this->assertSame('double', data_get($updateProperties, 'old.group.package_room_type'));
        $this->assertSame('triple', data_get($updateProperties, 'attributes.group.package_room_type'));
        $this->assertSame('classic_umrah', data_get($updateProperties, 'old.group.package_category'));
        $this->assertSame('deluxe_umrah', data_get($updateProperties, 'attributes.group.package_category'));

        $maskedUpdateContact = data_get($updateProperties, 'attributes.members.0.contact_number');
        $this->assertIsString($maskedUpdateContact);
        $this->assertNotSame('0190000000', $maskedUpdateContact);
        $this->assertTrue(str_contains((string) $maskedUpdateContact, '*'));

        $this->delete(route('customer-groups.destroy', $group->id))->assertRedirect();

        $deleteActivity = Activity::query()
            ->where('subject_type', CustomerGroup::class)
            ->where('subject_id', $group->id)
            ->where('description', 'Customer group #'.$group->id.' deleted')
            ->latest('id')
            ->first();

        $this->assertNotNull($deleteActivity);

        $deleteProperties = $deleteActivity->properties->toArray();
        $this->assertSame('delete', data_get($deleteProperties, 'context.operation'));
        $this->assertSame(true, data_get($deleteProperties, 'attributes.deleted'));
        $this->assertSame($group->id, data_get($deleteProperties, 'old.group.id'));
    }
}

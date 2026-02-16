<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\EnquiryRemark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryRemarkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Enquiry $enquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'general-enquiry view',
            'general-enquiry create',
            'general-enquiry edit',
            'general-enquiry delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->enquiry = Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Test User',
            'contact_number' => '0123456789',
            'email' => 'test@example.com',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_store_a_remark(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('enquiry-remarks.store', $this->enquiry->id), [
            'remark' => 'This is a test remark.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('enquiry_remarks', [
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'This is a test remark.',
        ]);
    }

    public function test_can_list_remarks_for_enquiry(): void
    {
        $this->actingAs($this->user);

        EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'First remark',
        ]);

        EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::Contacted->value,
            'remark' => 'Second remark',
        ]);

        $response = $this->getJson(route('enquiry-remarks.index', $this->enquiry->id));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['remark' => 'First remark']);
        $response->assertJsonFragment(['remark' => 'Second remark']);
    }

    public function test_can_update_a_remark(): void
    {
        $this->actingAs($this->user);

        $remark = EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'Original remark',
        ]);

        $response = $this->put(route('enquiry-remarks.update', [
            'enquiryId' => $this->enquiry->id,
            'remarkId' => $remark->id,
        ]), [
            'remark' => 'Updated remark',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('enquiry_remarks', [
            'id' => $remark->id,
            'remark' => 'Updated remark',
        ]);
    }

    public function test_can_delete_a_remark(): void
    {
        $this->actingAs($this->user);

        $remark = EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'Remark to delete',
        ]);

        $response = $this->delete(route('enquiry-remarks.destroy', [
            'enquiryId' => $this->enquiry->id,
            'remarkId' => $remark->id,
        ]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('enquiry_remarks', [
            'id' => $remark->id,
        ]);
    }

    public function test_store_remark_requires_remark_text(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('enquiry-remarks.store', $this->enquiry->id), [
            'remark' => '',
        ]);

        $response->assertSessionHasErrors('remark');
    }

    public function test_store_remark_captures_current_enquiry_status(): void
    {
        $this->actingAs($this->user);

        $this->enquiry->update(['status' => EnquiryStatus::Negotiating->value]);

        $response = $this->post(route('enquiry-remarks.store', $this->enquiry->id), [
            'remark' => 'Remark during negotiation.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('enquiry_remarks', [
            'enquiry_id' => $this->enquiry->id,
            'status_at_time' => EnquiryStatus::Negotiating->value,
            'remark' => 'Remark during negotiation.',
        ]);
    }

    public function test_remarks_include_creator_name(): void
    {
        $this->actingAs($this->user);

        EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'Test remark',
        ]);

        $response = $this->getJson(route('enquiry-remarks.index', $this->enquiry->id));

        $response->assertOk();
        $response->assertJsonFragment([
            'creator_name' => $this->user->name,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_remarks(): void
    {
        $response = $this->getJson(route('enquiry-remarks.index', $this->enquiry->id));

        $response->assertUnauthorized();
    }

    public function test_enquiry_has_remarks_relationship(): void
    {
        EnquiryRemark::create([
            'enquiry_id' => $this->enquiry->id,
            'created_by' => $this->user->id,
            'status_at_time' => EnquiryStatus::NewLead->value,
            'remark' => 'Test remark',
        ]);

        $this->assertCount(1, $this->enquiry->remarks);
        $this->assertNotNull($this->enquiry->latestRemark);
        $this->assertEquals('Test remark', $this->enquiry->latestRemark->remark);
    }

    public function test_remark_max_length_validation(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('enquiry-remarks.store', $this->enquiry->id), [
            'remark' => str_repeat('a', 2001),
        ]);

        $response->assertSessionHasErrors('remark');
    }
}

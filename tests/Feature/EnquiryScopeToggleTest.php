<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnquiryScopeToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_sees_only_unhandled_and_self_handled_enquiries_when_scope_enabled(): void
    {
        config(['data_scope.enabled' => true]);
        config(['data_scope.sales_ownership' => true]);

        $salesRole = Role::query()->firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $salesUser = User::factory()->create();
        $salesUser->assignRole($salesRole);

        $otherSalesUser = User::factory()->create();
        $otherSalesUser->assignRole($salesRole);

        $visibleUnhandled = Enquiry::create([
            'type' => 'general',
            'enquiry_number' => 'ENQ-SCOPE-1',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Visible Unhandled',
            'contact_number' => '0100000001',
            'email' => 'visible-unhandled@example.com',
            'created_by' => $salesUser->id,
            'handled_by' => null,
        ]);

        $visibleHandledBySelf = Enquiry::create([
            'type' => 'general',
            'enquiry_number' => 'ENQ-SCOPE-2',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Visible Self',
            'contact_number' => '0100000002',
            'email' => 'visible-self@example.com',
            'created_by' => $salesUser->id,
            'handled_by' => $salesUser->id,
        ]);

        Enquiry::create([
            'type' => 'private',
            'enquiry_number' => 'ENQ-SCOPE-3',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Hidden Other',
            'contact_number' => '0100000003',
            'email' => 'hidden-other@example.com',
            'created_by' => $otherSalesUser->id,
            'handled_by' => $otherSalesUser->id,
        ]);

        $this->actingAs($salesUser);

        $rows = app(EnquiryService::class)->getForDataTable();
        $ids = collect($rows)->pluck('id')->all();
        $visibleUnhandledRow = collect($rows)->firstWhere('id', $visibleUnhandled->id);

        $this->assertCount(2, $rows);
        $this->assertContains($visibleUnhandled->id, $ids);
        $this->assertContains($visibleHandledBySelf->id, $ids);
        $this->assertNotNull($visibleUnhandledRow);
        $this->assertNull($visibleUnhandledRow['handled_by_name']);
    }

    public function test_sales_sees_all_enquiries_when_scope_disabled(): void
    {
        config(['data_scope.enabled' => false]);
        config(['data_scope.sales_ownership' => false]);

        $salesRole = Role::query()->firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);

        $salesUser = User::factory()->create();
        $salesUser->assignRole($salesRole);

        $otherSalesUser = User::factory()->create();
        $otherSalesUser->assignRole($salesRole);

        Enquiry::create([
            'type' => 'general',
            'enquiry_number' => 'ENQ-OFF-1',
            'status' => EnquiryStatus::NewLead->value,
            'name' => 'Unhandled',
            'contact_number' => '0100000011',
            'email' => 'unhandled@example.com',
            'created_by' => $salesUser->id,
            'handled_by' => null,
        ]);

        Enquiry::create([
            'type' => 'general',
            'enquiry_number' => 'ENQ-OFF-2',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Handled Self',
            'contact_number' => '0100000012',
            'email' => 'handled-self@example.com',
            'created_by' => $salesUser->id,
            'handled_by' => $salesUser->id,
        ]);

        Enquiry::create([
            'type' => 'private',
            'enquiry_number' => 'ENQ-OFF-3',
            'status' => EnquiryStatus::Contacted->value,
            'name' => 'Handled Other',
            'contact_number' => '0100000013',
            'email' => 'handled-other@example.com',
            'created_by' => $otherSalesUser->id,
            'handled_by' => $otherSalesUser->id,
        ]);

        $this->actingAs($salesUser);

        $rows = app(EnquiryService::class)->getForDataTable();

        $this->assertCount(3, $rows);
    }
}

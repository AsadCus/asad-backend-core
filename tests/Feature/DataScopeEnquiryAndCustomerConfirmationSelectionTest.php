<?php

namespace Tests\Feature;

use App\Enums\EnquiryStatus;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Package;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataScopeEnquiryAndCustomerConfirmationSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_country_scope_filters_enquiry_and_customer_confirmation_indexes_for_admin(): void
    {
        config(['data_scope.enabled' => true]);
        config(['data_scope.mode' => 'country']);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $countrySingapore = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
        ]);

        $countryMalaysia = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $adminUser = User::factory()->create([
            'selected_country_ids' => [$countryMalaysia->id],
        ]);
        $adminUser->assignRole($adminRole);

        Admin::query()->create([
            'user_id' => $adminUser->id,
            'branch_id' => null,
            'country_id' => $countrySingapore->id,
            'branch_ids' => [],
            'country_ids' => [$countrySingapore->id, $countryMalaysia->id],
        ]);

        $packageMalaysiaOpen = Package::create([
            'package_number' => 'PKG-SCOPE-MY-OPEN-001',
            'name' => 'Malaysia Open Package',
            'status' => 'open',
            'country_id' => $countryMalaysia->id,
            'price_single' => 5000,
        ]);

        $packageMalaysiaCompleted = Package::create([
            'package_number' => 'PKG-SCOPE-MY-COMP-001',
            'name' => 'Malaysia Completed Package',
            'status' => 'completed',
            'country_id' => $countryMalaysia->id,
            'price_single' => 5000,
        ]);

        $packageSingaporeOpen = Package::create([
            'package_number' => 'PKG-SCOPE-SG-OPEN-001',
            'name' => 'Singapore Open Package',
            'status' => 'open',
            'country_id' => $countrySingapore->id,
            'price_single' => 5000,
        ]);

        $packageSingaporeCompleted = Package::create([
            'package_number' => 'PKG-SCOPE-SG-COMP-001',
            'name' => 'Singapore Completed Package',
            'status' => 'completed',
            'country_id' => $countrySingapore->id,
            'price_single' => 5000,
        ]);

        $enquiryMalaysia = $this->createScopedEnquiry($adminUser->id, 'Malaysia Enquiry', $countryMalaysia->id, $packageMalaysiaOpen->id);
        $enquirySingapore = $this->createScopedEnquiry($adminUser->id, 'Singapore Enquiry', $countrySingapore->id, $packageSingaporeOpen->id);

        $confirmedMalaysia = $this->createScopedConfirmation($adminUser->id, $enquiryMalaysia->id, $packageMalaysiaOpen->id, false, ['pending_payment']);
        $holdingMalaysia = $this->createScopedConfirmation($adminUser->id, $enquiryMalaysia->id, null, true, ['pending_payment']);
        $completedMalaysia = $this->createScopedConfirmation($adminUser->id, $enquiryMalaysia->id, $packageMalaysiaCompleted->id, false, ['fully_paid']);
        $cancelledMalaysia = $this->createScopedConfirmation($adminUser->id, $enquiryMalaysia->id, $packageMalaysiaOpen->id, false, ['cancelled']);

        $confirmedSingapore = $this->createScopedConfirmation($adminUser->id, $enquirySingapore->id, $packageSingaporeOpen->id, false, ['pending_payment']);
        $holdingSingapore = $this->createScopedConfirmation($adminUser->id, $enquirySingapore->id, null, true, ['pending_payment']);
        $completedSingapore = $this->createScopedConfirmation($adminUser->id, $enquirySingapore->id, $packageSingaporeCompleted->id, false, ['fully_paid']);
        $cancelledSingapore = $this->createScopedConfirmation($adminUser->id, $enquirySingapore->id, $packageSingaporeOpen->id, false, ['cancelled']);

        $this->actingAs($adminUser);

        $enquiryRows = app(EnquiryService::class)->getForDataTable();
        $enquiryIds = collect($enquiryRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $confirmedRows = app(CustomerConfirmationService::class)->getForConfirmedIndex();
        $confirmedIds = collect($confirmedRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $holdingRows = app(CustomerConfirmationService::class)->getForHoldingIndex();
        $holdingIds = collect($holdingRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $completedRows = app(CustomerConfirmationService::class)->getForCompletedIndex();
        $completedIds = collect($completedRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $cancelledRows = app(CustomerConfirmationService::class)->getForCancelledIndex();
        $cancelledIds = collect($cancelledRows)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains($enquiryMalaysia->id, $enquiryIds);
        $this->assertNotContains($enquirySingapore->id, $enquiryIds);

        $this->assertContains($confirmedMalaysia->id, $confirmedIds);
        $this->assertNotContains($confirmedSingapore->id, $confirmedIds);

        $this->assertContains($holdingMalaysia->id, $holdingIds);
        $this->assertNotContains($holdingSingapore->id, $holdingIds);

        $this->assertContains($completedMalaysia->id, $completedIds);
        $this->assertNotContains($completedSingapore->id, $completedIds);

        $this->assertContains($cancelledMalaysia->id, $cancelledIds);
        $this->assertNotContains($cancelledSingapore->id, $cancelledIds);
    }

    private function createScopedEnquiry(int $handledBy, string $name, int $countryId, ?int $packageId = null): Enquiry
    {
        return Enquiry::create([
            'type' => 'general',
            'status' => EnquiryStatus::Contacted->value,
            'name' => $name,
            'contact_number' => '123456789',
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'created_by' => $handledBy,
            'handled_by' => $handledBy,
            'country_id' => $countryId,
            'package_id' => $packageId,
        ]);
    }

    /**
     * @param  array<int, string>  $memberStatuses
     */
    private function createScopedConfirmation(
        int $createdBy,
        int $enquiryId,
        ?int $packageId,
        bool $isHolding,
        array $memberStatuses,
    ): CustomerConfirmation {
        $confirmation = CustomerConfirmation::create([
            'enquiry_id' => $enquiryId,
            'created_by' => $createdBy,
            'package_id' => $packageId,
            'is_holding' => $isHolding,
        ]);

        foreach ($memberStatuses as $index => $status) {
            $memberUser = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $memberUser->id,
                'customer_number' => 'CUST-SCOPE-'.str_pad((string) $confirmation->id, 4, '0', STR_PAD_LEFT).'-'.($index + 1),
            ]);

            CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 0,
                'status' => $status,
                'sharing_plan' => 'single',
            ]);
        }

        return $confirmation;
    }
}

<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure converted quotations can be voided without enum truncation errors.
     */
    public function test_can_cancel_converted_quotation(): void
    {
        $quotation = Quotation::create([
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Converted->value,
        ]);

        $cancelled = app(QuotationService::class)->cancel($quotation->id);

        $this->assertSame(QuotationStatus::Cancelled, $cancelled->fresh()->status);
    }

    public function test_cancel_quotation_resets_linked_confirmation_members_to_pending_payment(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Converted->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->cancel($quotation->id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_cancel_quotation_removes_linked_members_from_package_manifest(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-007',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-QS-007',
            'name' => 'Void Manifest Sync Package',
            'status' => 'open',
            'total_seats' => 10,
            'seats_left' => 10,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'fully_paid',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Converted->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-QS-007',
            'status' => 'draft',
        ]);

        $member = $manifest->members()->create([
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Linked Member',
        ]);

        app(QuotationService::class)->cancel($quotation->id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseMissing('manifest_members', [
            'id' => $member->id,
        ]);
    }

    public function test_reject_quotation_resets_linked_confirmation_members_to_pending_payment(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-002',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Ready->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->reject(['reason' => 'User requested'], $quotation->id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_store_quotation_sets_linked_member_to_pending_payment(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-003',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);

        app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => QuotationStatus::Draft->value,
            'description' => 'Store quotation status sync',
            'items' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'description' => 'Linked member item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 100,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_update_quotation_reverts_removed_member_to_pending_payment_when_unlinked(): void
    {
        $userA = User::factory()->create();
        $customerA = Customer::create([
            'user_id' => $userA->id,
            'customer_number' => 'CUST-QS-004',
        ]);

        $userB = User::factory()->create();
        $customerB = Customer::create([
            'user_id' => $userB->id,
            'customer_number' => 'CUST-QS-005',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $memberA = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customerA->id,
            'status' => 'pending_payment',
        ]);

        $memberB = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customerB->id,
            'status' => 'pending_payment',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customerA->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Draft->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $memberA->id,
            'description' => 'Member A item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->update([
            'customer_id' => $customerB->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => QuotationStatus::Draft->value,
            'description' => 'Update quotation status sync',
            'items' => [
                [
                    'customer_confirmation_member_id' => $memberB->id,
                    'description' => 'Member B item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 200,
                    'sort_order' => 1,
                ],
            ],
        ], $quotation->id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $memberA->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $memberB->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_delete_quotation_resets_linked_confirmation_members_to_pending_payment(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-006',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Draft->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->delete($quotation->id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);
    }
}

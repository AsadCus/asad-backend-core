<?php

namespace Tests\Feature\Tms;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Validation\ValidationException;
use Tests\TmsTestCase as TestCase;

class QuotationStatusTest extends TestCase
{
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

    public function test_customer_confirmation_create_options_include_only_members_without_active_quotation_links(): void
    {
        $activeStatuses = [
            QuotationStatus::Draft->value,
            QuotationStatus::Ready->value,
            QuotationStatus::Accepted->value,
            QuotationStatus::Converted->value,
        ];

        $inactiveStatuses = [
            QuotationStatus::Rejected->value,
            QuotationStatus::Expired->value,
            QuotationStatus::Cancelled->value,
        ];

        $availableConfirmation = $this->createConfirmationWithMemberAndQuotationStatus(null, 'CC Available Member');
        $activeLinkedConfirmation = $this->createConfirmationWithMemberAndQuotationStatus(
            $activeStatuses[0],
            'CC Active Linked Member'
        );

        $inactiveLinkedConfirmationIds = [];
        foreach ($inactiveStatuses as $index => $inactiveStatus) {
            $confirmation = $this->createConfirmationWithMemberAndQuotationStatus(
                $inactiveStatus,
                'CC Inactive Linked Member '.($index + 1)
            );
            $inactiveLinkedConfirmationIds[] = (int) $confirmation->id;
        }

        $options = app(QuotationService::class)->getCustomerConfirmationCreateOptions();
        $optionIds = collect($options)->pluck('value')->map(fn ($value) => (int) $value)->all();

        $this->assertContains((int) $availableConfirmation->id, $optionIds);
        foreach ($inactiveLinkedConfirmationIds as $confirmationId) {
            $this->assertContains($confirmationId, $optionIds);
        }
        $this->assertNotContains((int) $activeLinkedConfirmation->id, $optionIds);
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

    private function createConfirmationWithMemberAndQuotationStatus(?string $quotationStatus, string $customerName): CustomerConfirmation
    {
        $user = User::factory()->create(['name' => $customerName]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-'.strtoupper(substr(str_replace(' ', '', $customerName), 0, 8)).'-'.random_int(100, 999),
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'double',
        ]);

        if ($quotationStatus !== null) {
            $quotation = Quotation::create([
                'customer_id' => $customer->id,
                'customer_confirmation_id' => $confirmation->id,
                'quotation_date' => now()->format('Y-m-d'),
                'expiry_date' => now()->addDays(7)->format('Y-m-d'),
                'status' => $quotationStatus,
                'payment_plan' => 'full',
            ]);

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $member->id,
                'description' => 'Linked item',
                'is_header' => false,
                'quantity' => 1,
                'rate' => 100,
                'sort_order' => 1,
            ]);
        }

        return $confirmation;
    }

    public function test_expire_quotation_resets_member_removes_manifest_member_and_cancels_invoice(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-EXPIRE-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-QS-EXPIRE-001',
            'name' => 'Expire Sync Package',
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

        $item = QuotationItem::create([
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
            'manifest_number' => 'MNF-QS-EXPIRE-001',
            'status' => 'draft',
        ]);

        $manifestMember = $manifest->members()->create([
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Expire Member',
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice for expire test',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        app(QuotationService::class)->expire($quotation->id);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => QuotationStatus::Expired->value,
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseMissing('manifest_members', [
            'id' => $manifestMember->id,
        ]);
    }

    public function test_draft_transition_fails_when_member_is_linked_to_another_active_quotation(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-DRAFT-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
        ]);

        $expiredQuotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Expired->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $expiredQuotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Expired quotation member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $activeQuotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => QuotationStatus::Ready->value,
        ]);

        QuotationItem::create([
            'quotation_id' => $activeQuotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Active quotation member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(QuotationService::class)->draft($expiredQuotation->id);
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

    public function test_update_to_rejected_cancels_linked_invoices_drops_receipt_financial_transactions_and_reopens_member_for_quotation(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-QS-REJECT-UPD-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => QuotationStatus::Converted->value,
            'description' => 'Converted quotation to be rejected via update',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Linked invoice',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'receipt_number' => 'RCT-QS-REJECT-UPD-001',
            'amount' => 100,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $financialYear = FinancialYear::create([
            'year' => 'FY-TEST-QS-001',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'default' => true,
            'is_active' => true,
        ]);

        FinancialTransaction::create([
            'financial_year_id' => $financialYear->id,
            'type' => 'revenue',
            'amount' => 100,
            'description' => 'Receipt revenue',
            'reference_type' => 'App\\Models\\Receipt',
            'reference_id' => $receipt->id,
            'transaction_date' => now()->toDateString(),
        ]);

        app(QuotationService::class)->update([
            'quotation_number' => $quotation->quotation_number,
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'description' => 'Updated to rejected',
            'status' => QuotationStatus::Rejected->value,
            'items' => [
                [
                    'id' => $item->id,
                    'customer_confirmation_member_id' => $member->id,
                    'description' => 'Linked member item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 100,
                    'sort_order' => 1,
                ],
            ],
        ], $quotation->id);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => QuotationStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);

        $this->assertSame(
            0,
            FinancialTransaction::query()
                ->where('reference_type', 'App\\Models\\Receipt')
                ->where('reference_id', $receipt->id)
                ->count(),
        );

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'pending_payment',
        ]);
    }
}

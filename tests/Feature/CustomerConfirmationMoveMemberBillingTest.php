<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfirmationMoveMemberBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_member_splits_quotation_and_transfers_paid_receipt_then_creates_topup_after_group_update(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sourcePackage = Package::create([
            'package_number' => 'PKG-MOVE-SRC-001',
            'name' => 'Move Source Package',
            'status' => 'open',
            'price_single' => 10000,
        ]);

        $targetPackage = Package::create([
            'package_number' => 'PKG-MOVE-TGT-001',
            'name' => 'Move Target Package',
            'status' => 'open',
            'price_single' => 11000,
        ]);

        $sourceConfirmation = CustomerConfirmation::create([
            'package_id' => $sourcePackage->id,
            'created_by' => $user->id,
        ]);

        $payerUser = User::factory()->create();
        $payerCustomer = Customer::create([
            'user_id' => $payerUser->id,
            'customer_number' => 'CUST-MOVE-001',
        ]);

        $otherUser = User::factory()->create();
        $otherCustomer = Customer::create([
            'user_id' => $otherUser->id,
            'customer_number' => 'CUST-MOVE-002',
        ]);

        $movedMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $payerCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $remainingMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $otherCustomer->id,
            'is_leader' => false,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $sourceQuotation = Quotation::create([
            'customer_id' => $payerCustomer->id,
            'customer_confirmation_id' => $sourceConfirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Source quotation before move',
        ]);

        $movedItem = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => $movedMember->id,
            'description' => 'Moved member package item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 10000,
            'sort_order' => 1,
        ]);

        $remainingItem = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => $remainingMember->id,
            'description' => 'Remaining member package item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 10000,
            'sort_order' => 2,
        ]);

        $sourceOrder = Order::create([
            'quotation_id' => $sourceQuotation->id,
            'payment_plan' => 'installment',
        ]);

        $paidInvoice = Invoice::create([
            'order_id' => $sourceOrder->id,
            'description' => 'Paid installment for moved member',
            'amount' => 10000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $paidInvoice->quotationItems()->sync([$movedItem->id]);

        $unpaidInvoice = Invoice::create([
            'order_id' => $sourceOrder->id,
            'description' => 'Issued installment for remaining member',
            'amount' => 10000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $unpaidInvoice->quotationItems()->sync([$remainingItem->id]);

        $paidReceipt = Receipt::create([
            'invoice_id' => $paidInvoice->id,
            'amount' => 10000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'description' => 'Payment for moved member',
        ]);

        $newGroup = app(CustomerConfirmationService::class)->moveMembersToHolding(
            $sourceConfirmation->id,
            [$movedMember->id],
            $targetPackage->id,
        );

        $this->assertSame($targetPackage->id, (int) $newGroup->package_id);

        $newMember = CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->where('customer_id', $payerCustomer->id)
            ->first();

        $this->assertNotNull($newMember);

        $newQuotation = Quotation::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->whereHas('quotationItems', function ($query) use ($newMember) {
                $query->where('customer_confirmation_member_id', $newMember?->id);
            })
            ->first();

        $this->assertNotNull($newQuotation);

        $newOrder = $newQuotation->order;
        $this->assertNotNull($newOrder);

        $newTopUpInvoice = Invoice::query()
            ->where('order_id', $newOrder->id)
            ->where('amount', 1000)
            ->first();
        $this->assertNull($newTopUpInvoice);

        $newPaidReceipt = Receipt::query()
            ->whereIn('invoice_id', Invoice::query()->where('order_id', $newOrder->id)->pluck('id'))
            ->where('amount', 10000)
            ->first();
        $this->assertNotNull($newPaidReceipt);

        app(CustomerConfirmationService::class)->updateGroup($newGroup->id, [
            'package_id' => $targetPackage->id,
            'date_of_application' => now()->format('Y-m-d'),
            'members' => [
                [
                    'member_id' => $newMember->id,
                    'customer_id' => $newMember->customer_id,
                    'name' => $payerUser->name,
                    'email' => $payerUser->email,
                    'contact_number' => $payerUser->contact ?? '00000000',
                    'nric_number' => '',
                    'address' => '',
                    'is_leader' => true,
                    'status' => 'fully_paid',
                    'sharing_plan' => 'single',
                    'relationship' => null,
                ],
            ],
        ]);

        $newTopUpInvoiceAfterUpdate = Invoice::query()
            ->where('order_id', $newOrder->id)
            ->where('amount', 1000)
            ->first();

        $this->assertNotNull($newTopUpInvoiceAfterUpdate);

        $this->assertDatabaseMissing('quotation_items', [
            'id' => $movedItem->id,
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'id' => $remainingItem->id,
            'quotation_id' => $sourceQuotation->id,
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $newGroupedRow = collect($grouped)->firstWhere('id', $newGroup->id);

        $this->assertNotNull($newGroupedRow);
        $this->assertSame(10000.0, (float) ($newGroupedRow['paid_amount'] ?? 0));
        $this->assertSame(11000.0, (float) ($newGroupedRow['total_amount'] ?? 0));
    }

    public function test_refund_creation_does_not_cancel_member(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = Package::create([
            'package_number' => 'PKG-REFUND-001',
            'name' => 'Refund Package',
            'status' => 'open',
            'price_single' => 10000,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'created_by' => $user->id,
        ]);

        $memberUser = User::factory()->create();
        $memberCustomer = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CUST-REFUND-001',
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $memberCustomer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $memberCustomer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 10000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Member invoice',
            'amount' => 10000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        app(CustomerConfirmationService::class)->createRefundReceipts($confirmation->id, [
            [
                'member_id' => $member->id,
                'mode' => 'fixed',
                'amount' => 1000,
            ],
        ]);

        $member->refresh();

        $this->assertNotSame('cancelled', $member->status);
    }

    public function test_moving_member_reuses_dedicated_paid_quotation_and_switches_customer_to_moved_leader(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sourcePackage = Package::create([
            'package_number' => 'PKG-MOVE-DED-001',
            'name' => 'Move Dedicated Source',
            'status' => 'open',
            'price_single' => 5000,
        ]);

        $sourceConfirmation = CustomerConfirmation::create([
            'package_id' => $sourcePackage->id,
            'created_by' => $user->id,
        ]);

        $memberUser = User::factory()->create();
        $memberCustomer = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CUST-MOVE-DED-001',
        ]);

        $otherPayerUser = User::factory()->create();
        $otherPayerCustomer = Customer::create([
            'user_id' => $otherPayerUser->id,
            'customer_number' => 'CUST-MOVE-DED-002',
        ]);

        $sourceMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $memberCustomer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
            'sharing_plan' => 'single',
        ]);

        $sourceQuotation = Quotation::create([
            'customer_id' => $otherPayerCustomer->id,
            'customer_confirmation_id' => $sourceConfirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Dedicated quotation for moved member',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => $sourceMember->id,
            'description' => 'Dedicated moved item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $sourceQuotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Dedicated invoice',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $newGroup = app(CustomerConfirmationService::class)->moveMembersToHolding(
            $sourceConfirmation->id,
            [$sourceMember->id],
            null,
        );

        $newMember = CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->where('customer_id', $memberCustomer->id)
            ->first();

        $this->assertNotNull($newMember);

        $sourceQuotation->refresh();

        $this->assertSame((int) $newGroup->id, (int) $sourceQuotation->customer_confirmation_id);
        $this->assertSame((int) $memberCustomer->id, (int) $sourceQuotation->customer_id);

        $item->refresh();
        $this->assertSame((int) $newMember->id, (int) $item->customer_confirmation_member_id);

        $this->assertDatabaseMissing('customer_confirmation_members', [
            'id' => $sourceMember->id,
            'status' => 'partially_paid',
        ]);
    }

    public function test_overpayment_refund_creates_refund_receipt_for_overpaid_amount_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = Package::create([
            'package_number' => 'PKG-OVERPAY-001',
            'name' => 'Overpayment Package',
            'status' => 'open',
            'price_single' => 4000,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'created_by' => $user->id,
        ]);

        $memberUser = User::factory()->create();
        $memberCustomer = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CUST-OVERPAY-001',
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $memberCustomer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $memberCustomer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Overpaid item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Overpaid invoice',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $result = app(CustomerConfirmationService::class)->createOverpaymentRefundReceipts(
            $confirmation->id,
            [$member->id],
        );

        $this->assertSame(1, (int) ($result['count'] ?? 0));

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_method', 'overpayment_refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);
        $this->assertSame(-1000.0, (float) ($refundReceipt->amount ?? 0));

        $member->refresh();
        $this->assertNotSame('cancelled', $member->status);
    }
}

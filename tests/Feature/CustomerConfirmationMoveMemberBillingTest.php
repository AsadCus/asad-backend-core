<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemTax;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerConfirmationMoveMemberBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_members_route_only_removes_selected_member_from_manifest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = Package::create([
            'package_number' => 'PKG-MOVE-MAN-001',
            'name' => 'Move Manifest Package',
            'status' => 'open',
            'price_single' => 5000,
        ]);

        $sourceConfirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'created_by' => $user->id,
            'is_holding' => false,
        ]);

        $memberOneUser = User::factory()->create();
        $memberOneCustomer = Customer::create([
            'user_id' => $memberOneUser->id,
            'customer_number' => 'CUST-MOVE-MAN-001',
        ]);

        $memberTwoUser = User::factory()->create();
        $memberTwoCustomer = Customer::create([
            'user_id' => $memberTwoUser->id,
            'customer_number' => 'CUST-MOVE-MAN-002',
        ]);

        $movedMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $memberOneCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $remainingMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $memberTwoCustomer->id,
            'is_leader' => false,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-MOVE-MAN-001',
        ]);

        $manifestMemberToMove = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $movedMember->id,
            'name' => $memberOneUser->name,
            'sort_order' => 1,
        ]);

        $manifestMemberToKeep = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $remainingMember->id,
            'name' => $memberTwoUser->name,
            'sort_order' => 2,
        ]);

        $response = $this->post(route('customer-confirmations.move-members', [
            'id' => $sourceConfirmation->id,
        ]), [
            'member_ids' => [$movedMember->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('manifest_members', [
            'id' => $manifestMemberToMove->id,
        ]);

        $this->assertDatabaseHas('manifest_members', [
            'id' => $manifestMemberToKeep->id,
            'customer_confirmation_member_id' => $remainingMember->id,
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $movedMember->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $remainingMember->id,
            'status' => 'pending_payment',
        ]);
    }

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
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $targetManifest = Manifest::create([
            'package_id' => $targetPackage->id,
            'manifest_number' => 'MNF-MOVE-TGT-001',
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
        $this->assertFalse((bool) $newGroup->is_holding);

        $newMember = CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->where('customer_id', $payerCustomer->id)
            ->first();

        $this->assertNotNull($newMember);
        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $targetManifest->id,
            'customer_confirmation_member_id' => $newMember?->id,
        ]);

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

        $holdingRows = app(CustomerConfirmationService::class)->getForHoldingIndex();
        $confirmedRows = app(CustomerConfirmationService::class)->getForConfirmedIndex();

        $this->assertNull(collect($holdingRows)->firstWhere('id', $newGroup->id));
        $this->assertNotNull(collect($confirmedRows)->firstWhere('id', $newGroup->id));

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

        $holdingRowsAfterPackageSelect = app(CustomerConfirmationService::class)->getForHoldingIndex();
        $confirmedRowsAfterPackageSelect = app(CustomerConfirmationService::class)->getForConfirmedIndex();

        $this->assertNull(collect($holdingRowsAfterPackageSelect)->firstWhere('id', $newGroup->id));
        $this->assertNotNull(collect($confirmedRowsAfterPackageSelect)->firstWhere('id', $newGroup->id));
    }

    public function test_selecting_package_on_existing_confirmation_auto_links_member_with_paid_amount_to_open_manifest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $selectedPackage = Package::create([
            'package_number' => 'PKG-SELECT-MAN-001',
            'name' => 'Select Package Manifest Target',
            'status' => 'open',
            'price_single' => 10000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $targetManifest = Manifest::create([
            'package_id' => $selectedPackage->id,
            'manifest_number' => 'MNF-SELECT-MAN-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => null,
            'created_by' => $user->id,
            'is_holding' => true,
        ]);

        $memberUser = User::factory()->create([
            'name' => 'Select Package Member',
            'email' => 'select-package-member@example.com',
        ]);

        $memberCustomer = Customer::create([
            'user_id' => $memberUser->id,
            'customer_number' => 'CUST-SELECT-MAN-001',
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $memberCustomer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $memberCustomer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
            'description' => 'No package paid quotation before package select',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Paid member item before package select',
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
            'description' => 'Paid member invoice before package select',
            'amount' => 10000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'description' => 'Paid before package selected',
        ]);

        app(CustomerConfirmationService::class)->updateGroup($confirmation->id, [
            'package_id' => $selectedPackage->id,
            'date_of_application' => now()->format('Y-m-d'),
            'members' => [
                [
                    'member_id' => $member->id,
                    'customer_id' => $member->customer_id,
                    'name' => $memberUser->name,
                    'email' => $memberUser->email,
                    'contact_number' => $memberUser->contact ?? '00000000',
                    'nric_number' => '',
                    'address' => '',
                    'is_leader' => true,
                    'status' => 'pending_payment',
                    'sharing_plan' => 'single',
                    'relationship' => null,
                ],
            ],
        ]);

        $member->refresh();

        $this->assertSame('fully_paid', (string) $member->status);
        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $targetManifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);
    }

    public function test_refund_creation_marks_member_as_cancelled(): void
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
        ], 'cancel');

        $member->refresh();

        $this->assertSame('cancelled', $member->status);
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

    public function test_moving_member_preserves_original_header_and_splits_extensions_with_paid_invoice_consistency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sourcePackage = Package::create([
            'package_number' => 'PKG-MOVE-EXT-001',
            'name' => 'Move Extension Source',
            'status' => 'open',
            'price_single' => 10000,
        ]);

        $targetPackage = Package::create([
            'package_number' => 'PKG-MOVE-EXT-002',
            'name' => 'Move Extension Target',
            'status' => 'open',
            'price_single' => 10000,
        ]);

        $sourceConfirmation = CustomerConfirmation::create([
            'package_id' => $sourcePackage->id,
            'created_by' => $user->id,
        ]);

        $movedUser = User::factory()->create();
        $movedCustomer = Customer::create([
            'user_id' => $movedUser->id,
            'customer_number' => 'CUST-MOVE-EXT-001',
        ]);

        $remainingUser = User::factory()->create();
        $remainingCustomer = Customer::create([
            'user_id' => $remainingUser->id,
            'customer_number' => 'CUST-MOVE-EXT-002',
        ]);

        $movedMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $movedCustomer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $remainingMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $sourceConfirmation->id,
            'customer_id' => $remainingCustomer->id,
            'is_leader' => false,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $sourceQuotation = Quotation::create([
            'customer_id' => $movedCustomer->id,
            'customer_confirmation_id' => $sourceConfirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Split extensions quotation',
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => null,
            'parent_id' => null,
            'description' => 'Hotel Package',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $movedItem = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => $movedMember->id,
            'parent_id' => $header->id,
            'description' => 'Moved member hotel item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 10000,
            'sort_order' => 2,
        ]);

        $remainingItem = QuotationItem::create([
            'quotation_id' => $sourceQuotation->id,
            'customer_confirmation_member_id' => $remainingMember->id,
            'parent_id' => $header->id,
            'description' => 'Remaining member hotel item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 10000,
            'sort_order' => 3,
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $movedItem->id,
            'name' => 'Fixed Tax',
            'calculation_mode' => 'fixed',
            'calculation_value' => 200,
            'sort_order' => 1,
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $movedItem->id,
            'name' => 'Percentage Discount',
            'calculation_mode' => 'percentage',
            'calculation_value' => -10,
            'sort_order' => 2,
        ]);

        $sourceOrder = Order::create([
            'quotation_id' => $sourceQuotation->id,
            'payment_plan' => 'installment',
        ]);

        $sourceInvoice = Invoice::create([
            'order_id' => $sourceOrder->id,
            'description' => 'Shared paid invoice with mixed extensions',
            'amount' => 17500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'extensions' => [
                [
                    'id' => null,
                    'name' => 'Fixed Service Fee',
                    'type' => 'other',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => 300,
                    'sort_order' => 1,
                ],
                [
                    'id' => null,
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'percentage',
                    'calculation_value' => -10,
                    'amount' => -2000,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $sourceInvoice->quotationItems()->sync([$movedItem->id, $remainingItem->id]);

        Receipt::create([
            'invoice_id' => $sourceInvoice->id,
            'amount' => 17500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'description' => 'Paid split source',
        ]);

        $newGroup = app(CustomerConfirmationService::class)->moveMembersToHolding(
            $sourceConfirmation->id,
            [$movedMember->id],
            $targetPackage->id,
        );

        $newMember = CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->where('customer_id', $movedCustomer->id)
            ->first();

        $this->assertNotNull($newMember);

        $newQuotation = Quotation::query()
            ->where('customer_confirmation_id', $newGroup->id)
            ->first();

        $this->assertNotNull($newQuotation);

        $newHeader = QuotationItem::query()
            ->where('quotation_id', $newQuotation->id)
            ->where('is_header', true)
            ->where('description', 'Hotel Package')
            ->first();

        $this->assertNotNull($newHeader);

        $newMovedItem = QuotationItem::query()
            ->where('quotation_id', $newQuotation->id)
            ->where('customer_confirmation_member_id', $newMember->id)
            ->where('is_header', false)
            ->first();

        $this->assertNotNull($newMovedItem);
        $this->assertSame((int) $newHeader->id, (int) ($newMovedItem->parent_id ?? 0));

        $newMovedTaxes = QuotationItemTax::query()
            ->where('quotation_item_id', $newMovedItem->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $newMovedTaxes);
        $this->assertSame('fixed', (string) ($newMovedTaxes[0]->calculation_mode ?? ''));
        $this->assertSame(200.0, (float) ($newMovedTaxes[0]->calculation_value ?? 0));
        $this->assertSame('percentage', (string) ($newMovedTaxes[1]->calculation_mode ?? ''));
        $this->assertSame(-10.0, (float) ($newMovedTaxes[1]->calculation_value ?? 0));

        $newOrder = $newQuotation->order;
        $this->assertNotNull($newOrder);

        $newInvoice = Invoice::query()
            ->where('order_id', $newOrder->id)
            ->first();

        $this->assertNotNull($newInvoice);

        $newExtensions = collect(is_array($newInvoice->extensions) ? $newInvoice->extensions : []);
        $this->assertCount(1, $newExtensions);
        $this->assertSame('percentage', strtolower((string) ($newExtensions->first()['calculation_mode'] ?? '')));
        $this->assertSame(-1000.0, (float) ($newExtensions->first()['amount'] ?? 0));

        $sourceInvoice->refresh();
        $sourceExtensions = collect(is_array($sourceInvoice->extensions) ? $sourceInvoice->extensions : []);
        $this->assertCount(2, $sourceExtensions);

        $sourceFixedExtension = $sourceExtensions->firstWhere('calculation_mode', 'fixed');
        $this->assertNotNull($sourceFixedExtension);
        $this->assertSame(300.0, (float) ($sourceFixedExtension['amount'] ?? 0));

        $sourcePercentageExtension = $sourceExtensions->firstWhere('calculation_mode', 'percentage');
        $this->assertNotNull($sourcePercentageExtension);
        $this->assertSame(-1000.0, (float) ($sourcePercentageExtension['amount'] ?? 0));

        $this->assertSame(8200.0, (float) ($newInvoice->amount ?? 0));
        $this->assertSame(9300.0, (float) ($sourceInvoice->amount ?? 0));

        $newInvoiceReceiptTotal = (float) Receipt::query()
            ->where('invoice_id', $newInvoice->id)
            ->sum('amount');

        $sourceInvoiceReceiptTotal = (float) Receipt::query()
            ->where('invoice_id', $sourceInvoice->id)
            ->sum('amount');

        $this->assertSame(8200.0, round($newInvoiceReceiptTotal, 2));
        $this->assertSame(9300.0, round($sourceInvoiceReceiptTotal, 2));

        $newInvoice->refresh();
        $sourceInvoice->refresh();

        $this->assertSame('paid', (string) ($newInvoice->status ?? ''));
        $this->assertSame('paid', (string) ($sourceInvoice->status ?? ''));
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

        $refundInvoice = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', 'refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundInvoice);

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->where('payment_method', 'overpayment_refund')
            ->latest('id')
            ->first();

        $this->assertNotNull($refundReceipt);
        $this->assertSame(-1000.0, (float) ($refundReceipt->amount ?? 0));

        $member->refresh();
        $this->assertNotSame('cancelled', $member->status);
    }
}

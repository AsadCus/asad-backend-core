<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemTax;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceiptMemberStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createConfirmationWithQuotationOrder(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $package = Package::create([
            'package_number' => 'PKG-TEST',
            'name' => 'Test Package',
            'status' => 'open',
            'price_single' => 5000,
            'price_double' => 3500,
            'total_seats' => 10,
            'seats_left' => 10,
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-001',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Single Sharing — Test Member',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $depositInvoice = Invoice::create([
            'order_id' => $order->id,
            'type' => 'deposit',
            'description' => 'Invoice For Deposit',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $depositInvoice->quotationItems()->sync([$item->id]);

        return compact(
            'user',
            'package',
            'customer',
            'confirmation',
            'member',
            'quotation',
            'item',
            'order',
            'depositInvoice',
        );
    }

    public function test_full_payment_receipt_sets_member_to_fully_paid(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('fully_paid', $data['member']->status);
    }

    public function test_partial_payment_receipt_sets_member_to_partially_paid(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        // Create a second invoice (balance) so that paying only the deposit is partial
        Invoice::create([
            'order_id' => $data['order']->id,
            'type' => 'handover',
            'description' => 'Invoice For Balance',
            'amount' => 2000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'issued',
        ])->quotationItems()->sync([$data['item']->id]);

        // Update deposit invoice to be only partial (3000 of 5000 total)
        $data['depositInvoice']->update(['amount' => 3000]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 3000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('partially_paid', $data['member']->status);
    }

    public function test_discounted_invoice_payable_marks_member_as_fully_paid_when_required_amount_is_met(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['quotation']->update([
            'extensions' => [
                [
                    'name' => 'Quotation Promo',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $data['depositInvoice']->update([
            'amount' => 4500,
            'extensions' => [
                [
                    'name' => 'Invoice Promo',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 4500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('fully_paid', $data['member']->status);
    }

    public function test_installment_receipts_set_member_to_fully_paid_when_all_linked_invoices_paid(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['depositInvoice']->update(['amount' => 3000]);

        $balanceInvoice = Invoice::create([
            'order_id' => $data['order']->id,
            'type' => 'handover',
            'description' => 'Invoice For Balance',
            'amount' => 2000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $balanceInvoice->quotationItems()->sync([$data['item']->id]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 3000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('partially_paid', $data['member']->status);

        Receipt::create([
            'invoice_id' => $balanceInvoice->id,
            'amount' => 2000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('fully_paid', $data['member']->status);
    }

    public function test_deleting_receipt_reverts_member_to_pending_payment(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $receipt = Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('fully_paid', $data['member']->status);

        $receipt->delete();

        $data['member']->refresh();
        $this->assertEquals('pending_payment', $data['member']->status);
    }

    public function test_receipt_does_not_affect_cancelled_members(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        // Set member to cancelled before receipt
        $data['member']->update(['status' => 'cancelled']);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('cancelled', $data['member']->status);
    }

    public function test_receipt_moves_pending_payment_member_to_fully_paid_when_invoice_is_fully_paid(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('fully_paid', $data['member']->status);
    }

    public function test_receipt_on_non_confirmation_quotation_does_not_fail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-STANDALONE',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Standalone Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);

        // Should not throw an exception
        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    public function test_grouped_index_paid_amount_reflects_paid_invoice_even_without_receipt_allocation_rows(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['depositInvoice']->update([
            'status' => 'paid',
            'amount' => 5000,
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);

        $group = collect($grouped)->firstWhere('id', $data['confirmation']->id);
        $this->assertNotNull($group);
        $this->assertSame(5000.0, (float) ($group['paid_amount'] ?? 0));

        $memberRow = collect($group['members'] ?? [])->firstWhere('id', $data['member']->id);
        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['paid_amount'] ?? 0));
    }

    public function test_grouped_index_totals_consider_negative_quotation_and_invoice_extensions(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['quotation']->update([
            'extensions' => [
                [
                    'name' => 'Quotation Promo',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 300,
                    'amount' => -300,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $data['depositInvoice']->update([
            'extensions' => [
                [
                    'name' => 'Invoice Promo',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 200,
                    'amount' => -200,
                    'sort_order' => 1,
                ],
            ],
            'status' => 'paid',
            'amount' => 4500,
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $group = collect($grouped)->firstWhere('id', $data['confirmation']->id);

        $this->assertNotNull($group);
        $this->assertSame(5000.0, (float) ($group['total_amount'] ?? 0));
        $this->assertSame(5000.0, (float) ($group['paid_amount'] ?? 0));

        $memberRow = collect($group['members'] ?? [])->firstWhere('id', $data['member']->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['total_amount'] ?? 0));
        $this->assertSame(5000.0, (float) ($memberRow['paid_amount'] ?? 0));
    }

    public function test_grouped_index_paid_amount_excludes_positive_invoice_extensions_for_paid_invoice_fallback(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['depositInvoice']->update([
            'extensions' => [
                [
                    'name' => 'Admin Fee',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => 500,
                    'sort_order' => 1,
                ],
            ],
            'status' => 'paid',
            'amount' => 5500,
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $group = collect($grouped)->firstWhere('id', $data['confirmation']->id);

        $this->assertNotNull($group);
        $this->assertSame(5000.0, (float) ($group['paid_amount'] ?? 0));

        $memberRow = collect($group['members'] ?? [])->firstWhere('id', $data['member']->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['paid_amount'] ?? 0));
    }

    public function test_grouped_index_paid_amount_excludes_positive_invoice_extensions_for_receipt_totals(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['depositInvoice']->update([
            'extensions' => [
                [
                    'name' => 'Invoice Tax',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => 500,
                    'sort_order' => 1,
                ],
            ],
            'amount' => 5500,
            'status' => 'issued',
        ]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $group = collect($grouped)->firstWhere('id', $data['confirmation']->id);

        $this->assertNotNull($group);
        $this->assertSame(5000.0, (float) ($group['paid_amount'] ?? 0));

        $memberRow = collect($group['members'] ?? [])->firstWhere('id', $data['member']->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(5000.0, (float) ($memberRow['paid_amount'] ?? 0));
    }

    public function test_grouped_index_paid_amount_excludes_positive_item_tax_and_respects_discounted_package_payable(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['package']->update([
            'price_single' => 3000,
        ]);

        $data['quotation']->update([
            'extensions' => [
                [
                    'name' => 'Promo Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 500,
                    'amount' => -500,
                    'sort_order' => 1,
                ],
            ],
        ]);

        QuotationItemTax::create([
            'quotation_item_id' => $data['item']->id,
            'name' => 'Item Tax',
            'calculation_mode' => 'fixed',
            'calculation_value' => 210,
            'sort_order' => 1,
        ]);

        $data['depositInvoice']->update([
            'amount' => 1210,
            'status' => 'issued',
        ]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 1210,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $grouped = app(CustomerConfirmationService::class)->getForGroupedIndex(true);
        $group = collect($grouped)->firstWhere('id', $data['confirmation']->id);

        $this->assertNotNull($group);
        $this->assertSame(3000.0, (float) ($group['total_amount'] ?? 0));
        $this->assertSame(1210.0, (float) ($group['paid_amount'] ?? 0));

        $memberRow = collect($group['members'] ?? [])->firstWhere('id', $data['member']->id);

        $this->assertNotNull($memberRow);
        $this->assertSame(3000.0, (float) ($memberRow['total_amount'] ?? 0));
        $this->assertSame(1210.0, (float) ($memberRow['paid_amount'] ?? 0));
    }

    public function test_when_package_is_full_paid_member_reverts_to_pending_payment_and_is_not_linked_to_manifest(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['package']->update([
            'total_seats' => 0,
            'seats_left' => 0,
        ]);

        $manifest = Manifest::create([
            'package_id' => $data['package']->id,
            'manifest_number' => 'MNF-SEAT-CAP-001',
            'status' => 'draft',
        ]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $data['package']->refresh();

        $this->assertEquals('pending_payment', $data['member']->status);
        $this->assertEquals(0, $data['package']->seats_left);
        $this->assertFalse($manifest->members()->where('customer_confirmation_member_id', $data['member']->id)->exists());
    }

    public function test_cancelling_paid_member_is_blocked_and_keeps_manifest_member_link(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $data['package']->update([
            'total_seats' => 2,
            'seats_left' => 2,
        ]);

        $manifest = Manifest::create([
            'package_id' => $data['package']->id,
            'manifest_number' => 'MNF-SEAT-CAP-002',
            'status' => 'draft',
        ]);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $member = $manifest->members()->where('customer_confirmation_member_id', $data['member']->id)->first();

        $this->assertNotNull($member);

        $data['package']->refresh();
        $this->assertEquals(1, $data['package']->seats_left);

        try {
            app(CustomerConfirmationService::class)->cancelMember((int) $data['member']->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('member', $exception->errors());
        }

        $data['member']->refresh();
        $data['package']->refresh();

        $this->assertEquals('fully_paid', $data['member']->status);
        $this->assertEquals(1, $data['package']->seats_left);
        $this->assertDatabaseHas('manifest_members', [
            'id' => $member?->id,
        ]);
    }

    public function test_cancelling_unpaid_member_deletes_unpaid_quotation_and_invoice_records(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        $quotationId = (int) $data['quotation']->id;
        $invoiceId = (int) $data['depositInvoice']->id;
        $orderId = (int) $data['order']->id;
        $quotationItemId = (int) $data['item']->id;

        app(CustomerConfirmationService::class)->cancelMember((int) $data['member']->id);

        $data['member']->refresh();

        $this->assertSame('cancelled', (string) $data['member']->status);
        $this->assertDatabaseMissing('quotations', [
            'id' => $quotationId,
        ]);
        $this->assertDatabaseMissing('orders', [
            'id' => $orderId,
        ]);
        $this->assertDatabaseMissing('invoices', [
            'id' => $invoiceId,
        ]);
        $this->assertDatabaseMissing('quotation_items', [
            'id' => $quotationItemId,
        ]);
    }

    public function test_split_quotation_payments_create_separate_manifest_groups_and_rooms_for_same_confirmation(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $package = Package::create([
            'package_number' => 'PKG-SPLIT-001',
            'name' => 'Split Package',
            'status' => 'open',
            'room_type' => 'triple',
            'price_triple' => 3000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-SPLIT-001',
            'status' => 'draft',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
        ]);

        $members = collect(range(1, 5))->map(function (int $index) use ($confirmation) {
            $memberUser = User::factory()->create();
            $customer = Customer::create([
                'user_id' => $memberUser->id,
                'customer_number' => 'CUST-SPLIT-'.$index,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 1,
                'status' => 'pending_payment',
                'sharing_plan' => 'triple',
            ]);
        });

        $quotationOne = Quotation::create([
            'customer_id' => $members[0]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationTwo = Quotation::create([
            'customer_id' => $members[3]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        foreach ([0, 1, 2] as $index) {
            QuotationItem::create([
                'quotation_id' => $quotationOne->id,
                'customer_confirmation_member_id' => $members[$index]->id,
                'description' => 'Q1 member #'.$members[$index]->id,
                'is_header' => false,
                'quantity' => 1,
                'rate' => 3000,
                'sort_order' => $index + 1,
            ]);
        }

        foreach ([3, 4] as $offset => $index) {
            QuotationItem::create([
                'quotation_id' => $quotationTwo->id,
                'customer_confirmation_member_id' => $members[$index]->id,
                'description' => 'Q2 member #'.$members[$index]->id,
                'is_header' => false,
                'quantity' => 1,
                'rate' => 3000,
                'sort_order' => $offset + 1,
            ]);
        }

        $orderOne = Order::create([
            'quotation_id' => $quotationOne->id,
            'payment_plan' => 'full',
        ]);

        $orderTwo = Order::create([
            'quotation_id' => $quotationTwo->id,
            'payment_plan' => 'full',
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $orderOne->id,
            'description' => 'Q1 Invoice',
            'amount' => 9000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync(
            $quotationOne->quotationItems()->pluck('id')->all()
        );

        $invoiceTwo = Invoice::create([
            'order_id' => $orderTwo->id,
            'description' => 'Q2 Invoice',
            'amount' => 6000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync(
            $quotationTwo->quotationItems()->pluck('id')->all()
        );

        Receipt::create([
            'invoice_id' => $invoiceOne->id,
            'amount' => 9000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $this->assertDatabaseHas('manifest_sharing_groups', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_id' => $confirmation->id,
            'source_quotation_id' => $quotationOne->id,
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'source_quotation_id' => $quotationOne->id,
            'room_type' => 'triple',
            'capacity' => 3,
        ]);

        $roomOneId = (int) \DB::table('manifest_rooms')
            ->where('manifest_id', $manifest->id)
            ->where('source_quotation_id', $quotationOne->id)
            ->value('id');

        $this->assertSame(
            3,
            \DB::table('manifest_room_members')
                ->where('manifest_room_id', $roomOneId)
                ->count()
        );

        Receipt::create([
            'invoice_id' => $invoiceTwo->id,
            'amount' => 6000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $this->assertDatabaseHas('manifest_sharing_groups', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_id' => $confirmation->id,
            'source_quotation_id' => $quotationTwo->id,
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'source_quotation_id' => $quotationTwo->id,
            'room_type' => 'triple',
            'capacity' => 3,
        ]);

        $roomTwoId = (int) \DB::table('manifest_rooms')
            ->where('manifest_id', $manifest->id)
            ->where('source_quotation_id', $quotationTwo->id)
            ->value('id');

        $this->assertSame(
            2,
            \DB::table('manifest_room_members')
                ->where('manifest_room_id', $roomTwoId)
                ->count()
        );
    }

    public function test_receipt_sync_preserves_existing_manual_manifest_group_for_member(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-MANUAL-GROUP-001',
            'name' => 'Manual Group Preserve Package',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 10,
            'seats_left' => 10,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-MANUAL-GROUP-001',
            'status' => 'draft',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
        ]);

        $memberUsers = [
            User::factory()->create(['name' => 'Manual Group Member One']),
            User::factory()->create(['name' => 'Manual Group Member Two']),
        ];

        $members = collect($memberUsers)->map(function (User $user, int $index) use ($confirmation) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_number' => 'CUST-MANUAL-'.$index,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 0,
                'status' => 'pending_payment',
                'sharing_plan' => 'double',
            ]);
        })->values();

        $manualGroupId = (int) \DB::table('manifest_sharing_groups')->insertGetId([
            'manifest_id' => $manifest->id,
            'customer_confirmation_id' => $confirmation->id,
            'source_quotation_id' => null,
            'sort_order' => 1,
            'group_relationship' => null,
            'remarks' => 'Manual grouping',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manifestMemberIds = $members->map(function (CustomerConfirmationMember $member, int $index) use ($manifest, $manualGroupId): int {
            return (int) \DB::table('manifest_members')->insertGetId([
                'manifest_id' => $manifest->id,
                'manifest_sharing_group_id' => $manualGroupId,
                'customer_confirmation_member_id' => $member->id,
                'sharing_plan' => 'double',
                'name' => 'Manual Member '.($index + 1),
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        })->values();

        $quotation = Quotation::create([
            'customer_id' => $members[0]->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $members[0]->id,
            'description' => 'Manual preserve member',
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
            'description' => 'Manual preserve invoice',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $this->assertSame(
            $manualGroupId,
            (int) \DB::table('manifest_members')->where('id', $manifestMemberIds[0])->value('manifest_sharing_group_id')
        );

        $this->assertSame(
            0,
            (int) \DB::table('manifest_sharing_groups')
                ->where('manifest_id', $manifest->id)
                ->where('source_quotation_id', $quotation->id)
                ->count()
        );
    }
}

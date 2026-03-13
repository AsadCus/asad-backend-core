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
use App\Models\Receipt;
use App\Models\User;
use App\Services\CustomerConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_full_payment_receipt_sets_member_to_confirmed(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('confirmed', $data['member']->status);
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

    public function test_installment_receipts_set_member_to_confirmed_when_all_linked_invoices_paid(): void
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
        $this->assertEquals('confirmed', $data['member']->status);
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
        $this->assertEquals('confirmed', $data['member']->status);

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

    public function test_receipt_does_not_affect_draft_members(): void
    {
        $data = $this->createConfirmationWithQuotationOrder();

        // Set member to draft (not yet in payment flow)
        $data['member']->update(['status' => 'draft']);

        Receipt::create([
            'invoice_id' => $data['depositInvoice']->id,
            'amount' => 5000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $data['member']->refresh();
        $this->assertEquals('draft', $data['member']->status);
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

    public function test_when_package_is_full_paid_member_becomes_unavailable_and_not_linked_to_manifest(): void
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

        $this->assertEquals('unavailable', $data['member']->status);
        $this->assertEquals(0, $data['package']->seats_left);
        $this->assertFalse($manifest->travelers()->where('customer_confirmation_member_id', $data['member']->id)->exists());
    }

    public function test_cancelling_paid_member_releases_package_seat_and_removes_manifest_traveler_link(): void
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

        $traveler = $manifest->travelers()->where('customer_confirmation_member_id', $data['member']->id)->first();

        $this->assertNotNull($traveler);

        $data['package']->refresh();
        $this->assertEquals(1, $data['package']->seats_left);

        app(CustomerConfirmationService::class)->cancelMember((int) $data['member']->id);

        $data['member']->refresh();
        $data['package']->refresh();

        $this->assertEquals('cancelled', $data['member']->status);
        $this->assertEquals(2, $data['package']->seats_left);
        $this->assertDatabaseMissing('manifest_members', [
            'id' => $traveler?->id,
        ]);
    }
}

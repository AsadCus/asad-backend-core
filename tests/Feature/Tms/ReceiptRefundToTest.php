<?php

namespace Tests\Feature\Tms;

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
use App\Services\ReceiptService;
use Tests\TmsTestCase as TestCase;

class ReceiptRefundToTest extends TestCase
{
    /**
     * Set up a basic customer, package, member, quotation, order, invoice, and receipt graph.
     *
     * @return array<string, mixed>
     */
    private function createBaseGraph(string $contact = '12345678'): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create([
            'name' => 'Refund User Test',
            'email' => 'refund-test@example.com',
            'contact' => $contact,
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-REF-101',
            'address' => '123 Test Rd',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-REF-101',
            'name' => 'Refund Test Package',
            'status' => 'open',
            'price_single' => 1000,
        ]);

        $group = CustomerConfirmation::create([
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'fully_paid',
            'sharing_plan' => 'single',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $group->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $baseItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Base Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Base Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);

        $invoice->quotationItems()->sync([$baseItem->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        return compact('group', 'member', 'invoice', 'receipt', 'order');
    }

    public function test_refund_stores_given_refund_to_field(): void
    {
        $graph = $this->createBaseGraph();

        $response = $this->post(route('customer-confirmations.refunds.store', $graph['group']->id), [
            'refund_type' => 'cancel',
            'member_refunds' => [
                [
                    'member_id' => $graph['member']->id,
                    'mode' => 'fixed',
                    'amount' => 500,
                    'refund_to' => '99998888',
                    'payment_method' => 'transfer',
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $graph['order']->id)
            ->where('status', 'refund')
            ->firstOrFail();

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->firstOrFail();

        $this->assertSame('99998888', (string) $refundReceipt->refund_to);
    }

    public function test_refund_defaults_refund_to_to_member_contact_if_empty(): void
    {
        $graph = $this->createBaseGraph('77776666');

        $response = $this->post(route('customer-confirmations.refunds.store', $graph['group']->id), [
            'refund_type' => 'cancel',
            'member_refunds' => [
                [
                    'member_id' => $graph['member']->id,
                    'mode' => 'fixed',
                    'amount' => 500,
                    'payment_method' => 'transfer',
                ],
            ],
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundInvoice = Invoice::query()
            ->where('order_id', $graph['order']->id)
            ->where('status', 'refund')
            ->firstOrFail();

        $refundReceipt = Receipt::query()
            ->where('invoice_id', $refundInvoice->id)
            ->firstOrFail();

        $this->assertSame('77776666', (string) $refundReceipt->refund_to);
    }

    public function test_receipt_report_payload_contains_customer_details_and_refund_to(): void
    {
        $graph = $this->createBaseGraph('98765432');

        $refundInvoice = Invoice::create([
            'order_id' => $graph['order']->id,
            'description' => 'Refund Invoice',
            'amount' => -500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);

        $refundReceipt = Receipt::create([
            'invoice_id' => $refundInvoice->id,
            'amount' => -500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'refund_to' => 'Custom Refund Target',
        ]);

        $reportData = app(ReceiptService::class)->getForEditShow($refundReceipt->id);

        $this->assertSame('CUST-REF-101', (string) ($reportData['customer_number'] ?? ''));
        $this->assertSame('refund-test@example.com', (string) ($reportData['customer_email'] ?? ''));
        $this->assertSame('98765432', (string) ($reportData['customer_contact'] ?? ''));
        $this->assertSame('Custom Refund Target', (string) ($reportData['refund_to'] ?? ''));
    }

    public function test_get_member_receipts_for_pdf_returns_refund_to(): void
    {
        $graph = $this->createBaseGraph('98765432');

        $refundInvoice = Invoice::create([
            'order_id' => $graph['order']->id,
            'description' => 'Refund Invoice',
            'amount' => -500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);

        $refundReceipt = Receipt::create([
            'invoice_id' => $refundInvoice->id,
            'amount' => -500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'refund_to' => 'Custom Member Refund Target',
        ]);

        // Link refund invoice to the member's quotation item so it gets loaded
        $refundInvoice->quotationItems()->sync([$graph['member']->quotationItems->first()->id]);

        // Eager load relations like in controller/service
        $pdfData = app(CustomerConfirmationService::class)->getMemberReceiptsForPdf($graph['group']->id, $graph['member']->id);

        $receiptList = $pdfData['receipts'] ?? [];
        $refundItem = collect($receiptList)->firstWhere('amount', -500.0);

        $this->assertNotNull($refundItem);
        $this->assertSame('Custom Member Refund Target', (string) ($refundItem['refund_to'] ?? ''));
    }

    public function test_can_update_refund_to_field_on_refund_receipt(): void
    {
        $graph = $this->createBaseGraph('98765432');

        $refundInvoice = Invoice::create([
            'order_id' => $graph['order']->id,
            'description' => 'Refund Invoice',
            'amount' => -500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);

        $refundReceipt = Receipt::create([
            'invoice_id' => $refundInvoice->id,
            'amount' => -500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'refund_to' => 'Original Refund Target',
        ]);

        $response = $this->put(route('receipt.update', $refundReceipt->id), [
            'receipt_number' => 'REF-REC-999',
            'invoice_id' => $refundInvoice->id,
            'amount' => -500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'refund_to' => 'Updated Refund Target',
            'reference' => 'REF123',
            'description' => 'Updated notes',
        ]);

        $response->assertRedirect(route('receipt.index'));

        $refundReceipt->refresh();
        $this->assertSame('Updated Refund Target', (string) $refundReceipt->refund_to);
        $this->assertSame('cash', (string) $refundReceipt->payment_method);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\QuotationService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReceiptReportBreakdownTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{quotation: Quotation, invoice: Invoice, receipt: Receipt}
     */
    private function createGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RPT-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 600,
                    'amount' => -600,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $itemIds = collect(range(1, 3))->map(function (int $index) use ($quotation): int {
            $item = QuotationItem::create([
                'quotation_id' => $quotation->id,
                'description' => 'Member #'.$index,
                'is_header' => false,
                'quantity' => 1,
                'rate' => 3000,
                'sort_order' => $index,
            ]);

            return (int) $item->id;
        });

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice Breakdown Test',
            'amount' => 8400,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync($itemIds->all());

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 8400,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        return [
            'quotation' => $quotation,
            'invoice' => $invoice,
            'receipt' => $receipt,
        ];
    }

    public function test_quotation_report_payload_includes_invoice_payment_progress_rows(): void
    {
        $graph = $this->createGraph();

        $payload = app(QuotationService::class)->getForEditShow((int) $graph['quotation']->id);

        $this->assertSame('1st Payment', (string) data_get($payload, 'invoice_payment_progress.0.label'));
        $this->assertSame(8400.0, (float) data_get($payload, 'invoice_payment_progress.0.amount_paid'));
        $this->assertSame(8400.0, (float) data_get($payload, 'invoice_payment_progress.0.total_amount'));
    }

    public function test_invoice_report_payload_includes_subtotal_extensions_and_total_consistent_with_invoice_amount(): void
    {
        $graph = $this->createGraph();

        $payload = app(InvoiceService::class)->getForEditShow((int) $graph['invoice']->id);

        $this->assertSame(9000.0, (float) ($payload['subtotal_amount'] ?? 0));
        $this->assertSame(-600.0, (float) ($payload['extension_total_amount'] ?? 0));
        $this->assertSame(8400.0, (float) ($payload['total_amount'] ?? 0));
        $this->assertCount(1, $payload['extensions'] ?? []);
        $this->assertSame(-600.0, (float) (($payload['extensions'][0]['amount'] ?? 0)));
        $this->assertSame('1st Payment', (string) data_get($payload, 'invoice_payment_progress.0.label'));
        $this->assertSame(8400.0, (float) data_get($payload, 'invoice_payment_progress.0.amount_paid'));
    }

    public function test_receipt_report_payload_includes_subtotal_extensions_and_total_consistent_with_receipt_amount(): void
    {
        $graph = $this->createGraph();

        $payload = app(ReceiptService::class)->getForEditShow((int) $graph['receipt']->id);

        $this->assertSame(9000.0, (float) ($payload['subtotal_amount'] ?? 0));
        $this->assertSame(-600.0, (float) ($payload['extension_total_amount'] ?? 0));
        $this->assertSame(8400.0, (float) ($payload['total_amount'] ?? 0));
        $this->assertCount(1, $payload['extensions'] ?? []);
        $this->assertSame(-600.0, (float) (($payload['extensions'][0]['amount'] ?? 0)));
        $this->assertSame('1st Payment', (string) data_get($payload, 'invoice_payment_progress.0.label'));
        $this->assertSame(8400.0, (float) data_get($payload, 'invoice_payment_progress.0.amount_paid'));
    }

    /**
     * @return array{quotation: Quotation, invoice: Invoice, receipt: Receipt}
     */
    private function createMixedPaymentStatusGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RPT-MIXED-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Mixed Status Member',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstPaidInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => '1st payment milestone',
            'amount' => 3100,
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Admin Fee',
                    'type' => 'surcharge',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => 100,
                    'sort_order' => 1,
                ],
            ],
            'invoice_date' => now()->subDays(3)->format('Y-m-d'),
            'due_date' => now()->subDays(1)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $firstPaidInvoice->quotationItems()->sync([(int) $item->id]);

        $secondPaidInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => '2nd payment milestone',
            'amount' => 2000,
            'invoice_date' => now()->subDays(2)->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $secondPaidInvoice->quotationItems()->sync([(int) $item->id]);

        $issuedInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Outstanding invoice',
            'amount' => 3000,
            'invoice_date' => now()->subDay()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $issuedInvoice->quotationItems()->sync([(int) $item->id]);

        $refundInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund milestone',
            'amount' => -550,
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Refund Adjustment',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => -50,
                    'amount' => -50,
                    'sort_order' => 1,
                ],
            ],
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(4)->format('Y-m-d'),
            'status' => 'refund',
        ]);
        $refundInvoice->quotationItems()->sync([(int) $item->id]);

        $receipt = Receipt::create([
            'invoice_id' => $firstPaidInvoice->id,
            'amount' => 3100,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        return [
            'quotation' => $quotation,
            'invoice' => $firstPaidInvoice,
            'receipt' => $receipt,
        ];
    }

    public function test_payment_progress_rows_use_paid_or_outstanding_values_and_exclude_refund_rows(): void
    {
        $graph = $this->createMixedPaymentStatusGraph();

        $quotationPayload = app(QuotationService::class)->getForEditShow((int) $graph['quotation']->id);
        $invoicePayload = app(InvoiceService::class)->getForEditShow((int) $graph['invoice']->id);
        $receiptPayload = app(ReceiptService::class)->getForEditShow((int) $graph['receipt']->id);

        $this->assertSame('1st Payment', (string) data_get($quotationPayload, 'invoice_payment_progress.0.label'));
        $this->assertSame('2nd Payment', (string) data_get($quotationPayload, 'invoice_payment_progress.1.label'));
        $this->assertSame('3rd Payment', (string) data_get($quotationPayload, 'invoice_payment_progress.2.label'));
        $this->assertSame(3100.0, (float) data_get($quotationPayload, 'invoice_payment_progress.0.amount_paid'));
        $this->assertSame(2000.0, (float) data_get($quotationPayload, 'invoice_payment_progress.1.amount_paid'));
        $this->assertSame(0.0, (float) data_get($quotationPayload, 'invoice_payment_progress.2.amount_paid'));
        $this->assertSame(3100.0, (float) data_get($quotationPayload, 'invoice_payment_progress.0.total_amount'));
        $this->assertSame(3000.0, (float) data_get($quotationPayload, 'invoice_payment_progress.2.total_amount'));

        $this->assertSame('1st Payment', (string) data_get($invoicePayload, 'invoice_payment_progress.0.label'));
        $this->assertSame('2nd Payment', (string) data_get($invoicePayload, 'invoice_payment_progress.1.label'));
        $this->assertSame('3rd Payment', (string) data_get($invoicePayload, 'invoice_payment_progress.2.label'));
        $this->assertSame(3100.0, (float) data_get($invoicePayload, 'invoice_payment_progress.0.amount_paid'));
        $this->assertSame(2000.0, (float) data_get($invoicePayload, 'invoice_payment_progress.1.amount_paid'));
        $this->assertSame(0.0, (float) data_get($invoicePayload, 'invoice_payment_progress.2.amount_paid'));
        $this->assertSame(2000.0, (float) data_get($invoicePayload, 'invoice_payment_progress.1.total_amount'));
        $this->assertSame(3000.0, (float) data_get($invoicePayload, 'invoice_payment_progress.2.total_amount'));

        $this->assertSame('1st Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.0.label'));
        $this->assertSame('2nd Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.1.label'));
        $this->assertSame('3rd Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.2.label'));
        $this->assertSame(3100.0, (float) data_get($receiptPayload, 'invoice_payment_progress.0.amount_paid'));
        $this->assertSame(2000.0, (float) data_get($receiptPayload, 'invoice_payment_progress.1.amount_paid'));
        $this->assertSame(0.0, (float) data_get($receiptPayload, 'invoice_payment_progress.2.amount_paid'));
        $this->assertSame(3000.0, (float) data_get($receiptPayload, 'invoice_payment_progress.2.total_amount'));
    }
}

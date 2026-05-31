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
        $this->assertSame(8400.0, (float) ($payload['invoice_total_amount'] ?? 0));
        $this->assertSame(8400.0, (float) ($payload['invoice_paid_amount'] ?? 0));
        $this->assertSame(0.0, (float) ($payload['balance_due_amount'] ?? 0));
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

    /**
     * @return array{quotation: Quotation, refund_invoice: Invoice, receipt: Receipt}
     */
    private function createRefundReceiptGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RPT-REFUND-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Refund Scenario Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $paidInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Main invoice',
            'amount' => 5000,
            'invoice_date' => now()->subDays(2)->format('Y-m-d'),
            'due_date' => now()->subDay()->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $paidInvoice->quotationItems()->sync([(int) $item->id]);

        $refundInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Trip Cancelled-Refund',
            'amount' => -4500,
            'invoice_date' => now()->subDay()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);
        $refundInvoice->quotationItems()->sync([(int) $item->id]);

        $receipt = Receipt::create([
            'invoice_id' => $refundInvoice->id,
            'amount' => -4500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'refund',
        ]);

        return [
            'quotation' => $quotation,
            'refund_invoice' => $refundInvoice,
            'receipt' => $receipt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayloadWithStalePercentageSuffix(): array
    {
        return [
            'quotation_number' => 'Q-PCT-001',
            'invoice_number' => 'INV-PCT-001',
            'receipt_number' => 'OR-PCT-001',
            'customer_name' => 'Test Customer',
            'customer_address' => 'Test Address',
            'subtotal_amount' => 1000,
            'extension_total_amount' => -100,
            'total_amount' => 900,
            'quotation_date' => now()->format('d F Y'),
            'invoice_date' => now()->format('d F Y'),
            'receipt_date' => now()->format('d F Y'),
            'payment_plan' => 'full',
            'payment_plan_label' => 'Full Payment',
            'payment_method' => 'transfer',
            'payment_method_label' => 'Transfer',
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Discount Package 1%',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => -10.0000,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
            'invoice_payment_progress' => [
                [
                    'label' => 'Pending Payment',
                    'amount_paid' => 0,
                    'total_amount' => 900,
                ],
            ],
            'notes' => [],
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConvertedQuotationPayloadWithInvoiceExtensions(): array
    {
        return [
            'quotation_number' => 'Q-CONV-001',
            'customer_name' => 'Converted Customer',
            'customer_address' => 'Converted Address',
            'subtotal_amount' => 13200,
            'extension_total_amount' => 344,
            'total_amount' => 13544,
            'quotation_date' => now()->format('d F Y'),
            'payment_plan' => 'full',
            'payment_plan_label' => 'Full Payment',
            'extensions' => [],
            'invoice_extensions' => [
                [
                    'name' => 'Invoice Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => -150,
                    'amount' => -150,
                ],
                [
                    'name' => 'Discount Services',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => -200,
                    'amount' => -200,
                ],
                [
                    'name' => 'Discount Package',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => -100,
                    'amount' => -100,
                ],
            ],
            'invoice_payment_progress' => [],
            'notes' => [],
            'items' => [
                [
                    'description' => 'Package Item',
                    'is_header' => false,
                    'quantity' => 4000,
                    'rate' => 1,
                    'taxes' => [
                        [
                            'name' => 'Discount Package',
                            'calculation_mode' => 'percentage',
                            'calculation_value' => -10,
                            'quotation_extension_master_id' => 1,
                        ],
                        [
                            'name' => 'GST',
                            'calculation_mode' => 'percentage',
                            'calculation_value' => 7,
                            'quotation_extension_master_id' => 2,
                        ],
                    ],
                ],
            ],
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
        $this->assertSame(8100.0, (float) ($invoicePayload['invoice_total_amount'] ?? 0));
        $this->assertSame(5100.0, (float) ($invoicePayload['invoice_paid_amount'] ?? 0));
        $this->assertSame(3000.0, (float) ($invoicePayload['balance_due_amount'] ?? 0));

        $this->assertSame('1st Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.0.label'));
        $this->assertSame('2nd Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.1.label'));
        $this->assertSame('3rd Payment', (string) data_get($receiptPayload, 'invoice_payment_progress.2.label'));
        $this->assertSame(3100.0, (float) data_get($receiptPayload, 'invoice_payment_progress.0.amount_paid'));
        $this->assertSame(2000.0, (float) data_get($receiptPayload, 'invoice_payment_progress.1.amount_paid'));
        $this->assertSame(0.0, (float) data_get($receiptPayload, 'invoice_payment_progress.2.amount_paid'));
        $this->assertSame(3000.0, (float) data_get($receiptPayload, 'invoice_payment_progress.2.total_amount'));
        $this->assertSame(8100.0, (float) ($receiptPayload['invoice_total_amount'] ?? 0));
        $this->assertSame(5100.0, (float) ($receiptPayload['invoice_paid_amount'] ?? 0));
        $this->assertSame(3000.0, (float) ($receiptPayload['balance_due_amount'] ?? 0));
    }

    public function test_payment_progress_orders_by_invoice_creation_sequence_not_invoice_date(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RPT-ORDER-001',
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
            'description' => 'Sequenced Member',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        // Created first (lowest id) but with the LATEST invoice_date — must still be "1st Payment".
        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync([(int) $item->id]);

        // Created second, with the EARLIEST invoice_date.
        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => '50%',
            'amount' => 2000,
            'invoice_date' => now()->subDays(5)->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync([(int) $item->id]);

        // Created third, with a middle invoice_date.
        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Balance',
            'amount' => 3000,
            'invoice_date' => now()->subDays(2)->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync([(int) $item->id]);

        $receipt = Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $payloads = [
            app(QuotationService::class)->getForEditShow((int) $quotation->id),
            app(InvoiceService::class)->getForEditShow((int) $firstInvoice->id),
            app(ReceiptService::class)->getForEditShow((int) $receipt->id),
        ];

        foreach ($payloads as $payload) {
            $this->assertSame('1st Payment', (string) data_get($payload, 'invoice_payment_progress.0.label'));
            $this->assertSame(1000.0, (float) data_get($payload, 'invoice_payment_progress.0.total_amount'));
            $this->assertSame('2nd Payment', (string) data_get($payload, 'invoice_payment_progress.1.label'));
            $this->assertSame(2000.0, (float) data_get($payload, 'invoice_payment_progress.1.total_amount'));
            $this->assertSame('3rd Payment', (string) data_get($payload, 'invoice_payment_progress.2.label'));
            $this->assertSame(3000.0, (float) data_get($payload, 'invoice_payment_progress.2.total_amount'));
        }
    }

    public function test_refund_receipt_report_payload_uses_net_order_total_for_amount_not_refunded(): void
    {
        $graph = $this->createRefundReceiptGraph();

        $payload = app(ReceiptService::class)->getForEditShow((int) $graph['receipt']->id);

        $this->assertTrue((bool) ($payload['is_refund_receipt_report'] ?? false));
        $this->assertSame(500.0, (float) ($payload['amount_not_refunded'] ?? 0));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_quotation_report_view_normalizes_percentage_labels_from_extension_values(): void
    {
        $quotationPayload = $this->buildReportPayloadWithStalePercentageSuffix();

        $quotationHtml = view('quotations.report-content', [
            'data' => $quotationPayload,
            'items' => $quotationPayload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $this->assertStringContainsString('Discount Package 10%:', $quotationHtml);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_invoice_report_view_normalizes_percentage_labels_from_extension_values(): void
    {
        $invoicePayload = $this->buildReportPayloadWithStalePercentageSuffix();

        $invoiceHtml = view('invoices.report-content', [
            'data' => $invoicePayload,
            'items' => $invoicePayload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $this->assertStringContainsString('Discount Package 10%:', $invoiceHtml);
        $this->assertStringContainsString('Balance Due:', $invoiceHtml);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_receipt_report_view_normalizes_percentage_labels_from_extension_values(): void
    {
        $receiptPayload = $this->buildReportPayloadWithStalePercentageSuffix();

        $receiptHtml = view('receipts.report-content', [
            'data' => $receiptPayload,
            'items' => $receiptPayload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $this->assertStringContainsString('Discount Package 10%:', $receiptHtml);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_receipt_report_view_shows_amount_not_refunded_only_for_refund_report(): void
    {
        $payload = $this->buildReportPayloadWithStalePercentageSuffix();
        $payload['balance_due_amount'] = 350;
        $payload['amount_not_refunded'] = 350;

        $nonRefundHtml = view('receipts.report-content', [
            'data' => [
                ...$payload,
                'is_refund_receipt_report' => false,
            ],
            'items' => $payload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $refundHtml = view('receipts.report-content', [
            'data' => [
                ...$payload,
                'is_refund_receipt_report' => true,
            ],
            'items' => $payload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $this->assertStringContainsString('Balance Due:', $nonRefundHtml);
        $this->assertStringNotContainsString('Amount Not Refunded:', $nonRefundHtml);
        $this->assertStringContainsString('Amount Not Refunded:', $refundHtml);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_converted_quotation_report_view_shows_invoice_extensions_in_subtotal_section(): void
    {
        $quotationPayload = $this->buildConvertedQuotationPayloadWithInvoiceExtensions();

        $quotationHtml = view('quotations.report-content', [
            'data' => $quotationPayload,
            'items' => $quotationPayload['items'] ?? [],
            'branding' => [],
            'is_pdf' => false,
        ])->render();

        $this->assertStringContainsString('Discount Services:', $quotationHtml);
        $this->assertStringContainsString('Discount Package 10%:', $quotationHtml);
        $this->assertStringContainsString('Discount Package:', $quotationHtml);
        $this->assertStringContainsString('GST 7%:', $quotationHtml);
    }
}

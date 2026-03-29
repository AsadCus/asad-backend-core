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
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReceiptReportBreakdownTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{invoice: Invoice, receipt: Receipt}
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
            'invoice' => $invoice,
            'receipt' => $receipt,
        ];
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
    }
}

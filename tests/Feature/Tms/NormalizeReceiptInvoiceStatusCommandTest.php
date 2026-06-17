<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use Tests\TmsTestCase as TestCase;

class NormalizeReceiptInvoiceStatusCommandTest extends TestCase
{
    /**
     * @return array{invoice: Invoice, receipt: Receipt}
     */
    private function createInvoiceWithMismatchedReceipt(): array
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-NORM-CMD-001',
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
            'description' => 'Normalization command invoice',
            'amount' => 2750,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1200,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        return [
            'invoice' => $invoice,
            'receipt' => $receipt,
        ];
    }

    public function test_normalization_command_updates_receipt_amount_and_invoice_status(): void
    {
        $graph = $this->createInvoiceWithMismatchedReceipt();

        $this->artisan('receipts:normalize-invoice-status')
            ->assertExitCode(0);

        $this->assertDatabaseHas('receipts', [
            'id' => $graph['receipt']->id,
            'amount' => '2750.00',
        ]);

        $graph['invoice']->refresh();
        $this->assertSame('paid', (string) $graph['invoice']->status);
    }

    public function test_normalization_command_dry_run_does_not_update_data(): void
    {
        $graph = $this->createInvoiceWithMismatchedReceipt();

        $this->artisan('receipts:normalize-invoice-status', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('receipts', [
            'id' => $graph['receipt']->id,
            'amount' => '1200.00',
        ]);

        $graph['invoice']->refresh();
        $this->assertSame('outstanding', (string) $graph['invoice']->status);
    }
}

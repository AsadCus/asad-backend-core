<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillRefundInvoiceNumbersCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RF-BACKFILL-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        return Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);
    }

    private function seedLegacyRefundInvoices(Order $order): void
    {
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-2026-0138',
            'description' => 'Regular invoice',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        DB::table('invoices')->insert([
            [
                'order_id' => $order->id,
                'invoice_number' => 'INV-2026-0139',
                'description' => 'Legacy refund invoice 39',
                'amount' => -50,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->format('Y-m-d'),
                'status' => 'refund',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order->id,
                'invoice_number' => 'INV-2026-0140',
                'description' => 'Legacy refund invoice 40',
                'amount' => -25,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->format('Y-m-d'),
                'status' => 'refund',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_backfill_refund_invoice_numbers_command_dry_run_does_not_update_data(): void
    {
        $order = $this->createOrder();
        $this->seedLegacyRefundInvoices($order);

        $this->artisan('invoices:backfill-refund-invoice-numbers', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'status' => 'refund',
            'invoice_number' => 'INV-2026-0139',
        ]);

        $this->assertDatabaseHas('invoices', [
            'status' => 'refund',
            'invoice_number' => 'INV-2026-0140',
        ]);
    }

    public function test_backfill_refund_invoice_numbers_command_nulls_refund_numbers_and_preserves_forward_sequence(): void
    {
        $order = $this->createOrder();
        $this->seedLegacyRefundInvoices($order);

        $this->artisan('invoices:backfill-refund-invoice-numbers')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('invoices', [
            'status' => 'refund',
            'invoice_number' => 'INV-2026-0139',
        ]);

        $this->assertDatabaseMissing('invoices', [
            'status' => 'refund',
            'invoice_number' => 'INV-2026-0140',
        ]);

        $this->assertSame(
            2,
            Invoice::query()
                ->where('status', 'refund')
                ->whereNull('invoice_number')
                ->count(),
        );

        $nextInvoiceNumber = app(NumberingService::class)->generateNextNumber('invoice');

        $this->assertStringEndsWith('0141', $nextInvoiceNumber);
    }
}

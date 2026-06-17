<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use Tests\TmsTestCase as TestCase;

class InvoiceControllerWorkflowTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function createInvoiceGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-INV-001',
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
            'description' => 'Invoice For Linkage Test',
            'amount' => 1200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        return compact('invoice', 'order', 'quotation');
    }

    public function test_invoice_show_loads_related_order_using_invoice_order_id(): void
    {
        $graph = $this->createInvoiceGraph();

        $response = $this->get(route('invoice.show', $graph['invoice']->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('invoices/view')
            ->where('data.data.id', $graph['invoice']->id)
            ->where('data.data.order_id', $graph['order']->id)
            ->where('data.order.id', $graph['order']->id)
            ->where('data.order.quotation_id', $graph['quotation']->id)
        );
    }

    public function test_invoice_edit_loads_related_order_using_invoice_order_id(): void
    {
        $graph = $this->createInvoiceGraph();

        $response = $this->get(route('invoice.edit', $graph['invoice']->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('invoices/edit')
            ->where('data.data.id', $graph['invoice']->id)
            ->where('data.data.order_id', $graph['order']->id)
            ->where('data.order.id', $graph['order']->id)
            ->where('data.order.quotation_id', $graph['quotation']->id)
        );
    }

    public function test_recreate_receipt_deletes_existing_receipt_and_redirects_back(): void
    {
        $graph = $this->createInvoiceGraph();

        $graph['invoice']->update([
            'status' => 'paid',
        ]);

        $receipt = Receipt::create([
            'invoice_id' => $graph['invoice']->id,
            'amount' => 1200,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('invoice.recreate-receipt', ['id' => $graph['invoice']->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('receipts', [
            'id' => $receipt->id,
        ]);

        $graph['invoice']->refresh();
        $this->assertNotSame('paid', (string) $graph['invoice']->status);
    }

    public function test_recreate_receipt_rejects_refund_invoice(): void
    {
        $graph = $this->createInvoiceGraph();

        $graph['invoice']->update([
            'status' => 'refund',
        ]);

        Receipt::create([
            'invoice_id' => $graph['invoice']->id,
            'amount' => -1200,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response = $this->post(route('invoice.recreate-receipt', ['id' => $graph['invoice']->id]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_invoice_create_loads_form_without_invoice_number_seed(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-INV-002',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
        ]);

        $response = $this->get(route('invoice.create', ['quotation_id' => $quotation->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('invoices/create')
            ->where('data.quotation.id', $quotation->id)
            ->missing('data.invoiceNumberSeed')
        );
    }
}

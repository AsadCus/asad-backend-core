<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentMethodMaster;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceiptControllerValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function createReceiptGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RCP-001',
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
            'description' => 'Invoice For Receipt Validation',
            'amount' => 800,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $receipt = Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        return compact('receipt');
    }

    public function test_receipt_update_requires_receipt_date_and_payment_method_fields(): void
    {
        $graph = $this->createReceiptGraph();

        $response = $this->putJson(route('receipt.update', $graph['receipt']->id), []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'receipt_date',
                'payment_method',
            ]);
    }

    public function test_receipt_create_uses_active_default_payment_method_from_master_page(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        PaymentMethodMaster::query()->create([
            'name' => 'Paynow',
            'value' => 'paynow',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('receipt.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('receipts/create')
            ->where('data.defaultPaymentMethod', 'paynow')
        );
    }

    public function test_receipt_update_preserves_invoice_and_amount_when_not_provided(): void
    {
        $graph = $this->createReceiptGraph();

        $response = $this->put(route('receipt.update', $graph['receipt']->id), [
            'receipt_date' => now()->addDay()->format('Y-m-d'),
            'payment_method' => 'transfer',
            'reference' => 'UPDATED-REF',
        ]);

        $response->assertRedirect(route('receipt.index'));

        $this->assertDatabaseHas('receipts', [
            'id' => $graph['receipt']->id,
            'invoice_id' => $graph['receipt']->invoice_id,
            'amount' => '400.00',
            'payment_method' => 'transfer',
            'reference' => 'UPDATED-REF',
        ]);
    }

    public function test_receipt_store_rejects_duplicate_invoice_receipt(): void
    {
        $graph = $this->createReceiptGraph();

        $response = $this->postJson(route('receipt.store'), [
            'invoice_id' => $graph['receipt']->invoice_id,
            'amount' => 500,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'invoice_id',
            ]);
    }

    public function test_receipt_store_allows_negative_amount(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RCP-NEG-001',
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
            'description' => 'Credit invoice',
            'amount' => -200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $response = $this->post(route('receipt.store'), [
            'invoice_id' => $invoice->id,
            'amount' => -200,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        $response->assertRedirect(route('invoice.index'));

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => '-200.00',
        ]);

        $invoice->refresh();
        $this->assertSame('refund', (string) $invoice->status);
    }
}

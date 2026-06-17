<?php

namespace Tests\Feature\Tms;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentMethodMaster;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TmsTestCase as TestCase;

class ReceiptControllerValidationTest extends TestCase
{
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

    public function test_receipt_update_normalizes_amount_to_invoice_total(): void
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
            'amount' => '800.00',
            'payment_method' => 'transfer',
            'reference' => 'UPDATED-REF',
        ]);
    }

    public function test_receipt_store_normalizes_amount_and_marks_invoice_paid(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RCP-NORM-001',
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
            'description' => 'Invoice For Receipt Normalization',
            'amount' => 1600,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $response = $this->post(route('receipt.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect(route('invoice.index'));

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => '1600.00',
        ]);

        $invoice->refresh();
        $this->assertSame('paid', (string) $invoice->status);
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
        $this->assertNull($invoice->invoice_number);
    }

    public function test_receipt_store_blocks_when_linked_package_status_is_not_open(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RCP-BLOCK-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-RCP-BLOCK-001',
            'name' => 'Full Package',
            'status' => 'full',
            'total_seats' => 5,
            'seats_left' => 0,
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $authUser->id,
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
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
            'description' => 'Invoice For Blocked Package Receipt Validation',
            'amount' => 900,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'single',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 900,
            'sort_order' => 1,
        ]);

        $invoice->quotationItems()->sync([$quotationItem->id]);

        $response = $this->postJson(route('receipt.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'invoice_id',
            ]);

        $this->assertDatabaseMissing('receipts', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_receipt_store_allows_full_package_when_linked_member_has_paid_history_status(): void
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-RCP-ALLOW-001',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-RCP-ALLOW-001',
            'name' => 'Full Package Allowed By History',
            'status' => 'full',
            'total_seats' => 5,
            'seats_left' => 0,
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $authUser->id,
            'package_id' => $package->id,
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'partially_paid',
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

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Allowed Full Package Receipt Validation',
            'amount' => 900,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Linked paid-history member item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 900,
            'sort_order' => 1,
        ]);

        $invoice->quotationItems()->sync([$quotationItem->id]);

        $response = $this->post(route('receipt.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 900,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect(route('invoice.index'));

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => '900.00',
        ]);
    }
}

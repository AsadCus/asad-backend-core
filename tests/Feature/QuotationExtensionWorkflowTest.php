<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentMethodMaster;
use App\Models\Quotation;
use App\Models\QuotationExtensionMaster;
use App\Models\Receipt;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationExtensionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_quotation_with_discount_extension_updates_total_amount(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-001',
        ]);

        $quotation = app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation with extension',
            'items' => [
                [
                    '_key' => 'item-1',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [
                [
                    'name' => 'Promo discount',
                    'type' => 'discount',
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotation->refresh();

        $this->assertSame(1000.0, (float) $quotation->item_subtotal_amount);
        $this->assertSame(-100.0, (float) $quotation->extension_total_amount);
        $this->assertSame(900.0, (float) $quotation->total_amount);
        $this->assertDatabaseHas('quotation_extensions', [
            'quotation_id' => $quotation->id,
            'name' => 'Promo discount',
            'type' => 'discount',
            'amount' => '-100.00',
        ]);
    }

    public function test_update_quotation_replaces_extensions(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-002',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Initial quotation',
        ]);

        $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 700,
            'sort_order' => 1,
        ]);

        $oldExtension = $quotation->quotationExtensions()->create([
            'name' => 'Old discount',
            'type' => 'discount',
            'amount' => -50,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->update([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Updated quotation',
            'items' => [
                [
                    'id' => $quotation->quotationItems()->first()->id,
                    '_key' => 'item-existing',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 700,
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [
                [
                    'name' => 'New discount',
                    'type' => 'discount',
                    'amount' => -120,
                    'sort_order' => 1,
                ],
            ],
        ], $quotation->id);

        $quotation->refresh();

        $this->assertSame(580.0, (float) $quotation->total_amount);
        $this->assertDatabaseMissing('quotation_extensions', [
            'id' => $oldExtension->id,
        ]);
        $this->assertDatabaseHas('quotation_extensions', [
            'quotation_id' => $quotation->id,
            'name' => 'New discount',
            'amount' => '-120.00',
        ]);
    }

    public function test_update_converted_quotation_extension_syncs_invoice_and_receipt_amounts(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-003',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Converted quotation',
        ]);

        $quotationItem = $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $quotation->quotationExtensions()->create([
            'name' => 'Initial discount',
            'type' => 'discount',
            'amount' => -100,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Full Payment',
            'amount' => 900,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 900,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        app(QuotationService::class)->update([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Converted quotation updated',
            'items' => [
                [
                    'id' => $quotationItem->id,
                    '_key' => 'item-existing',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [
                [
                    'name' => 'Updated discount',
                    'type' => 'discount',
                    'amount' => -200,
                    'sort_order' => 1,
                ],
            ],
        ], $quotation->id);

        $this->assertSame(800.0, (float) $invoice->fresh()->amount);
        $this->assertSame(800.0, (float) Receipt::query()->where('invoice_id', $invoice->id)->firstOrFail()->amount);
    }

    public function test_store_quotation_with_percentage_tax_extension_calculates_amount_from_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-004',
        ]);

        $quotation = app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation with percentage tax',
            'items' => [
                [
                    '_key' => 'item-1',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 2,
                    'rate' => 500,
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [
                [
                    'name' => 'Tax 9%',
                    'type' => 'tax',
                    'calculation_mode' => 'percentage',
                    'calculation_value' => 9,
                    'amount' => 0,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotation->refresh();

        $this->assertSame(1000.0, (float) $quotation->item_subtotal_amount);
        $this->assertSame(90.0, (float) $quotation->extension_total_amount);
        $this->assertSame(1090.0, (float) $quotation->total_amount);
        $this->assertDatabaseHas('quotation_extensions', [
            'quotation_id' => $quotation->id,
            'name' => 'Tax 9%',
            'type' => 'tax',
            'calculation_mode' => 'percentage',
            'calculation_value' => '9.0000',
            'amount' => '90.00',
        ]);
    }

    public function test_default_extensions_for_create_follow_selected_payment_method(): void
    {
        QuotationExtensionMaster::query()->create([
            'name' => 'General Discount',
            'type' => 'discount',
            'calculation_mode' => 'fixed',
            'calculation_value' => -50,
            'payment_methods' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        QuotationExtensionMaster::query()->create([
            'name' => 'Credit Card Interest',
            'type' => 'credit_card',
            'calculation_mode' => 'percentage',
            'calculation_value' => 3,
            'payment_methods' => ['credit_card'],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $creditCardDefaults = app(QuotationService::class)
            ->getDefaultExtensionsForCreate('credit_card');

        $transferDefaults = app(QuotationService::class)
            ->getDefaultExtensionsForCreate('transfer');

        $this->assertCount(2, $creditCardDefaults);
        $this->assertCount(1, $transferDefaults);
        $this->assertSame('Credit Card Interest', $creditCardDefaults[1]['name']);
    }

    public function test_store_quotation_percentage_discount_uses_subtotal_as_base(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-005',
        ]);

        $quotation = app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation with item tax and discount',
            'items' => [
                [
                    '_key' => 'item-1',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'taxes' => [
                        [
                            'quotation_extension_master_id' => null,
                            'name' => 'VAT',
                            'calculation_mode' => 'percentage',
                            'calculation_value' => 10,
                            'sort_order' => 1,
                        ],
                    ],
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [
                [
                    'name' => 'Promo 10%',
                    'type' => 'discount',
                    'calculation_mode' => 'percentage',
                    'calculation_value' => 10,
                    'amount' => 0,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $quotation->refresh();

        $this->assertSame(1000.0, (float) $quotation->item_subtotal_amount);
        $this->assertSame(0.0, (float) $quotation->extension_total_amount);
        $this->assertSame(1000.0, (float) $quotation->total_amount);
        $this->assertDatabaseHas('quotation_extensions', [
            'quotation_id' => $quotation->id,
            'name' => 'Promo 10%',
            'amount' => '-100.00',
        ]);
    }

    public function test_update_converted_quotation_item_tax_syncs_invoice_and_receipt_amounts(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-006',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Converted quotation with item tax',
        ]);

        $quotationItem = $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $quotationItem->taxes()->create([
            'quotation_extension_master_id' => null,
            'name' => 'VAT',
            'calculation_mode' => 'percentage',
            'calculation_value' => 10,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Full Payment',
            'amount' => 1100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::withoutEvents(function () use ($invoice): void {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => 1100,
                'receipt_date' => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
            ]);
        });

        app(QuotationService::class)->update([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'description' => 'Converted quotation updated',
            'items' => [
                [
                    'id' => $quotationItem->id,
                    '_key' => 'item-existing',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'taxes' => [
                        [
                            'quotation_extension_master_id' => null,
                            'name' => 'VAT',
                            'calculation_mode' => 'percentage',
                            'calculation_value' => 20,
                            'sort_order' => 1,
                        ],
                    ],
                    'sort_order' => 1,
                ],
            ],
            'extensions' => [],
        ], $quotation->id);

        $this->assertSame(1200.0, (float) $invoice->fresh()->amount);
        $this->assertSame(1200.0, (float) Receipt::query()->where('invoice_id', $invoice->id)->firstOrFail()->amount);
    }

    public function test_store_quotation_without_customer_confirmation_is_allowed(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-005',
        ]);

        $quotation = app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => null,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation without customer confirmation',
            'items' => [
                [
                    '_key' => 'item-header',
                    'description' => 'Umrah Packages',
                    'is_header' => true,
                    'quantity' => null,
                    'rate' => null,
                    'sort_order' => 1,
                ],
                [
                    '_key' => 'item-1',
                    'parent_key' => 'item-header',
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $freshQuotation = $quotation->fresh('quotationItems.parent');
        $this->assertNull($freshQuotation?->customer_confirmation_id);

        $childItem = $freshQuotation?->quotationItems
            ->firstWhere('description', 'Package cost');

        $this->assertNotNull($childItem);
        $this->assertSame('Umrah Packages', $childItem?->parent?->description);
    }

    public function test_payment_method_options_use_active_master_records_when_available(): void
    {
        PaymentMethodMaster::query()->create([
            'name' => 'Manual Cash',
            'value' => 'manual_cash',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);

        PaymentMethodMaster::query()->create([
            'name' => 'Virtual Card',
            'value' => 'virtual_card',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        PaymentMethodMaster::query()->create([
            'name' => 'Inactive Method',
            'value' => 'inactive_method',
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 3,
        ]);

        $options = app(QuotationService::class)->getPaymentMethodOptions();

        $this->assertSame('virtual_card', $options[0]['value']);
        $this->assertSame('manual_cash', $options[1]['value']);
        $this->assertCount(2, $options);
    }

    public function test_store_payment_method_masters_generates_value_from_name(): void
    {
        app(QuotationService::class)->storePaymentMethodMasters([
            [
                'name' => 'Credit Card (Terminal)',
                'value' => '',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
        ]);

        $this->assertDatabaseHas('payment_method_masters', [
            'name' => 'Credit Card (Terminal)',
            'value' => 'credit_card_terminal',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_store_payment_method_masters_normalizes_single_default_method(): void
    {
        app(QuotationService::class)->storePaymentMethodMasters([
            [
                'name' => 'Cash',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Transfer',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Card',
                'is_active' => false,
                'is_default' => true,
                'sort_order' => 3,
            ],
        ]);

        $defaultRows = PaymentMethodMaster::query()
            ->where('is_default', true)
            ->get();

        $this->assertCount(1, $defaultRows);
        $this->assertSame('cash', $defaultRows->first()?->value);
        $this->assertSame('cash', app(QuotationService::class)->getDefaultPaymentMethodValue());
    }
}

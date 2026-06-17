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
use App\Models\QuotationExtensionMaster;
use App\Models\Receipt;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Validation\ValidationException;
use Tests\TmsTestCase as TestCase;

class QuotationExtensionWorkflowTest extends TestCase
{
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
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Promo discount'
                && ($extension['type'] ?? null) === 'discount'
                && (float) ($extension['amount'] ?? 0) === -100.0;
        }));
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

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Old discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 50,
                    'amount' => -50,
                    'sort_order' => 1,
                ],
            ],
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
        $this->assertFalse(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Old discount';
        }));
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'New discount'
                && (float) ($extension['amount'] ?? 0) === -120.0;
        }));
    }

    public function test_update_quotation_merges_extensions_by_name_and_type_and_ignores_master_id(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-MERGE-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation merge test',
        ]);

        $item = $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->update([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation merge test updated',
            'items' => [
                [
                    'id' => $item->id,
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
                    'quotation_extension_master_id' => 1001,
                    'name' => 'Promo Bundle',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 25,
                    'amount' => -25,
                    'sort_order' => 1,
                ],
                [
                    'quotation_extension_master_id' => 2002,
                    'name' => 'Promo Bundle',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 15,
                    'amount' => -15,
                    'sort_order' => 2,
                ],
            ],
        ], $quotation->id);

        $quotation->refresh();
        $extensions = collect($quotation->extensions ?? [])->values();

        $this->assertCount(1, $extensions);
        $this->assertSame(null, $extensions[0]['quotation_extension_master_id'] ?? null);
        $this->assertSame('Promo Bundle', $extensions[0]['name'] ?? null);
        $this->assertSame('discount', $extensions[0]['type'] ?? null);
        $this->assertSame('fixed', $extensions[0]['calculation_mode'] ?? null);
        $this->assertSame(40.0, (float) ($extensions[0]['calculation_value'] ?? 0));
        $this->assertSame(-40.0, (float) ($extensions[0]['amount'] ?? 0));
        $this->assertSame(960.0, (float) $quotation->total_amount);
    }

    public function test_get_for_data_table_uses_order_invoice_totals_when_available(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-DATATABLE-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'accepted',
            'description' => 'Quotation datatable total test',
        ]);

        $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 700,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'status' => 'outstanding',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Cancelled invoice',
            'amount' => 500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'cancelled',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund invoice',
            'amount' => -50,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'refund',
        ]);

        $rows = app(QuotationService::class)->getForDataTable([], null);
        $row = $rows->firstWhere('id', $quotation->id);

        $this->assertNotNull($row);
        // Refund invoices are now included in total calculation: 700 + 200 - 50 = 850
        $this->assertSame(850.0, (float) ($row['total_amount'] ?? 0));
    }

    public function test_update_converted_quotation_does_not_override_invoice_owned_extensions(): void
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

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Initial discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Full Payment',
            'extensions' => [
                [
                    'name' => 'Initial discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
            ],
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

        $invoice->refresh();

        $this->assertSame(900.0, (float) $invoice->amount);
        $this->assertSame(900.0, (float) Receipt::query()->where('invoice_id', $invoice->id)->firstOrFail()->amount);
        $this->assertTrue(collect($invoice->extensions ?? [])->contains(function (array $extension): bool {
            return ($extension['name'] ?? null) === 'Initial discount'
                && (string) ($extension['type'] ?? '') === 'discount'
                && (float) ($extension['amount'] ?? 0) === -100.0;
        }));

        $quotation->refresh();
        $this->assertFalse(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Updated discount';
        }));
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
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Tax 9%'
                && ($extension['type'] ?? null) === 'tax'
                && ($extension['calculation_mode'] ?? null) === 'percentage'
                && (float) ($extension['calculation_value'] ?? 0) === 9.0
                && (float) ($extension['amount'] ?? 0) === 90.0;
        }));
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
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Promo 10%'
                && (float) ($extension['amount'] ?? 0) === -100.0;
        }));
    }

    public function test_update_quotation_with_multiple_discounts_persists_fixed_and_percentage_entries(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-007',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation for multi-discount update',
        ]);

        $quotation->quotationItems()->create([
            'description' => 'Package cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        app(QuotationService::class)->update([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation for multi-discount update',
            'items' => [
                [
                    'id' => $quotation->quotationItems()->first()->id,
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
                    'name' => 'Fixed Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 50,
                    'amount' => -50,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Percent Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'percentage',
                    'calculation_value' => 10,
                    'amount' => -100,
                    'sort_order' => 2,
                ],
            ],
        ], $quotation->id);

        $quotation->refresh();

        $this->assertSame(-150.0, (float) $quotation->extension_total_amount);
        $this->assertSame(850.0, (float) $quotation->total_amount);
        $this->assertCount(2, collect($quotation->extensions ?? [])->where('type', 'discount'));
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Fixed Discount'
                && ($extension['calculation_mode'] ?? null) === 'fixed'
                && (float) ($extension['amount'] ?? 0) === -50.0;
        }));
        $this->assertTrue(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Percent Discount'
                && ($extension['calculation_mode'] ?? null) === 'percentage'
                && (float) ($extension['amount'] ?? 0) === -100.0;
        }));
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

    public function test_store_quotation_with_customer_confirmation_requires_member_sharing_plan_when_package_exists(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'CUST-EXT-006',
        ]);

        $package = Package::create([
            'package_number' => 'PKG-EXT-PLAN-001',
            'name' => 'Quotation Sharing Plan Package',
            'status' => 'open',
            'price_single' => 1000,
        ]);

        $confirmation = CustomerConfirmation::create([
            'created_by' => $user->id,
            'package_id' => $package->id,
            'date_of_application' => now()->toDateString(),
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => null,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('missing sharing plan');

        app(QuotationService::class)->store([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'draft',
            'description' => 'Quotation with missing sharing plan',
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
                    '_key' => 'item-member',
                    'parent_key' => 'item-header',
                    'customer_confirmation_member_id' => $member->id,
                    'description' => 'Package cost',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 1000,
                    'sort_order' => 2,
                ],
            ],
        ]);
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

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\NumberingSequence;
use App\Models\NumberingSimpleCounter;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationExtensionMaster;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Rules\OrderRule;
use App\Services\OrderService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderInvoiceUpdateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function createBaseGraph(): array
    {
        $authUser = User::factory()->create();
        $this->actingAs($authUser);

        $customerUser = User::factory()->create();
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_number' => 'CUST-ORD-UPD-001',
        ]);

        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        return compact('order', 'quotation');
    }

    public function test_order_datatable_and_edit_payload_exclude_refund_invoices(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Regular Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund Invoice',
            'amount' => -200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        $datatableRow = collect($orderService->getForDataTable())
            ->firstWhere('id', $order->id);

        $this->assertNotNull($datatableRow);
        $this->assertCount(1, $datatableRow['invoices']);
        $this->assertFalse(collect($datatableRow['invoices'])->contains(fn ($invoice) => ($invoice['status'] ?? null) === 'refund'));

        $editPayload = $orderService->getForEditShow((int) $order->id);

        $this->assertCount(1, $editPayload['invoices']);
        $this->assertFalse(collect($editPayload['invoices'])->contains(fn ($invoice) => ($invoice['status'] ?? null) === 'refund'));
    }

    public function test_order_update_cannot_remove_refund_invoice_when_missing_from_payload(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $editableItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Editable Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $refundHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Refund',
            'is_header' => true,
            'sort_order' => 2,
        ]);

        $refundDetail = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $refundHeader->id,
            'description' => 'Refund - Detail',
            'is_header' => false,
            'quantity' => 1,
            'rate' => -200,
            'sort_order' => 3,
        ]);

        $editableInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Editable Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $editableInvoice->quotationItems()->sync([$editableItem->id]);

        $refundInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Refund Invoice',
            'amount' => -200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);
        $refundInvoice->quotationItems()->sync([$refundHeader->id, $refundDetail->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'full',
            'invoices' => [
                [
                    'id' => $editableInvoice->id,
                    '_key' => 'editable-invoice',
                    'description' => 'Editable Invoice Updated',
                    'amount' => 1000,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $editableItem->id,
                            '_key' => 'editable-item',
                            'description' => 'Editable Item Updated',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1000,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertDatabaseHas('invoices', [
            'id' => $refundInvoice->id,
            'order_id' => $order->id,
            'status' => 'refund',
            'amount' => '-200.00',
        ]);
    }

    public function test_order_rule_allows_refund_status_for_nested_invoice_rows(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $lineItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Refund Line',
            'is_header' => false,
            'quantity' => 1,
            'rate' => -200,
            'sort_order' => 1,
        ]);

        $payload = [
            'order_number' => 'ORD-REF-001',
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    '_key' => 'invoice-refund-1',
                    'description' => 'Refund Invoice',
                    'payment_method' => 'refund',
                    'amount' => -200,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->format('Y-m-d'),
                    'status' => 'refund',
                    'items' => [
                        [
                            'id' => $lineItem->id,
                            '_key' => 'item-refund-1',
                            'description' => 'Refund Line',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => -200,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $validator = Validator::make($payload, (new OrderRule)->rules($order->id));

        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray()));
    }

    public function test_order_update_does_not_auto_create_extension_master_when_extension_is_edited(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        QuotationExtensionMaster::query()->create([
            'name' => 'Existing Master',
            'type' => 'discount',
            'calculation_mode' => 'fixed',
            'calculation_value' => 50,
            'payment_methods' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $existingMasterCount = QuotationExtensionMaster::query()->count();

        $item = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Main Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Editable Invoice',
            'payment_method' => 'credit_card',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
            'extensions' => [],
        ]);
        $invoice->quotationItems()->sync([$item->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        $orderService->update([
            'payment_plan' => 'full',
            'invoices' => [
                [
                    'id' => $invoice->id,
                    '_key' => 'invoice-1',
                    'description' => 'Editable Invoice',
                    'payment_method' => 'credit_card',
                    'amount' => 1000,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'issued',
                    'extensions' => [
                        [
                            'quotation_extension_master_id' => null,
                            'name' => 'Manual Edited Discount',
                            'type' => 'discount',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => 100,
                            'amount' => -100,
                            'sort_order' => 1,
                        ],
                    ],
                    'items' => [
                        [
                            'id' => $item->id,
                            '_key' => 'item-1',
                            'description' => 'Main Item',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1000,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertSame($existingMasterCount, QuotationExtensionMaster::query()->count());

        $updatedExtensions = (array) ($invoice->fresh()->extensions ?? []);
        $this->assertNotEmpty($updatedExtensions);
        $this->assertSame(null, $updatedExtensions[0]['quotation_extension_master_id'] ?? null);
        $this->assertSame('Manual Edited Discount', $updatedExtensions[0]['name'] ?? null);
    }

    public function test_order_update_maps_duplicate_invoice_number_error_to_row_field(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $existingItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Existing Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $existingInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-OWN-001',
            'description' => 'Existing Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $existingInvoice->quotationItems()->sync([$existingItem->id]);

        $otherCustomerUser = User::factory()->create();
        $otherCustomer = Customer::create([
            'user_id' => $otherCustomerUser->id,
            'customer_number' => 'CUST-ORD-UPD-OTHER-001',
        ]);

        $otherQuotation = Quotation::create([
            'customer_id' => $otherCustomer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'payment_method' => 'transfer',
            'status' => 'converted',
        ]);

        $otherOrder = Order::create([
            'quotation_id' => $otherQuotation->id,
            'payment_plan' => 'full',
        ]);

        Invoice::create([
            'order_id' => $otherOrder->id,
            'invoice_number' => 'INV-DUP-001',
            'description' => 'External Invoice',
            'amount' => 700,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $newItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'New Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 2,
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        try {
            $orderService->update([
                'payment_plan' => 'full',
                'invoices' => [
                    [
                        'id' => $existingInvoice->id,
                        '_key' => 'new-invoice',
                        'invoice_number' => 'INV-DUP-001',
                        'description' => 'New Invoice',
                        'payment_method' => 'transfer',
                        'amount' => 500,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(7)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $newItem->id,
                                '_key' => 'new-item',
                                'description' => 'New Item',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 500,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected duplicate invoice number validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoices.0.invoice_number', $exception->errors());
            $this->assertSame(
                'The number has already been used.',
                (string) ($exception->errors()['invoices.0.invoice_number'][0] ?? ''),
            );
        }
    }

    public function test_order_update_rejects_refund_row_without_id_when_refund_invoice_cannot_be_resolved(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $editableItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Editable Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $refundHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Refund',
            'is_header' => true,
            'sort_order' => 2,
        ]);

        $refundDetail = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $refundHeader->id,
            'description' => 'Refund - Detail',
            'is_header' => false,
            'quantity' => 1,
            'rate' => -200,
            'sort_order' => 3,
        ]);

        $editableInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-EDIT-001',
            'description' => 'Editable Invoice',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $editableInvoice->quotationItems()->sync([$editableItem->id]);

        $refundInvoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-REFUND-001',
            'description' => 'Refund Invoice',
            'amount' => -200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => 'refund',
        ]);
        $refundInvoice->quotationItems()->sync([$refundHeader->id, $refundDetail->id]);
        $refundInvoice->refresh();

        $this->assertNull($refundInvoice->invoice_number);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        try {
            $orderService->update([
                'payment_plan' => 'full',
                'invoices' => [
                    [
                        'id' => $editableInvoice->id,
                        '_key' => 'editable-row',
                        'invoice_number' => 'INV-EDIT-001',
                        'description' => 'Editable Invoice Updated',
                        'payment_method' => 'transfer',
                        'amount' => 1000,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(7)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $editableItem->id,
                                '_key' => 'editable-item',
                                'description' => 'Editable Item Updated',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 1000,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                    [
                        '_key' => 'refund-row-no-id',
                        'invoice_number' => 'INV-REFUND-001',
                        'description' => 'Refund Invoice',
                        'payment_method' => 'refund',
                        'amount' => -200,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->format('Y-m-d'),
                        'status' => 'refund',
                        'is_refund' => true,
                        'items' => [
                            [
                                'id' => $refundHeader->id,
                                '_key' => 'refund-header',
                                'description' => 'Refund',
                                'is_header' => true,
                                'quantity' => null,
                                'rate' => null,
                                'sort_order' => 2,
                            ],
                            [
                                'id' => $refundDetail->id,
                                '_key' => 'refund-detail',
                                'description' => 'Refund - Detail',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => -200,
                                'sort_order' => 3,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected refund row validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoices.1.invoice_number', $exception->errors());
            $this->assertSame(
                'Refund invoice row is invalid. Please refresh and try again.',
                (string) ($exception->errors()['invoices.1.invoice_number'][0] ?? ''),
            );
        }
    }

    public function test_order_update_keeps_invoice_identity_and_syncs_receipt_amount_for_paid_invoice(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Package Cost',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 10,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Deposit',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'full',
            'invoices' => [
                [
                    'id' => $invoice->id,
                    '_key' => 'inv-1',
                    'description' => 'Invoice For Full Payment',
                    'amount' => 1500,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'paid',
                    'items' => [
                        [
                            'id' => $quotationItem->id,
                            '_key' => 'item-1',
                            'description' => 'Package Cost Updated',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1500,
                            'sort_order' => 999,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertSame(1, Invoice::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => '1500.00',
        ]);

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => '1500.00',
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'id' => $quotationItem->id,
            'quotation_id' => $quotation->id,
            'sort_order' => 1001,
        ]);
    }

    public function test_order_update_rolls_back_latest_invoice_sequence_when_trailing_invoice_removed(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'full',
            'invoices' => [
                [
                    'id' => $invoiceOne->id,
                    '_key' => 'inv-one',
                    'description' => 'Invoice 1 Updated',
                    'amount' => 300,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(5)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $itemOne->id,
                            '_key' => 'item-one',
                            'description' => 'Line 1 Updated',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 300,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertDatabaseHas('invoices', ['id' => $invoiceOne->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoiceTwo->id]);

        $year = now()->format('Y');
        $this->assertDatabaseHas('numbering_sequences', [
            'model_key' => 'invoice',
            'sequence_year' => $year,
        ]);

        $sequence = NumberingSequence::query()
            ->where('model_key', 'invoice')
            ->where('sequence_year', $year)
            ->first();

        $this->assertNotNull($sequence);
        $this->assertGreaterThanOrEqual(0, (int) $sequence->current_number);

        $simpleCounter = NumberingSimpleCounter::query()
            ->where('model_key', 'invoice')
            ->first();

        if ((int) $sequence->current_number === 0) {
            $this->assertNotNull($simpleCounter);
            $this->assertNotNull($simpleCounter?->latest_number);
        }
    }

    public function test_order_update_rejects_removing_invoice_when_receipt_exists(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        $receipt = Receipt::create([
            'invoice_id' => $invoiceTwo->id,
            'amount' => 200,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        try {
            $orderService->update([
                'payment_plan' => 'full',
                'invoices' => [
                    [
                        'id' => $invoiceOne->id,
                        '_key' => 'inv-one',
                        'description' => 'Invoice 1 Updated',
                        'amount' => 300,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(5)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $itemOne->id,
                                '_key' => 'item-one',
                                'description' => 'Line 1 Updated',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 300,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected validation exception for removing invoice with receipt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoices', $exception->errors());
        }

        $this->assertDatabaseHas('invoices', ['id' => $invoiceTwo->id]);
        $this->assertDatabaseHas('receipts', ['id' => $receipt->id]);
    }

    public function test_order_update_rejects_removing_invoice_when_invoice_is_paid(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        try {
            $orderService->update([
                'payment_plan' => 'full',
                'invoices' => [
                    [
                        'id' => $invoiceOne->id,
                        '_key' => 'inv-one',
                        'description' => 'Invoice 1 Updated',
                        'amount' => 300,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(5)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $itemOne->id,
                                '_key' => 'item-one',
                                'description' => 'Line 1 Updated',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 300,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected validation exception for removing paid invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoices', $exception->errors());
            $this->assertStringContainsString('Cannot remove paid invoice', (string) $exception->errors()['invoices'][0]);
        }

        $this->assertDatabaseHas('invoices', ['id' => $invoiceTwo->id]);
    }

    public function test_order_update_rejects_switching_installment_to_full_when_more_than_one_invoice_is_paid(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $itemThree = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 3',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 300,
            'sort_order' => 3,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        $invoiceThree = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 3',
            'amount' => 300,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceThree->quotationItems()->sync([$itemThree->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        try {
            $orderService->update([
                'payment_plan' => 'full',
                'invoices' => [
                    [
                        'id' => $invoiceOne->id,
                        '_key' => 'inv-one',
                        'description' => 'Invoice 1',
                        'amount' => 100,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'paid',
                        'items' => [
                            [
                                'id' => $itemOne->id,
                                '_key' => 'item-one',
                                'description' => 'Installment 1',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 100,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                    [
                        'id' => $invoiceTwo->id,
                        '_key' => 'inv-two',
                        'description' => 'Invoice 2',
                        'amount' => 200,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'paid',
                        'items' => [
                            [
                                'id' => $itemTwo->id,
                                '_key' => 'item-two',
                                'description' => 'Installment 2',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 200,
                                'sort_order' => 2,
                            ],
                        ],
                    ],
                    [
                        'id' => $invoiceThree->id,
                        '_key' => 'inv-three',
                        'description' => 'Invoice 3',
                        'amount' => 300,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $itemThree->id,
                                '_key' => 'item-three',
                                'description' => 'Installment 3',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 300,
                                'sort_order' => 3,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected validation exception for switching installment to full with multiple paid invoices.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_plan', $exception->errors());
            $this->assertStringContainsString('Cannot change payment plan from installment', (string) $exception->errors()['payment_plan'][0]);
        }

        $order->refresh();
        $this->assertSame('installment', $order->payment_plan);
    }

    public function test_order_update_rejects_switching_installment_to_direct_when_more_than_one_invoice_is_paid(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 200,
            'sort_order' => 2,
        ]);

        $itemThree = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Installment 3',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 300,
            'sort_order' => 3,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        $invoiceThree = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 3',
            'amount' => 300,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceThree->quotationItems()->sync([$itemThree->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        try {
            $orderService->update([
                'payment_plan' => 'direct',
                'invoices' => [
                    [
                        'id' => $invoiceOne->id,
                        '_key' => 'inv-one',
                        'description' => 'Invoice 1',
                        'amount' => 100,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'paid',
                        'items' => [
                            [
                                'id' => $itemOne->id,
                                '_key' => 'item-one',
                                'description' => 'Installment 1',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 100,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                    [
                        'id' => $invoiceTwo->id,
                        '_key' => 'inv-two',
                        'description' => 'Invoice 2',
                        'amount' => 200,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'paid',
                        'items' => [
                            [
                                'id' => $itemTwo->id,
                                '_key' => 'item-two',
                                'description' => 'Installment 2',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 200,
                                'sort_order' => 2,
                            ],
                        ],
                    ],
                    [
                        'id' => $invoiceThree->id,
                        '_key' => 'inv-three',
                        'description' => 'Invoice 3',
                        'amount' => 300,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->addDays(3)->format('Y-m-d'),
                        'status' => 'issued',
                        'items' => [
                            [
                                'id' => $itemThree->id,
                                '_key' => 'item-three',
                                'description' => 'Installment 3',
                                'is_header' => false,
                                'quantity' => 1,
                                'rate' => 300,
                                'sort_order' => 3,
                            ],
                        ],
                    ],
                ],
            ], $order->id);

            $this->fail('Expected validation exception for switching installment to direct with multiple paid invoices.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_plan', $exception->errors());
            $this->assertStringContainsString('Cannot change payment plan from installment', (string) $exception->errors()['payment_plan'][0]);
        }

        $order->refresh();
        $this->assertSame('installment', $order->payment_plan);
    }

    public function test_quotation_update_assigns_new_items_to_invoice_by_sort_order_anchor(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $existingQuotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Existing Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $olderInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Older Invoice',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $olderInvoice->quotationItems()->sync([$existingQuotationItem->id]);

        $latestInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Latest Invoice',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        /** @var QuotationService $quotationService */
        $quotationService = app(QuotationService::class);
        $quotationService->update([
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'items' => [
                [
                    'id' => $existingQuotationItem->id,
                    '_key' => 'existing-item',
                    'description' => 'Existing Item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 100,
                    'sort_order' => 1,
                ],
                [
                    '_key' => 'new-item',
                    'description' => 'New Added Item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 300,
                    'sort_order' => 2,
                ],
            ],
        ], $quotation->id);

        $newQuotationItem = QuotationItem::query()
            ->where('quotation_id', $quotation->id)
            ->where('description', 'New Added Item')
            ->first();

        $this->assertNotNull($newQuotationItem);
        $this->assertDatabaseHas('quotation_items', [
            'id' => $newQuotationItem->id,
            'sort_order' => 1002,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $olderInvoice->id,
            'quotation_item_id' => $newQuotationItem->id,
        ]);

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $latestInvoice->id,
            'quotation_item_id' => $newQuotationItem->id,
        ]);
    }

    public function test_quotation_update_assigns_new_child_item_to_same_invoice_as_parent(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $existingParentItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Existing Parent Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 120,
            'sort_order' => 1,
        ]);

        $olderInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Older Invoice',
            'amount' => 120,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $olderInvoice->quotationItems()->sync([$existingParentItem->id]);

        $latestInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Latest Invoice',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        /** @var QuotationService $quotationService */
        $quotationService = app(QuotationService::class);
        $quotationService->update([
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
            'items' => [
                [
                    'id' => $existingParentItem->id,
                    '_key' => 'existing-parent',
                    'description' => 'Existing Parent Item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 120,
                    'sort_order' => 1,
                ],
                [
                    '_key' => 'new-child',
                    'parent_id' => $existingParentItem->id,
                    'parent_key' => 'existing-parent',
                    'description' => 'New Child Item',
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => 80,
                    'sort_order' => 2,
                ],
            ],
        ], $quotation->id);

        $newChildItem = QuotationItem::query()
            ->where('quotation_id', $quotation->id)
            ->where('description', 'New Child Item')
            ->first();

        $this->assertNotNull($newChildItem);
        $this->assertDatabaseHas('quotation_items', [
            'id' => $newChildItem->id,
            'sort_order' => 1002,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $olderInvoice->id,
            'quotation_item_id' => $newChildItem->id,
        ]);

        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $latestInvoice->id,
            'quotation_item_id' => $newChildItem->id,
        ]);
    }

    public function test_order_update_without_invoice_ids_keeps_existing_invoices_when_count_is_same(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $quotationItems = collect([
            ['description' => 'Line 1', 'sort_order' => 1, 'rate' => 100],
            ['description' => 'Line 2', 'sort_order' => 2, 'rate' => 200],
            ['description' => 'Line 3', 'sort_order' => 3, 'rate' => 300],
        ])->map(function (array $item) use ($quotation): QuotationItem {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'description' => $item['description'],
                'is_header' => false,
                'quantity' => 1,
                'rate' => $item['rate'],
                'sort_order' => $item['sort_order'],
            ]);
        })->values();

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$quotationItems[0]->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 200,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(4)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync([$quotationItems[1]->id]);

        $invoiceThree = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 3',
            'amount' => 300,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceThree->quotationItems()->sync([$quotationItems[2]->id]);

        $invoiceIds = [$invoiceOne->id, $invoiceTwo->id, $invoiceThree->id];

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Simulate frontend payload that accidentally misses invoice IDs.
        $payload = [
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    '_key' => 'inv-1',
                    'description' => 'Invoice 1 Updated',
                    'amount' => 111,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(3)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $quotationItems[0]->id,
                            '_key' => 'item-1',
                            'description' => 'Line 1',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 111,
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    '_key' => 'inv-2',
                    'description' => 'Invoice 2 Updated',
                    'amount' => 222,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(4)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $quotationItems[1]->id,
                            '_key' => 'item-2',
                            'description' => 'Line 2',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 222,
                            'sort_order' => 2,
                        ],
                    ],
                ],
                [
                    '_key' => 'inv-3',
                    'description' => 'Invoice 3 Updated',
                    'amount' => 333,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(5)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $quotationItems[2]->id,
                            '_key' => 'item-3',
                            'description' => 'Line 3',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 333,
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],
        ];

        $orderService->update($payload, $order->id);
        $orderService->update($payload, $order->id);

        $this->assertSame(3, Invoice::query()->where('order_id', $order->id)->count());

        foreach ($invoiceIds as $invoiceId) {
            $this->assertDatabaseHas('invoices', [
                'id' => $invoiceId,
                'order_id' => $order->id,
            ]);
        }
    }

    public function test_order_update_with_missing_item_ids_does_not_duplicate_quotation_items(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $quotationItems = collect([
            ['description' => 'Package Item (Deposit)', 'sort_order' => 1001, 'rate' => 1000],
            ['description' => 'Package Item (50%)', 'sort_order' => 2001, 'rate' => 1500],
            ['description' => 'Package Item (Balance)', 'sort_order' => 3001, 'rate' => 2500],
        ])->map(function (array $item) use ($quotation): QuotationItem {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'description' => $item['description'],
                'is_header' => false,
                'quantity' => 1,
                'rate' => $item['rate'],
                'sort_order' => $item['sort_order'],
            ]);
        })->values();

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice Deposit',
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$quotationItems[0]->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 50%',
            'amount' => 1500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(4)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync([$quotationItems[1]->id]);

        $invoiceThree = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice Balance',
            'amount' => 2500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceThree->quotationItems()->sync([$quotationItems[2]->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        $payload = [
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    'id' => $invoiceOne->id,
                    '_key' => 'inv-deposit',
                    'description' => 'Invoice Deposit',
                    'amount' => 1000,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(3)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'item-deposit',
                            'description' => 'Package Item (Deposit)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1000,
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceTwo->id,
                    '_key' => 'inv-fifty',
                    'description' => 'Invoice 50%',
                    'amount' => 1500,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(4)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'item-fifty',
                            'description' => 'Package Item (50%)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1500,
                            'sort_order' => 2,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceThree->id,
                    '_key' => 'inv-balance',
                    'description' => 'Invoice Balance',
                    'amount' => 2500,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(5)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'item-balance',
                            'description' => 'Package Item (Balance)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 2500,
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],
        ];

        $orderService->update($payload, $order->id);
        $orderService->update($payload, $order->id);

        $this->assertSame(
            3,
            QuotationItem::query()->where('quotation_id', $quotation->id)->count()
        );

        $this->assertSame(
            3,
            \DB::table('invoice_items')
                ->whereIn('invoice_id', [$invoiceOne->id, $invoiceTwo->id, $invoiceThree->id])
                ->count()
        );
    }

    public function test_order_update_syncs_parent_child_and_between_invoice_sort_order(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $parentItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Parent Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 1,
        ]);

        $otherItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Other Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 300,
            'sort_order' => 3,
        ]);

        $childItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $parentItem->id,
            'description' => 'Existing Child Item',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 100,
            'sort_order' => 2,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 100,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$parentItem->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 300,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        // Start from incorrect linkage to verify order update can rebalance it.
        $invoiceTwo->quotationItems()->sync([$otherItem->id, $childItem->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    'id' => $invoiceOne->id,
                    '_key' => 'inv-one',
                    'description' => 'Invoice 1',
                    'amount' => 100,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(3)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $parentItem->id,
                            '_key' => 'parent-item',
                            'description' => 'Parent Item',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 100,
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceTwo->id,
                    '_key' => 'inv-two',
                    'description' => 'Invoice 2',
                    'amount' => 300,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(5)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            'id' => $otherItem->id,
                            '_key' => 'other-item',
                            'description' => 'Other Item',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 300,
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertDatabaseHas('quotation_items', [
            'id' => $childItem->id,
            'parent_id' => $parentItem->id,
            'sort_order' => 1002,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceOne->id,
            'quotation_item_id' => $parentItem->id,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceOne->id,
            'quotation_item_id' => $childItem->id,
        ]);
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoiceTwo->id,
            'quotation_item_id' => $childItem->id,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceTwo->id,
            'quotation_item_id' => $otherItem->id,
        ]);
    }

    public function test_quotation_update_sync_amount_uses_item_taxes_and_invoice_extensions_only(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Package - Member 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 1000,
            'sort_order' => 1,
        ]);

        $quotationItem->taxes()->create([
            'name' => 'Item Tax',
            'calculation_mode' => 'percentage',
            'calculation_value' => 10,
            'sort_order' => 1,
        ]);

        $quotation->update([
            'extensions' => [
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Credit Card Surcharge',
                    'type' => 'credit_card',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 50,
                    'amount' => 50,
                    'sort_order' => 2,
                ],
                [
                    'id' => null,
                    'quotation_extension_master_id' => null,
                    'name' => 'Legacy Tax Extension',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 80,
                    'amount' => 80,
                    'sort_order' => 3,
                ],
            ],
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Deposit',
            'extensions' => [
                [
                    'name' => 'Group Discount',
                    'type' => 'discount',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 100,
                    'amount' => -100,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Credit Card Surcharge',
                    'type' => 'credit_card',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 50,
                    'amount' => 50,
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Legacy Tax Extension',
                    'type' => 'tax',
                    'calculation_mode' => 'fixed',
                    'calculation_value' => 80,
                    'amount' => 80,
                    'sort_order' => 3,
                ],
            ],
            'amount' => 1000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'paid',
        ]);
        $invoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->format('Y-m-d'),
            'payment_method' => 'transfer',
        ]);

        /** @var QuotationService $quotationService */
        $quotationService = app(QuotationService::class);
        $quotationService->update([
            'customer_id' => $quotation->customer_id,
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'installment',
            'payment_method' => 'transfer',
            'status' => 'converted',
        ], $quotation->id);

        $expectedAmount = 1050.00;

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount' => number_format($expectedAmount, 2, '.', ''),
        ]);

        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => number_format($expectedAmount, 2, '.', ''),
        ]);
    }

    public function test_order_update_keeps_invoice_extensions_distinct_from_quotation_extensions(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $itemOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 1,
        ]);

        $itemTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Line 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 500,
            'sort_order' => 2,
        ]);

        $invoiceOne = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 1',
            'amount' => 500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceOne->quotationItems()->sync([$itemOne->id]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice 2',
            'amount' => 500,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $invoiceTwo->quotationItems()->sync([$itemTwo->id]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    'id' => $invoiceOne->id,
                    '_key' => 'inv-1',
                    'description' => 'Invoice 1',
                    'amount' => 475,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'issued',
                    'extensions' => [
                        [
                            'name' => 'Group Discount',
                            'type' => 'discount',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => -50,
                            'amount' => -50,
                            'sort_order' => 1,
                        ],
                        [
                            'name' => 'Credit Card Surcharge',
                            'type' => 'credit_card',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => 25,
                            'amount' => 25,
                            'sort_order' => 2,
                        ],
                        [
                            'name' => 'Tax Extension',
                            'type' => 'tax',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => 10,
                            'amount' => 10,
                            'sort_order' => 3,
                        ],
                    ],
                    'items' => [
                        [
                            'id' => $itemOne->id,
                            '_key' => 'item-1',
                            'description' => 'Line 1',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 500,
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceTwo->id,
                    '_key' => 'inv-2',
                    'description' => 'Invoice 2',
                    'amount' => 475,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'issued',
                    'extensions' => [
                        [
                            'name' => 'Group Discount',
                            'type' => 'discount',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => -50,
                            'amount' => -50,
                            'sort_order' => 1,
                        ],
                        [
                            'name' => 'Credit Card Surcharge',
                            'type' => 'credit_card',
                            'calculation_mode' => 'fixed',
                            'calculation_value' => 25,
                            'amount' => 25,
                            'sort_order' => 2,
                        ],
                    ],
                    'items' => [
                        [
                            'id' => $itemTwo->id,
                            '_key' => 'item-2',
                            'description' => 'Line 2',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 500,
                            'sort_order' => 2,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $invoiceOne->refresh();
        $invoiceTwo->refresh();

        $this->assertTrue(collect($invoiceOne->extensions ?? [])->contains(function (array $extension): bool {
            return ($extension['name'] ?? null) === 'Group Discount'
                && ($extension['type'] ?? null) === 'discount'
                && (float) ($extension['amount'] ?? 0) === -50.0;
        }));

        $this->assertTrue(collect($invoiceTwo->extensions ?? [])->contains(function (array $extension): bool {
            return ($extension['name'] ?? null) === 'Group Discount'
                && ($extension['type'] ?? null) === 'discount'
                && (float) ($extension['amount'] ?? 0) === -50.0;
        }));

        $quotation->refresh();

        $this->assertFalse(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Group Discount';
        }));

        $this->assertFalse(collect($quotation->extensions ?? [])->contains(function ($extension): bool {
            return is_array($extension)
                && ($extension['name'] ?? null) === 'Credit Card Surcharge';
        }));
    }

    public function test_order_update_full_to_installment_persists_header_and_member_children_per_invoice(): void
    {
        $graph = $this->createBaseGraph();
        $order = $graph['order'];
        $quotation = $graph['quotation'];

        $confirmation = CustomerConfirmation::create([
            'date_of_application' => now()->format('Y-m-d'),
        ]);

        $memberOne = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $quotation->customer_id,
            'is_leader' => true,
            'status' => 'pending_payment',
            'sharing_plan' => 'double',
        ]);

        $memberTwo = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $quotation->customer_id,
            'is_leader' => false,
            'status' => 'pending_payment',
            'sharing_plan' => 'double',
        ]);

        $quotation->update([
            'customer_confirmation_id' => $confirmation->id,
            'payment_plan' => 'full',
        ]);

        $baseHeader = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'quantity' => null,
            'rate' => null,
            'sort_order' => 1,
        ]);

        $baseMemberOne = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $baseHeader->id,
            'customer_confirmation_member_id' => $memberOne->id,
            'description' => 'Package Sharing Cost - Member 1',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2500,
            'sort_order' => 2,
        ]);

        $baseMemberTwo = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => $baseHeader->id,
            'customer_confirmation_member_id' => $memberTwo->id,
            'description' => 'Package Sharing Cost - Member 2',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 2500,
            'sort_order' => 3,
        ]);

        $fullInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Full Payment',
            'amount' => 5000,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'issued',
        ]);
        $fullInvoice->quotationItems()->sync([
            $baseHeader->id,
            $baseMemberOne->id,
            $baseMemberTwo->id,
        ]);

        $invoiceTwo = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For 50%',
            'amount' => 0,
            'invoice_date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(8)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        $invoiceThree = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Invoice For Balance',
            'amount' => 0,
            'invoice_date' => now()->addDays(2)->format('Y-m-d'),
            'due_date' => now()->addDays(9)->format('Y-m-d'),
            'status' => 'issued',
        ]);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->update([
            'payment_plan' => 'installment',
            'invoices' => [
                [
                    'id' => $fullInvoice->id,
                    '_key' => 'inv-deposit',
                    'description' => 'Invoice For Deposit',
                    'amount' => 500,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'header-deposit',
                            'id' => $baseHeader->id,
                            'description' => 'Umrah Packages',
                            'is_header' => true,
                            'sort_order' => 1,
                        ],
                        [
                            '_key' => 'member-1-deposit',
                            'parent_key' => 'header-deposit',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberOne->id,
                            'description' => 'Package Sharing Cost - Member 1 (Deposit)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 250,
                            'sort_order' => 2,
                        ],
                        [
                            '_key' => 'member-2-deposit',
                            'parent_key' => 'header-deposit',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberTwo->id,
                            'description' => 'Package Sharing Cost - Member 2 (Deposit)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 250,
                            'sort_order' => 3,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceTwo->id,
                    '_key' => 'inv-fifty',
                    'description' => 'Invoice For 50%',
                    'amount' => 2500,
                    'invoice_date' => now()->addDay()->format('Y-m-d'),
                    'due_date' => now()->addDays(8)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'header-fifty',
                            'id' => $baseHeader->id,
                            'description' => 'Umrah Packages',
                            'is_header' => true,
                            'sort_order' => 1,
                        ],
                        [
                            '_key' => 'member-1-fifty',
                            'parent_key' => 'header-fifty',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberOne->id,
                            'description' => 'Package Sharing Cost - Member 1 (50%)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1250,
                            'sort_order' => 2,
                        ],
                        [
                            '_key' => 'member-2-fifty',
                            'parent_key' => 'header-fifty',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberTwo->id,
                            'description' => 'Package Sharing Cost - Member 2 (50%)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1250,
                            'sort_order' => 3,
                        ],
                    ],
                ],
                [
                    'id' => $invoiceThree->id,
                    '_key' => 'inv-balance',
                    'description' => 'Invoice For Balance',
                    'amount' => 2000,
                    'invoice_date' => now()->addDays(2)->format('Y-m-d'),
                    'due_date' => now()->addDays(9)->format('Y-m-d'),
                    'status' => 'issued',
                    'items' => [
                        [
                            '_key' => 'header-balance',
                            'id' => $baseHeader->id,
                            'description' => 'Umrah Packages',
                            'is_header' => true,
                            'sort_order' => 1,
                        ],
                        [
                            '_key' => 'member-1-balance',
                            'parent_key' => 'header-balance',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberOne->id,
                            'description' => 'Package Sharing Cost - Member 1 (Balance)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1000,
                            'sort_order' => 2,
                        ],
                        [
                            '_key' => 'member-2-balance',
                            'parent_key' => 'header-balance',
                            'parent_id' => $baseHeader->id,
                            'customer_confirmation_member_id' => $memberTwo->id,
                            'description' => 'Package Sharing Cost - Member 2 (Balance)',
                            'is_header' => false,
                            'quantity' => 1,
                            'rate' => 1000,
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],
        ], $order->id);

        $this->assertSame(
            9,
            QuotationItem::query()->where('quotation_id', $quotation->id)->count(),
        );

        $this->assertSame(
            3,
            QuotationItem::query()
                ->where('quotation_id', $quotation->id)
                ->where('description', 'Umrah Packages')
                ->where('is_header', true)
                ->count(),
        );

        $this->assertDatabaseMissing('quotation_items', [
            'quotation_id' => $quotation->id,
            'description' => 'Package Sharing Cost - Member 1',
        ]);

        $this->assertDatabaseMissing('quotation_items', [
            'quotation_id' => $quotation->id,
            'description' => 'Package Sharing Cost - Member 2',
        ]);

        $this->assertSame(
            9,
            \DB::table('invoice_items')
                ->whereIn('invoice_id', [$fullInvoice->id, $invoiceTwo->id, $invoiceThree->id])
                ->count(),
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\OrderService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertDatabaseHas('number_sequences', [
            'type' => 'invoice',
            'year' => $year,
            'current_number' => 1,
        ]);

        $sequence = NumberSequence::query()
            ->where('type', 'invoice')
            ->where('year', $year)
            ->first();

        $this->assertNotNull($sequence);
        $this->assertSame(1, (int) $sequence->current_number);
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
        $invoiceNumbers = [$invoiceOne->invoice_number, $invoiceTwo->invoice_number, $invoiceThree->invoice_number];

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

        foreach ($invoiceNumbers as $invoiceNumber) {
            $this->assertDatabaseHas('invoices', [
                'invoice_number' => $invoiceNumber,
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
}

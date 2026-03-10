<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Helpers\NumberGenerator;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected $invoiceService;

    protected $formatService;

    protected $quotationItemService;

    protected $quotationService;

    public function __construct(InvoiceService $invoiceService, FormatService $formatService, QuotationItemService $quotationItemService, QuotationService $quotationService)
    {
        $this->invoiceService = $invoiceService;
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
        $this->quotationService = $quotationService;
    }

    public function get()
    {
        return Order::with('quotation')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Order::with(['quotation.customer.user', 'quotation.customer.handledBy', 'invoices.receipt'])
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('quotation.customer', function ($cq) use ($value) {
                    $cq->where('handled_by', $value);
                });
            })->orderBy('order_number', 'desc')->get()->map(function ($o) {
                $hasReceipts = $o->invoices->some(function ($invoice) {
                    return $invoice->receipt->isNotEmpty();
                });

                return [
                    'id' => $o->id,
                    'order_number' => $o->order_number ?? '-',
                    'quotation_id' => $o->quotation_id ?? '-',
                    'quotation_number' => $o->quotation->quotation_number ?? '-',
                    'customer_id' => $o->quotation->customer->id ?? '-',
                    'customer_number' => $o->quotation->customer->customer_number ?? '-',
                    'customer_name' => $o->quotation->customer->user->name ?? '-',
                    'sales_id' => $o->quotation->customer->handledBy->id ?? '-',
                    'sales_name' => $o->quotation->customer->handledBy->name ?? '-',
                    'payment_plan' => $o->payment_plan,
                    'created_at' => $o->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $o->updated_at?->translatedFormat('d F Y'),
                    'has_receipts' => $hasReceipts,
                    'invoices' => $o->invoices->map(function ($i) {
                        return [
                            'id' => $i->id,
                            'invoice_number' => $i->invoice_number ?? '-',
                            'quotation_id' => $i->order->quotation->id ?? '-',
                            'quotation_number' => $i->order->quotation->quotation_number ?? '-',
                            'order_id' => $i->order_id ?? '-',
                            'order_number' => $i->order->order_number ?? '-',
                            'customer_id' => $i->order->quotation->customer->id ?? '-',
                            'customer_number' => $i->order->quotation->customer->customer_number ?? '-',
                            'customer_name' => $i->order->quotation->customer->user->name ?? '-',
                            'sales_id' => $i->order->quotation->customer->handledBy->id ?? '-',
                            'sales_name' => $i->order->quotation->customer->handledBy->name ?? '-',
                            'type' => $i->type,
                            'description' => $i->description,
                            'amount' => $this->formatService->cleanDecimal($i->amount),
                            'invoice_date' => $i->invoice_date_formatted,
                            'due_date' => $i->due_date_formatted,
                            'status' => $i->status,
                            'has_receipt' => $i->receipt->isNotEmpty(),
                            'receipt_id' => $i->receipt->first()?->id,
                            'created_at' => $i->created_at?->translatedFormat('d F Y'),
                            'updated_at' => $i->updated_at?->translatedFormat('d F Y'),
                        ];
                    }),
                ];
            });
    }

    public function getForFilter()
    {
        return Order::get()->map(fn ($o) => [
            'value' => $o->id,
            'label' => $o->order_number,
        ]);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'quotation_id' => $data['quotation_id'],
                'payment_plan' => $data['payment_plan'],
            ]);

            $this->quotationService->converted($order->quotation->id);

            foreach ($data['invoices'] ?? [] as $invoice) {
                $this->invoiceService->store([
                    'order_id' => $order->id,
                    'description' => $invoice['description'],
                    'amount' => $invoice['amount'],
                    'invoice_date' => $invoice['invoice_date'],
                    'due_date' => $invoice['due_date'],
                    'status' => $invoice['status'] ?? 'issued',
                    'items' => $invoice['items'] ?? [],
                ]);
            }

            activity()
                ->performedOn($order)
                ->withProperties(['subject_type' => 'Order', 'subject_id' => $order->id ?? null])
                ->log('Order created successfully #'.($order->id ?? null));

            return $order;
        });
    }

    public function getForEditShow($id)
    {
        $o = Order::with([
            'quotation',
            'invoices.quotationItems.confirmationMember',
        ])->findOrFail($id);

        return [
            'id' => $o->id,
            'order_number' => $o->order_number ?? '-',
            'quotation_id' => $o->quotation_id ?? '-',
            'quotation_number' => $o->quotation->quotation_number ?? '-',
            'payment_plan' => $o->payment_plan,
            'invoices' => $o->invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->order_id,
                'type' => $invoice->type,
                'description' => $invoice->description,
                'amount' => $this->formatService->cleanDecimal($invoice->amount),
                'invoice_date' => $invoice->invoice_date_formatted,
                'due_date' => $invoice->due_date_formatted,
                'status' => $invoice->status,
                'items' => $invoice->quotationItems->map(fn ($item) => [
                    'id' => $item->id,
                    'quotation_id' => $item->quotation_id,
                    'parent_id' => $item->parent_id,
                    'customer_confirmation_member_id' => $item->customer_confirmation_member_id,
                    'sharing_plan' => $item->confirmationMember?->sharing_plan,
                    'type' => $item->type,
                    'description' => $item->description,
                    'is_header' => $item->is_header,
                    'quantity' => $this->formatService->cleanDecimal($item->quantity),
                    'rate' => $this->formatService->cleanDecimal($item->rate),
                    'sort_order' => $item->sort_order,
                ]),
            ]),
        ];
    }

    public function update(array $data, int $id): Order
    {
        return DB::transaction(function () use ($data, $id) {
            $order = Order::with(['invoices.receipt', 'quotation'])->findOrFail($id);
            $order->quotation()->where('is_locked', false)->update(['is_locked' => true]);
            $order->update([
                'payment_plan' => $data['payment_plan'],
            ]);

            $incomingInvoiceIds = [];
            $seenInvoiceIds = [];
            $incomingInvoices = array_values($data['invoices'] ?? []);
            $existingInvoicesByNumber = $order->invoices
                ->filter(fn ($invoice) => ! empty($invoice->invoice_number))
                ->keyBy('invoice_number');
            $existingInvoicesByIndex = $order->invoices
                ->sortBy('id')
                ->values();

            foreach ($incomingInvoices as $index => $invoiceData) {
                $existingInvoiceId = $invoiceData['id'] ?? null;

                if (
                    empty($existingInvoiceId) &&
                    ! empty($invoiceData['invoice_number']) &&
                    $existingInvoicesByNumber->has($invoiceData['invoice_number'])
                ) {
                    $existingInvoiceId = $existingInvoicesByNumber
                        ->get($invoiceData['invoice_number'])
                        ?->id;
                }

                // Frontend fallback: if IDs are missing but invoice count is unchanged,
                // map by stable position to avoid recreating invoices.
                if (
                    empty($existingInvoiceId) &&
                    count($incomingInvoices) === $existingInvoicesByIndex->count() &&
                    $existingInvoicesByIndex->has($index)
                ) {
                    $candidateInvoiceId = (int) $existingInvoicesByIndex->get($index)->id;

                    if (! in_array($candidateInvoiceId, $seenInvoiceIds, true)) {
                        $existingInvoiceId = $candidateInvoiceId;
                    }
                }

                if (! empty($existingInvoiceId)) {
                    $existingInvoiceId = (int) $existingInvoiceId;

                    if (in_array($existingInvoiceId, $seenInvoiceIds, true)) {
                        throw ValidationException::withMessages([
                            'invoices' => 'Duplicate invoice rows detected. Please refresh and try again.',
                        ]);
                    }

                    if (! $order->invoices->contains('id', $existingInvoiceId)) {
                        throw ValidationException::withMessages([
                            'invoices' => 'One or more invoices do not belong to this order.',
                        ]);
                    }

                    $invoice = $this->invoiceService->update(
                        [
                            ...$invoiceData,
                            'delete_missing_quotation_items' => false,
                        ],
                        $existingInvoiceId
                    );

                    $seenInvoiceIds[] = $existingInvoiceId;
                } else {
                    $invoice = $this->invoiceService->store([
                        ...$invoiceData,
                        'order_id' => $order->id,
                        'delete_missing_quotation_items' => false,
                    ]);
                }

                $incomingInvoiceIds[] = $invoice->id;
            }

            $removableInvoices = $order->invoices()
                ->whereNotIn('id', $incomingInvoiceIds)
                ->get();

            $invoicesWithReceipts = $removableInvoices
                ->filter(fn ($invoice) => $invoice->receipt()->exists())
                ->values();

            if ($invoicesWithReceipts->isNotEmpty()) {
                $invoiceNumbers = $invoicesWithReceipts
                    ->pluck('invoice_number')
                    ->filter()
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'invoices' => $invoiceNumbers
                        ? "Cannot remove invoice(s) with receipt: {$invoiceNumbers}."
                        : 'Cannot remove invoice(s) with receipt.',
                ]);
            }

            NumberGenerator::rollbackByNumbers(
                'invoice',
                $removableInvoices->pluck('invoice_number')->filter()->all(),
            );

            foreach ($removableInvoices as $invoice) {
                $invoice->delete();
            }

            $order->load('invoices.quotationItems', 'quotation');
            $this->quotationService->syncQuotationItemsToRelevantInvoicesBySortOrder(
                $order->quotation,
                $incomingInvoiceIds,
            );

            $linkedQuotationItemIds = $order->invoices
                ->flatMap(fn ($invoice) => $invoice->quotationItems->pluck('id'))
                ->map(fn ($itemId) => (int) $itemId)
                ->unique()
                ->values()
                ->all();

            $this->quotationItemService->deleteUnusedQuotationItems(
                (int) $order->quotation_id,
                $linkedQuotationItemIds,
            );

            return $order->fresh('invoices.quotationItems');
        });
    }

    public function delete($id)
    {
        return Order::find($id)?->delete() ?? false;
    }

    /**
     * Get monthly order counts for dashboard (calendar month)
     */
    public function getMonthlyOrderCounts(): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentMonthOrders = Order::whereHas('quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->count();

        $previousMonthOrders = Order::whereHas('quotation', function ($q) {
            $q->where('status', '!=', 'cancelled');
        })->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();

        return [
            'current' => $currentMonthOrders,
            'previous' => $previousMonthOrders,
            'current_month_start' => $currentMonthStart,
            'current_month_end' => $currentMonthEnd,
        ];
    }
}

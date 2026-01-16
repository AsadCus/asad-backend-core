<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected $invoiceService, $formatService, $quotationItemService, $quotationService;

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

    public function getForDataTable()
    {
        return Order::with('quotation')->orderBy('order_number', 'desc')->get()->map(function ($o) {
            return [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'quotation_id' => $o->quotation_id,
                'quotation_number' => $o->quotation->quotation_number,
                'customer_id' => $o->quotation->customer->id,
                'customer_number' => $o->quotation->customer->customer_number,
                'customer_name' => $o->quotation->customer->user->name,
                'payment_plan' => $o->payment_plan,
                'handover_date' => $o->handover_date_formatted,
                'invoices' => $o->invoices->map(function ($i) {
                    return [
                        'id' => $i->id,
                        'invoice_number' => $i->invoice_number,
                        'quotation_id' => $i->order->quotation->id,
                        'quotation_number' => $i->order->quotation->quotation_number,
                        'order_id' => $i->order_id,
                        'order_number' => $i->order->order_number,
                        'customer_id' => $i->order->quotation->customer->id,
                        'customer_number' => $i->order->quotation->customer->customer_number,
                        'type' => $i->type,
                        'description' => $i->description,
                        'amount' => $this->formatService->cleanDecimal($i->amount),
                        'invoice_date' => $i->invoice_date_formatted,
                        'due_date' => $i->due_date_formatted,
                        'status' => $i->status,
                    ];
                }),
            ];
        });
    }

    public function getForFilter()
    {
        return Order::get()->map(fn($o) => [
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
                'handover_date' => $data['handover_date'],
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

            return $order;
        });
    }

    public function getForEditShow($id)
    {
        $o = Order::with(['quotation', 'invoices.quotationItems'])->findOrFail($id);

        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'quotation_id' => $o->quotation_id,
            'quotation_number' => $o->quotation->quotation_number,
            'payment_plan' => $o->payment_plan,
            'handover_date' => $o->handover_date_formatted,
            'invoices' => $o->invoices->map(fn($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->order_id,
                'type' => $invoice->type,
                'description' => $invoice->description,
                'amount' => $this->formatService->cleanDecimal($invoice->amount),
                'invoice_date' => $invoice->invoice_date_formatted,
                'due_date' => $invoice->due_date_formatted,
                'status' => $invoice->status,
                'items' => $invoice->quotationItems->map(fn($item) => [
                    'id' => $item->id,
                    'quotation_id' => $item->quotation_id,
                    'parent_id' => $item->parent_id,
                    'type' => $item->type,
                    'description' => $item->description,
                    'is_header' => $item->is_header,
                    'is_placement_fee' => $item->is_placement_fee,
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
            $order = Order::with('invoices')->findOrFail($id);
            $order->quotation()->where('is_locked', false)->update(['is_locked' => true]);
            $order->update([
                'payment_plan' => $data['payment_plan'],
                'handover_date' => $data['handover_date'],
            ]);

            $incomingInvoiceIds = [];

            foreach ($data['invoices'] ?? [] as $invoiceData) {
                if (!empty($invoiceData['id'])) {
                    $invoice = $this->invoiceService->update(
                        $invoiceData,
                        $invoiceData['id']
                    );
                } else {
                    $invoice = $this->invoiceService->store([
                        ...$invoiceData,
                        'order_id' => $order->id,
                    ]);
                }

                $incomingInvoiceIds[] = $invoice->id;
            }

            $order->invoices()->whereNotIn('id', $incomingInvoiceIds)->delete();

            return $order->fresh('invoices.quotationItems');
        });
    }

    public function delete($id)
    {
        return Order::find($id)?->delete() ?? false;
    }
}

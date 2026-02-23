<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function get()
    {
        return Receipt::with('invoice')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Receipt::with(['invoice.order.quotation.customer.user', 'invoice.order.quotation.customer.handledBy'])
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('invoice.order.quotation.customer', function ($cq) use ($value) {
                    $cq->where('handled_by', $value);
                });
            })
            ->orderBy('receipt_number', 'desc')->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'invoice_id' => $r->invoice_id ?? '-',
                    'invoice_number' => $r->invoice?->invoice_number ?? '-',
                    'invoice_description' => $r->invoice?->description ?? '-',
                    'receipt_number' => $r->receipt_number ?? '-',
                    'customer_id' => $r->invoice?->order->quotation->customer->id ?? '-',
                    'customer_number' => $r->invoice?->order->quotation->customer->customer_number ?? '-',
                    'customer_name' => $r->invoice?->order->quotation->customer->user->name ?? '-',
                    'sales_id' => $r->invoice?->order->quotation->customer->handledBy->id ?? '-',
                    'sales_name' => $r->invoice?->order->quotation->customer->handledBy->name ?? '-',
                    'amount' => $this->formatService->cleanDecimal($r->amount),
                    'receipt_date' => $r->receipt_date_formatted,
                    'payment_method' => $r->payment_method,
                    'reference' => $r->reference,
                    'description' => $r->description,
                ];
            });
    }

    public function getForFilter()
    {
        return Receipt::get()->map(fn ($r) => [
            'value' => $r->id,
            'label' => $r->receipt_number,
        ]);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $receipt = Receipt::create([
                'invoice_id' => $data['invoice_id'],
                'amount' => $this->formatService->cleanDecimal($data['amount']),
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $invoice = $receipt->invoice()->with(['order.quotation.customer'])->first();

            if ($invoice->outstanding_amount <= 0) {
                $invoice->update(['status' => 'paid']);
            } else {
                $invoice->update(['status' => 'partial']);
            }

            return $receipt;
        });
    }

    public function getForEditShow($id)
    {
        $r = Receipt::with('invoice')->findOrFail($id);

        return [
            'id' => $r->id,
            'receipt_number' => $r->receipt_number,
            'receipt_date' => $r->receipt_date_formatted,
            'invoice_id' => $r->invoice_id,
            'invoice_number' => $r->invoice?->invoice_number,
            'order_id' => $r->invoice?->order_id,
            'order_number' => $r->invoice?->order->order_number,
            'customer_id' => $r->invoice?->order->quotation->customer_id,
            'customer_name' => $r->invoice?->order->quotation->customer->user->name,
            'customer_address' => $r->invoice?->order->quotation->customer->address,
            'maid_id' => $r->invoice?->order->quotation->maid_id,
            'maid_name' => null,
            'amount' => $this->formatService->cleanDecimal($r->amount),
            'payment_method' => $r->payment_method,
            'reference' => $r->reference,
            'description' => $r->description,
            'sales_registration_number' => $r->invoice?->order->quotation->sales_registration_number,
            'items' => $r->invoice?->quotationItems->map(fn ($item) => [
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
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $receipt = Receipt::findOrFail($id);

            $receipt->update([
                'invoice_id' => $data['invoice_id'],
                'amount' => $this->formatService->cleanDecimal($data['amount']),
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $invoice = $receipt->invoice()->with(['order.quotation.customer'])->first();

            if ($invoice->outstanding_amount <= 0) {
                $invoice->update(['status' => 'paid']);
            } else {
                $invoice->update(['status' => 'partial']);
            }

            return $receipt;
        });
    }

    public function delete($id)
    {
        return Receipt::find($id)?->delete() ?? false;
    }
}

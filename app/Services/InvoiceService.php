<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected $formatService;

    protected $quotationItemService;

    public function __construct(FormatService $formatService, QuotationItemService $quotationItemService)
    {
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
    }

    public function get()
    {
        return Invoice::with('order')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Invoice::with(['order.quotation.customer.user', 'order.quotation.customer.handledBy'])
            ->with('receipt:id,invoice_id')
            ->withCount('receipt')
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('order.quotation.customer', function ($cq) use ($value) {
                    $cq->where('handled_by', $value);
                });
            })
            ->orderBy('invoice_number', 'desc')->get()->map(function ($i) {
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
                    'has_receipt' => (int) ($i->receipt_count ?? 0) > 0,
                    'receipt_id' => $i->receipt->first()?->id,
                    'created_at' => $i->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $i->updated_at?->translatedFormat('d F Y'),
                ];
            });
    }

    public function getForFilter()
    {
        return Invoice::get()->map(fn ($i) => [
            'value' => $i->id,
            'label' => $i->invoice_number,
        ]);
    }

    public function store(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'order_id' => $data['order_id'],
                'type' => $data['type'] ?? null,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? 'issued',
            ]);

            if (! empty($data['items'])) {
                $quotationItemIds = $this->quotationItemService->replaceQuotationItems(
                    $invoice->order->quotation->id,
                    $data['items'],
                    $data['delete_missing_quotation_items'] ?? true,
                );
                $invoice->quotationItems()->sync($quotationItemIds);
            }

            activity()
                ->performedOn($invoice)
                ->withProperties(['subject_type' => 'Invoice', 'subject_id' => $invoice->id ?? null])
                ->log('Invoice created successfully #'.($invoice->id ?? null));

            return $invoice;
        });
    }

    public function getForEditShow($id)
    {
        $i = Invoice::with('order')->findOrFail($id);

        return [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'customer_id' => $i->order->quotation->customer->id,
            'customer_number' => $i->order->quotation->customer->customer_number,
            'customer_name' => $i->order->quotation->customer->user->name,
            'customer_email' => $i->order->quotation->customer->user->email,
            'customer_contact' => $i->order->quotation->customer->user->contact,
            'customer_address' => $i->order->quotation->customer->address,
            'order_id' => $i->order_id,
            'order_number' => $i->order->order_number,
            'type' => $i->type,
            'description' => $i->description,
            'amount' => $this->formatService->cleanDecimal($i->amount),
            'payment_plan' => $i->order->quotation->payment_plan,
            'payment_method' => $i->order->quotation->payment_method,
            'placement_fee' => $this->formatService->cleanDecimal($i->order->quotation->total_amount),
            'invoice_date' => $i->invoice_date_formatted,
            'due_date' => $i->due_date_formatted,
            'sales_registration_number' => $i->order->quotation->sales_registration_number,
            'status' => $i->status,
            'items' => $i->quotationItems->map(fn ($item) => [
                'id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'parent_id' => $item->parent_id,
                'type' => $item->type,
                'description' => $item->description,
                'is_header' => $item->is_header,
                'quantity' => $this->formatService->cleanDecimal($item->quantity),
                'rate' => $this->formatService->cleanDecimal($item->rate),
                'sort_order' => $item->sort_order,
            ]),
        ];
    }

    public function update(array $data, int $id): Invoice
    {
        return DB::transaction(function () use ($data, $id) {
            $query = Invoice::with('order.quotation');

            // If order_id is provided, scope to that order
            if (! empty($data['order_id'])) {
                $query->where('order_id', $data['order_id']);
            }

            $invoice = $query->findOrFail($id);

            $invoice->update([
                'type' => $data['type'] ?? null,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? $invoice->status,
            ]);

            if (array_key_exists('items', $data)) {
                $quotationItemIds = $this->quotationItemService->replaceQuotationItems(
                    $invoice->order->quotation->id,
                    $data['items'],
                    $data['delete_missing_quotation_items'] ?? true,
                );
                $invoice->quotationItems()->sync($quotationItemIds);
            }

            if ($invoice->receipt()->exists()) {
                $invoice->receipt()->update([
                    'amount' => $invoice->amount,
                ]);
            }

            activity()
                ->performedOn($invoice)
                ->withProperties(['subject_type' => 'Invoice', 'subject_id' => $invoice->id ?? null])
                ->log('Invoice updated successfully #'.($invoice->id ?? null));

            return $invoice;
        });
    }

    public function delete($id)
    {
        return Invoice::find($id)?->delete() ?? false;
    }
}

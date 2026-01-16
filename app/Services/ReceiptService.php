<?php

namespace App\Services;

use App\Models\Receipt;
use App\Helpers\FormatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptService
{
    protected $formatService;
    protected $maidStatusService;

    public function __construct(FormatService $formatService, MaidStatusService $maidStatusService)
    {
        $this->formatService = $formatService;
        $this->maidStatusService = $maidStatusService;
    }

    public function get()
    {
        return Receipt::with('invoice')->get();
    }

    public function getForDataTable()
    {
        return Receipt::with('invoice')->orderBy('receipt_number', 'desc')->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'invoice_id' => $r->invoice_id,
                'invoice_number' => $r->invoice?->invoice_number,
                'invoice_description' => $r->invoice?->description,
                'receipt_number' => $r->receipt_number,
                'customer_id' => $r->invoice?->order->quotation->customer->id,
                'customer_number' => $r->invoice?->order->quotation->customer->customer_number,
                'customer_name' => $r->invoice?->order->quotation->customer->user->name,
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
        return Receipt::get()->map(fn($r) => [
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

            $invoice = $receipt->invoice()->with(['order.quotation.maid', 'order.quotation.customer'])->first();

            if ($invoice->outstanding_amount <= 0) {
                $invoice->update(['status' => 'paid']);

                // Auto-assign maid if deposit invoice is fully paid
                if ($invoice->type === 'deposit') {
                    $this->handleDepositPaid($invoice);
                }
            } else {
                $invoice->update(['status' => 'partial']);
            }
        });
    }

    /**
     * Handle deposit invoice paid - auto assign maid to customer
     */
    protected function handleDepositPaid($invoice)
    {
        try {
            $order = $invoice->order;
            if (!$order) {
                Log::warning('ReceiptService: No order found for invoice', ['invoice_id' => $invoice->id]);
                return;
            }

            $quotation = $order->quotation;
            if (!$quotation) {
                Log::warning('ReceiptService: No quotation found for order', ['order_id' => $order->id]);
                return;
            }

            $maidId = $quotation->maid_id;
            if (!$maidId) {
                Log::warning('ReceiptService: No maid assigned to quotation', ['quotation_id' => $quotation->id]);
                return;
            }

            // If maid already assigned via quotation ready, do nothing
            if ($quotation->maid && $quotation->maid->status === 'assigned') {
                Log::info('ReceiptService: Skipping auto-assign, maid already assigned from quotation ready', [
                    'maid_id' => $maidId,
                    'customer_id' => $quotation->customer_id,
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Auto-assign maid to customer (legacy flow when not yet assigned)
            $result = $this->maidStatusService->assignMaidFromPayment($maidId, $quotation->customer_id);

            Log::info('ReceiptService: Maid auto-assigned after deposit payment', [
                'maid_id' => $maidId,
                'customer_id' => $quotation->customer_id,
                'invoice_id' => $invoice->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('ReceiptService: Failed to auto-assign maid after deposit payment', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
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
            'maid_name' => $r->invoice?->order->quotation->maid->name,
            'amount' => $this->formatService->cleanDecimal($r->amount),
            'payment_method' => $r->payment_method,
            'reference' => $r->reference,
            'description' => $r->description,
            'items' => $r->invoice?->quotationItems->map(fn($item) => [
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

            return $receipt;
        });
    }

    public function delete($id)
    {
        return Receipt::find($id)?->delete() ?? false;
    }
}

<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\FinancialTransaction;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    protected $formatService;

    protected $quotationItemService;

    public function __construct(FormatService $formatService, QuotationItemService $quotationItemService)
    {
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
    }

    public function getForDataTable(array $filters = [])
    {
        $data = Quotation::with(['customer.user', 'customer.handledBy', 'quotationItems', 'order'])->withTrashed()
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('customer', function ($cq) use ($value) {
                    $cq->where('handled_by', $value);
                });
            })->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })->when($filters['customer_id'] ?? null, function ($q, $value) {
                $q->where('customer_id', $value);
            })->when($filters['from_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '>=', $value);
            })->when($filters['to_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '<=', $value);
            })->orderBy('quotation_number', 'desc')->get()->map(function ($q) {
                return [
                    'id' => $q->id,
                    'quotation_number' => $q->quotation_number ?? '-',
                    'order_id' => $q->order->id ?? '-',
                    'order_number' => $q->order->order_number ?? '-',
                    'customer_id' => $q->customer_id,
                    'customer_number' => $q->customer->customer_number ?? '-',
                    'customer_name' => $q->customer->user->name ?? '-',
                    'sales_id' => $q->customer->handledBy->id ?? '-',
                    'sales_name' => $q->customer->handledBy->name ?? '-',
                    'description' => $q->description ?? '-',
                    'quotation_date' => $q->quotation_date_formatted,
                    'expiry_date' => $q->expiry_date_formatted,
                    'items_count' => $q->quotationItems->count(),
                    'total_amount' => $this->formatService->cleanDecimal($q->total_amount),
                    'payment_plan' => $q->payment_plan_label,
                    'payment_method' => ucfirst($q->payment_method),
                    'status' => $q->status,
                    'reason' => $q->reason,
                    'have_invoices' => $q->order?->invoices()->exists() ?? false,
                    'created_at' => $q->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $q->updated_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        $data = Quotation::select('id', 'quotation_number')->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->quotation_number,
            ];
        });

        return $data;
    }

    public function getCanCreateOrderForFilter()
    {
        $data = Quotation::select('id', 'quotation_number')->whereIn('status', ['sent', 'accepted'])->whereDoesntHave('order')->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->quotation_number,
            ];
        });

        return $data;
    }

    public function store(array $data = []): Quotation
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['quotation_date'])) {
                $data['quotation_date'] = Carbon::parse($data['quotation_date'])->format('Y-m-d');
            }
            if (! empty($data['expiry_date'])) {
                $data['expiry_date'] = Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }

            $quotation = Quotation::create([
                'customer_id' => $data['customer_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'deposit_type' => $data['deposit_type'] ?? null,
                'deposit_value' => $data['deposit_value'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'reason' => $data['reason'] ?? null,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->quotationItemService->storeQuotationItems($quotation->id, $data['items']);
            }

            return $quotation;
        });
    }

    public function getForEditShow($id): array
    {
        $quotation = Quotation::with(['customer.user', 'quotationItems'])->findOrFail($id);

        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_id' => $quotation->customer_id,
            'customer_number' => $quotation->customer->customer_number ?? '',
            'customer_name' => $quotation->customer->user->name ?? '',
            'nric_number' => $quotation->customer->nric_number ?? '',
            'customer_contact' => $quotation->customer->user->contact ?? '',
            'customer_address' => $quotation->customer->address ?? '',
            'customer_email' => $quotation->customer->user->email ?? '',
            'description' => $quotation->description ?? '',
            'quotation_date' => $quotation->quotation_date_formatted,
            'expiry_date' => $quotation->expiry_date_formatted,
            'total_amount' => $this->formatService->cleanDecimal($quotation->total_amount),
            'payment_plan' => $quotation->payment_plan,
            'deposit_type' => $quotation->deposit_type,
            'deposit_value' => $this->formatService->cleanDecimal($quotation->deposit_value),
            'payment_method' => $quotation->payment_method,
            'status' => $quotation->status,
            'reason' => $quotation->reason,
            'sales_registration_number' => $quotation->sales_registration_number,
            'model' => 'quotation',
            'notes' => $quotation->quotationNotes->sortBy('sort_order')->values()->toArray(),
            'items' => $quotation->quotationItems->sortBy('sort_order')->map(function (QuotationItem $it) {
                return [
                    'id' => $it->id,
                    'parent_id' => $it->parent_id,
                    'description' => $it->description,
                    'is_header' => $it->is_header,
                    'is_optional' => $it->is_optional,
                    'quantity' => $this->formatService->cleanDecimal($it->quantity),
                    'rate' => $this->formatService->cleanDecimal($it->rate),
                    'sort_order' => $it->sort_order,
                ];
            })->values()->toArray(),
        ];
    }

    public function update(array $data, int $id): Quotation
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::findOrFail($id);

            if (! empty($data['quotation_date'])) {
                $data['quotation_date'] = Carbon::parse($data['quotation_date'])->format('Y-m-d');
            }
            if (! empty($data['expiry_date'])) {
                $data['expiry_date'] = Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }

            $quotation->update([
                'customer_id' => $data['customer_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'deposit_type' => $data['deposit_type'] ?? null,
                'deposit_value' => $data['deposit_value'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $quotation->status,
                'reason' => $data['reason'] ?? $quotation->reason,
            ]);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->quotationItemService->replaceQuotationItems($quotation->id, $data['items']);
            }

            return $quotation->fresh();
        });
    }

    public function accept($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => 'accepted']);

            return $quotation;
        });
    }

    public function converted($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => 'converted']);

            return $quotation;
        });
    }

    public function reject($data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::findOrFail($id);

            $quotation->update([
                'status' => 'rejected',
                'reason' => $data['reason'] ?? null,
            ]);

            return $quotation;
        });
    }

    public function ready($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update([
                'status' => 'sent',
            ]);

            return $quotation->fresh();
        });
    }

    public function expire($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);

            $quotation->update([
                'status' => 'expired',
            ]);

            return $quotation;
        });
    }

    public function cancel($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);

            if ($quotation->order) {
                $invoices = $quotation->order->invoices;

                foreach ($invoices as $invoice) {
                    $receiptIds = $invoice->receipt()->pluck('id');

                    if ($receiptIds->isNotEmpty()) {
                        FinancialTransaction::where('reference_type', 'App\Models\Receipt')->whereIn('reference_id', $receiptIds)->delete();
                    }

                    $invoice->update(['status' => 'cancelled']);
                }
            }

            $quotation->update(['status' => 'cancelled']);

            return $quotation;
        });
    }

    public function delete($id)
    {
        $quotation = Quotation::find($id);

        if (! $quotation) {
            return false;
        }

        $quotation->update(['status' => 'expired']);

        return $quotation->delete();
    }
}

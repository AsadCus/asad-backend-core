<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Invoice;
use App\Support\DataScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected $formatService;

    protected $quotationItemService;

    protected $numberingService;

    public function __construct(FormatService $formatService, QuotationItemService $quotationItemService, NumberingService $numberingService)
    {
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
        $this->numberingService = $numberingService;
    }

    public function get()
    {
        return Invoice::with('order')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Invoice::with(['order.quotation.customer.user', 'order.quotation.customer.handledBy', 'order.quotation.customerConfirmation.enquiry.handledBy:id,name'])
            ->with('receipt:id,invoice_id')
            ->withCount('receipt')
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('order.quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('created_by', $value);
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
                    'sales_id' => $i->order->quotation->customerConfirmation?->enquiry?->handledBy?->id ?? '-',
                    'sales_name' => $i->order->quotation->customerConfirmation?->enquiry?->handledBy?->name ?? '-',
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

    public function getForFilter(array $filters = [])
    {
        return Invoice::query()
            ->when($filters['sales_id'] ?? null, function ($query, $value) {
                $query->whereHas('order.quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('created_by', $value);
                });
            })
            ->get()
            ->map(fn ($i) => [
                'value' => $i->id,
                'label' => $i->invoice_number,
            ]);
    }

    public function store(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'order_id' => $data['order_id'],
                'invoice_number' => $this->numberingService->ensureNumber(
                    'invoice',
                    $data['invoice_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'type' => $data['type'] ?? null,
                'description' => $data['description'],
                'payment_method' => $data['payment_method'] ?? null,
                'extensions' => $this->normalizeInvoiceExtensions($data['extensions'] ?? []),
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
                    false,
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
        $query = Invoice::with([
            'quotationItems.taxes',
            'order.quotation.customer.user',
            'invoiceNotes',
        ]);

        if (DataScope::shouldScopeSalesOwnership()) {
            $query->whereHas('order.quotation', function ($quotationQuery) {
                $quotationQuery->where('created_by', auth()->id());
            });
        }

        $i = $query->findOrFail($id);

        $subtotalAmount = (float) $i->quotationItems
            ->where('is_header', false)
            ->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

        $totalAmount = (float) ($i->amount ?? 0);
        $extensionTotalAmount = round($totalAmount - $subtotalAmount, 2);

        $itemTaxExtensions = $this->buildItemTaxExtensions($i->quotationItems);
        $itemTaxTotal = (float) collect($itemTaxExtensions)
            ->sum(fn (array $extension): float => (float) ($extension['amount'] ?? 0));

        $storedInvoiceExtensions = $this->normalizeInvoiceExtensions($i->extensions ?? []);

        $quotationExtensions = ! empty($storedInvoiceExtensions)
            ? collect($storedInvoiceExtensions)
                ->map(function (array $extension) {
                    return [
                        'id' => $extension['id'] ?? null,
                        'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                        'name' => $extension['name'] ?? 'Extension',
                        'type' => $extension['type'] ?? 'discount',
                        'calculation_mode' => $extension['calculation_mode'] ?? null,
                        'calculation_value' => $this->formatService->cleanDecimal($extension['calculation_value'] ?? null),
                        'sort_order' => $extension['sort_order'] ?? null,
                        'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? 0),
                    ];
                })
                ->values()
                ->all()
            : $this->allocateExtensionsForTargetTotal(
                collect(is_array($i->order?->quotation?->extensions ?? null) ? $i->order?->quotation?->extensions : []),
                round($extensionTotalAmount - $itemTaxTotal, 2),
            );

        $extensions = array_values(array_merge($itemTaxExtensions, $quotationExtensions));

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
            'payment_method' => $i->payment_method,
            'placement_fee' => $this->formatService->cleanDecimal($i->order->quotation->total_amount),
            'invoice_date' => $i->invoice_date_formatted,
            'due_date' => $i->due_date_formatted,
            'sales_registration_number' => $i->order->quotation->sales_registration_number,
            'status' => $i->status,
            'subtotal_amount' => $this->formatService->cleanDecimal($subtotalAmount),
            'extension_total_amount' => $this->formatService->cleanDecimal($extensionTotalAmount),
            'total_amount' => $this->formatService->cleanDecimal($totalAmount),
            'extensions' => $extensions,
            'notes' => $i->invoiceNotes->sortBy('sort_order')->values()->toArray(),
            'items' => $i->quotationItems->map(fn ($item) => [
                'id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'parent_id' => $item->parent_id,
                'type' => $item->type,
                'description' => $item->description,
                'is_header' => $item->is_header,
                'quantity' => $this->formatService->cleanDecimal($item->quantity),
                'rate' => $this->formatService->cleanDecimal($item->rate),
                'taxes' => $item->taxes->map(fn ($tax) => [
                    'id' => $tax->id,
                    'quotation_item_id' => $tax->quotation_item_id,
                    'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                    'name' => $tax->name,
                    'calculation_mode' => $tax->calculation_mode,
                    'calculation_value' => $this->formatService->cleanDecimal($tax->calculation_value),
                    'sort_order' => $tax->sort_order,
                ])->values(),
                'sort_order' => $item->sort_order,
            ]),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItemTaxExtensions(Collection $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            if ((bool) ($item->is_header ?? false)) {
                continue;
            }
            $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

            foreach ($item->taxes as $tax) {
                $calculationMode = (string) ($tax->calculation_mode ?? '');
                $calculationValue = (float) ($tax->calculation_value ?? 0);

                if (! in_array($calculationMode, ['fixed', 'percentage'], true) || $calculationValue <= 0) {
                    continue;
                }

                $taxAmount = $calculationMode === 'percentage'
                    ? ($lineAmount * $calculationValue / 100)
                    : $calculationValue;

                $key = implode('|', [
                    (int) ($tax->quotation_extension_master_id ?? 0),
                    strtolower(trim((string) ($tax->name ?? 'Tax'))),
                    $calculationMode,
                    (string) $calculationValue,
                ]);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => null,
                        'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                        'name' => $tax->name ?: 'Tax',
                        'type' => 'tax',
                        'calculation_mode' => $calculationMode,
                        'calculation_value' => $this->formatService->cleanDecimal($calculationValue),
                        'amount' => 0.0,
                    ];
                }

                $grouped[$key]['amount'] += $taxAmount;
            }
        }

        return collect(array_values($grouped))
            ->map(function (array $extension, int $index) {
                return [
                    ...$extension,
                    'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? 0),
                    'sort_order' => $index + 1,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $extensions
     * @return array<int, array<string, mixed>>
     */
    private function allocateExtensionsForTargetTotal(Collection $extensions, float $targetTotal): array
    {
        if ($extensions->isEmpty()) {
            return [];
        }

        $sortedExtensions = $extensions
            ->sortBy('sort_order')
            ->values();

        $sourceTotal = (float) $sortedExtensions->sum(function (array $extension): float {
            return (float) ($extension['amount'] ?? 0);
        });

        $allocated = [];
        $runningTotal = 0.0;
        $lastIndex = $sortedExtensions->count() - 1;

        foreach ($sortedExtensions as $index => $extension) {
            if ($index === $lastIndex) {
                $amount = round($targetTotal - $runningTotal, 2);
            } elseif (abs($sourceTotal) > 0.00001) {
                $ratio = (float) ($extension['amount'] ?? 0) / $sourceTotal;
                $amount = round($targetTotal * $ratio, 2);
            } else {
                $amount = 0.0;
            }

            $runningTotal += $amount;

            $allocated[] = [
                'id' => null,
                'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                'name' => $extension['name'] ?? 'Extension',
                'type' => $extension['type'] ?? 'discount',
                'calculation_mode' => $extension['calculation_mode'] ?? 'fixed',
                'calculation_value' => $this->formatService->cleanDecimal($extension['calculation_value'] ?? null),
                'sort_order' => $extension['sort_order'] ?? ($index + 1),
                'amount' => $this->formatService->cleanDecimal($amount),
            ];
        }

        return $allocated;
    }

    public function update(array $data, int $id): Invoice
    {
        return DB::transaction(function () use ($data, $id) {
            $query = Invoice::with('order.quotation');

            if (DataScope::shouldScopeSalesOwnership()) {
                $query->whereHas('order.quotation', function ($quotationQuery) {
                    $quotationQuery->where('created_by', auth()->id());
                });
            }

            // If order_id is provided, scope to that order
            if (! empty($data['order_id'])) {
                $query->where('order_id', $data['order_id']);
            }

            $invoice = $query->findOrFail($id);

            $resolvedInvoiceNumber = array_key_exists('invoice_number', $data)
                ? $this->numberingService->ensureNumber(
                    'invoice',
                    $data['invoice_number'],
                    (int) $invoice->id,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                )
                : $invoice->invoice_number;

            $invoice->update([
                'invoice_number' => $resolvedInvoiceNumber,
                'type' => $data['type'] ?? null,
                'description' => $data['description'],
                'payment_method' => $data['payment_method'] ?? $invoice->payment_method,
                'extensions' => $this->normalizeInvoiceExtensions($data['extensions'] ?? []),
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
                    false,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeInvoiceExtensions(array $extensions): array
    {
        return collect($extensions)
            ->filter(fn ($extension) => is_array($extension))
            ->map(function (array $extension, int $index) {
                $calculationMode = (string) ($extension['calculation_mode'] ?? 'fixed');
                if (! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    $calculationMode = 'fixed';
                }

                return [
                    'id' => $extension['id'] ?? null,
                    'quotation_extension_master_id' => ! empty($extension['quotation_extension_master_id'])
                        ? (int) $extension['quotation_extension_master_id']
                        : null,
                    'name' => (string) ($extension['name'] ?? 'Extension'),
                    'type' => (string) ($extension['type'] ?? 'discount'),
                    'calculation_mode' => $calculationMode,
                    'calculation_value' => $this->formatService->cleanDecimal(
                        $extension['calculation_value'] ?? 0
                    ) ?? 0,
                    'amount' => $this->formatService->cleanDecimal(
                        $extension['amount'] ?? 0
                    ) ?? 0,
                    'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
                ];
            })
            ->values()
            ->all();
    }
}

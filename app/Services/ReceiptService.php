<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Invoice;
use App\Models\PaymentMethodMaster;
use App\Models\QuotationExtension;
use App\Models\Receipt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    private const FALLBACK_DEFAULT_PAYMENT_METHOD = 'transfer';

    protected $formatService;

    protected $numberingService;

    public function __construct(FormatService $formatService, NumberingService $numberingService)
    {
        $this->formatService = $formatService;
        $this->numberingService = $numberingService;
    }

    public function get()
    {
        return Receipt::with('invoice')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Receipt::with(['invoice.order.quotation.customer.user', 'invoice.order.quotation.customer.handledBy', 'invoice.order.quotation.customerConfirmation.enquiry.handledBy:id,name'])
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('invoice.order.quotation.customerConfirmation.enquiry', function ($enquiryQuery) use ($value) {
                    $enquiryQuery->where('handled_by', $value);
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
                    'sales_id' => $r->invoice?->order->quotation->customerConfirmation?->enquiry?->handledBy?->id ?? '-',
                    'sales_name' => $r->invoice?->order->quotation->customerConfirmation?->enquiry?->handledBy?->name ?? '-',
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

    public function getDefaultPaymentMethodValue(): string
    {
        $default = PaymentMethodMaster::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('value');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        $fallback = PaymentMethodMaster::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('value');

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return self::FALLBACK_DEFAULT_PAYMENT_METHOD;
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::query()->findOrFail((int) $data['invoice_id']);

            $alreadyHasReceipt = Receipt::query()
                ->where('invoice_id', $invoice->id)
                ->exists();

            if ($alreadyHasReceipt) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'A receipt already exists for this invoice.',
                ]);
            }

            $receipt = Receipt::create([
                'invoice_id' => $invoice->id,
                'receipt_number' => $this->numberingService->ensureNumber(
                    'receipt',
                    $data['receipt_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'amount' => $this->formatService->cleanDecimal($data['amount']),
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            activity()
                ->performedOn($receipt)
                ->withProperties(['subject_type' => 'Receipt', 'subject_id' => $receipt->id ?? null])
                ->log('Receipt created successfully #'.($receipt->id ?? null));

            return $receipt;
        });
    }

    public function getForEditShow($id)
    {
        $r = Receipt::with([
            'invoice.quotationItems.taxes',
            'invoice.order.quotation.quotationExtensions',
            'invoice.order.quotation.customer.user',
        ])->findOrFail($id);

        $invoice = $r->invoice;

        $subtotalAmount = (float) ($invoice?->quotationItems
            ->where('is_header', false)
            ->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            }) ?? 0);

        $totalAmount = (float) ($r->amount ?? 0);
        $extensionTotalAmount = round($totalAmount - $subtotalAmount, 2);

        $itemTaxExtensions = $this->buildItemTaxExtensions($invoice?->quotationItems ?? collect());
        $itemTaxTotal = (float) collect($itemTaxExtensions)
            ->sum(fn (array $extension): float => (float) ($extension['amount'] ?? 0));

        $storedInvoiceExtensions = collect($invoice?->extensions ?? [])
            ->filter(fn ($extension) => is_array($extension))
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
            ->all();

        $quotationExtensions = ! empty($storedInvoiceExtensions)
            ? $storedInvoiceExtensions
            : $this->allocateExtensionsForTargetTotal(
                $invoice?->order?->quotation?->quotationExtensions ?? collect(),
                round($extensionTotalAmount - $itemTaxTotal, 2),
            );

        $extensions = array_values(array_merge($itemTaxExtensions, $quotationExtensions));

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
            'amount' => $this->formatService->cleanDecimal($r->amount),
            'payment_method' => $r->payment_method,
            'reference' => $r->reference,
            'description' => $r->description,
            'sales_registration_number' => $r->invoice?->order->quotation->sales_registration_number,
            'subtotal_amount' => $this->formatService->cleanDecimal($subtotalAmount),
            'extension_total_amount' => $this->formatService->cleanDecimal($extensionTotalAmount),
            'total_amount' => $this->formatService->cleanDecimal($totalAmount),
            'extensions' => $extensions,
            'items' => $r->invoice?->quotationItems->map(fn ($item) => [
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
     * @param  Collection<int, QuotationExtension>  $extensions
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

        $sourceTotal = (float) $sortedExtensions->sum(function (QuotationExtension $extension): float {
            return (float) ($extension->amount ?? 0);
        });

        $allocated = [];
        $runningTotal = 0.0;
        $lastIndex = $sortedExtensions->count() - 1;

        foreach ($sortedExtensions as $index => $extension) {
            if ($index === $lastIndex) {
                $amount = round($targetTotal - $runningTotal, 2);
            } elseif (abs($sourceTotal) > 0.00001) {
                $ratio = (float) ($extension->amount ?? 0) / $sourceTotal;
                $amount = round($targetTotal * $ratio, 2);
            } else {
                $amount = 0.0;
            }

            $runningTotal += $amount;

            $allocated[] = [
                'id' => $extension->id,
                'quotation_extension_master_id' => $extension->quotation_extension_master_id,
                'name' => $extension->name,
                'type' => $extension->type,
                'calculation_mode' => $extension->calculation_mode,
                'calculation_value' => $this->formatService->cleanDecimal($extension->calculation_value),
                'sort_order' => $extension->sort_order,
                'amount' => $this->formatService->cleanDecimal($amount),
            ];
        }

        return $allocated;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $receipt = Receipt::findOrFail($id);

            $targetInvoiceId = array_key_exists('invoice_id', $data) && $data['invoice_id'] !== null
                ? (int) $data['invoice_id']
                : (int) $receipt->invoice_id;

            if ($targetInvoiceId !== (int) $receipt->invoice_id) {
                $alreadyHasReceipt = Receipt::query()
                    ->where('invoice_id', $targetInvoiceId)
                    ->where('id', '!=', $receipt->id)
                    ->exists();

                if ($alreadyHasReceipt) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'A receipt already exists for this invoice.',
                    ]);
                }
            }

            $amount = $receipt->amount;
            if (array_key_exists('amount', $data) && $data['amount'] !== null) {
                $amount = $this->formatService->cleanDecimal($data['amount']);
            }

            $resolvedReceiptNumber = array_key_exists('receipt_number', $data)
                ? $this->numberingService->ensureNumber(
                    'receipt',
                    $data['receipt_number'],
                    (int) $receipt->id,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                )
                : $receipt->receipt_number;

            $receipt->update([
                'receipt_number' => $resolvedReceiptNumber,
                'invoice_id' => $targetInvoiceId,
                'amount' => $amount,
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            activity()
                ->performedOn($receipt)
                ->withProperties(['subject_type' => 'Receipt', 'subject_id' => $receipt->id ?? null])
                ->log('Receipt updated successfully #'.($receipt->id ?? null));

            return $receipt;
        });
    }

    public function delete($id)
    {
        return Receipt::find($id)?->delete() ?? false;
    }
}

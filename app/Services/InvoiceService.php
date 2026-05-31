<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\QuotationItem;
use App\Support\DataScope;
use App\Support\InvoiceStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $invoices = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
            Invoice::with([
                'order.quotation.customer.user',
                'order.quotation.customerConfirmation.enquiry.handledBy:id,name',
                'order.quotation.customerConfirmation.package:id,package_number,name,status',
                'order.quotation.handledBy:id,name',
                'quotationItems:id,is_header,customer_confirmation_member_id',
            ])
                ->with('receipt:id,invoice_id')
                ->withCount('receipt')
                ->where('status', '!=', InvoiceStatus::Refund),
            'order.quotation'
        )
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('order.quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('handled_by', $value);
                });
            })
            ->orderBy('invoice_number', 'desc')
            ->get();

        $linkedMemberIdsByInvoice = $invoices->mapWithKeys(function (Invoice $invoice): array {
            $memberIds = $invoice->quotationItems
                ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($memberId): int => (int) $memberId)
                ->filter(fn (int $memberId): bool => $memberId > 0)
                ->unique()
                ->values()
                ->all();

            return [(int) $invoice->id => $memberIds];
        });

        $allLinkedMemberIds = $linkedMemberIdsByInvoice
            ->values()
            ->flatten()
            ->map(fn ($memberId): int => (int) $memberId)
            ->filter(fn (int $memberId): bool => $memberId > 0)
            ->unique()
            ->values();

        $memberStatuses = $allLinkedMemberIds->isEmpty()
            ? collect()
            : CustomerConfirmationMember::query()
                ->whereIn('id', $allLinkedMemberIds->all())
                ->pluck('status', 'id')
                ->mapWithKeys(fn ($status, $memberId): array => [(int) $memberId => strtolower(trim((string) $status))]);

        $memberIdsWithPaidReceiptHistory = $allLinkedMemberIds->isEmpty()
            ? collect()
            : QuotationItem::query()
                ->whereIn('customer_confirmation_member_id', $allLinkedMemberIds->all())
                ->whereHas('invoices.receipt', function ($query): void {
                    $query->where('amount', '>', 0);
                })
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($memberId): int => (int) $memberId)
                ->filter(fn (int $memberId): bool => $memberId > 0)
                ->unique()
                ->values()
                ->flip();

        return $invoices->map(function ($i) use ($linkedMemberIdsByInvoice, $memberStatuses, $memberIdsWithPaidReceiptHistory) {
            $packageStatus = strtolower(trim((string) ($i->order->quotation->customerConfirmation?->package?->status ?? '')));
            $isPackageStatusBlocked = in_array($packageStatus, ['full', 'closed', 'completed'], true);
            $linkedMemberIds = $linkedMemberIdsByInvoice->get((int) $i->id, []);

            $hasLinkedMemberWithPaidHistory = collect($linkedMemberIds)->contains(function ($memberId) use ($memberStatuses, $memberIdsWithPaidReceiptHistory): bool {
                $normalizedStatus = strtolower(trim((string) ($memberStatuses->get((int) $memberId) ?? '')));

                if (in_array($normalizedStatus, ['partially_paid', 'fully_paid', 'overpaid'], true)) {
                    return true;
                }

                return $memberIdsWithPaidReceiptHistory->has((int) $memberId);
            });

            $isPackageReceiptLocked = $isPackageStatusBlocked
                && ! empty($linkedMemberIds)
                && ! $hasLinkedMemberWithPaidHistory;

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
                'package_name' => $i->order->quotation->customerConfirmation?->package?->name ?? '',
                'package_number' => $i->order->quotation->customerConfirmation?->package?->package_number ?? '',
                'package_status' => $i->order->quotation->customerConfirmation?->package?->status ?? null,
                'is_package_receipt_locked' => $isPackageReceiptLocked,
                'has_linked_member_paid_history_for_receipt' => $hasLinkedMemberWithPaidHistory,
                'sales_id' => $i->order->quotation->handledBy?->id ?? '-',
                'sales_name' => $i->order->quotation->handledBy?->name ?? '-',
                'description' => $i->description,
                'amount' => $this->formatService->cleanDecimal($i->amount),
                'invoice_date' => $i->invoice_date_formatted,
                'due_date' => $i->due_date_formatted,
                'status' => $i->status,
                'is_refund' => InvoiceStatus::isRefund($i->status),
                'has_receipt' => (int) ($i->receipt_count ?? 0) > 0,
                'receipt_id' => $i->receipt->first()?->id,
                'created_at' => $i->created_at?->translatedFormat('d F Y'),
                'updated_at' => $i->updated_at?->translatedFormat('d F Y'),
            ];
        });
    }

    public function getForFilter(array $filters = [])
    {
        return DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
            Invoice::query(),
            'order.quotation'
        )
            ->when($filters['sales_id'] ?? null, function ($query, $value) {
                $query->whereHas('order.quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('handled_by', $value);
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
                'description' => $data['description'],
                'payment_method' => $data['payment_method'] ?? null,
                'extensions' => $this->normalizeInvoiceExtensions($data['extensions'] ?? []),
                'amount' => $data['amount'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'] ?? InvoiceStatus::Outstanding,
            ]);

            if (! empty($data['items'])) {
                $quotationItemIds = $this->quotationItemService->replaceQuotationItems(
                    $invoice->order->quotation->id,
                    $data['items'],
                    $data['delete_missing_quotation_items'] ?? false,
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
        $i = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
            Invoice::with([
                'quotationItems.taxes',
                'order.quotation.customer.user',
                'order.invoices',
                'invoiceNotes',
            ]),
            'order.quotation'
        )->findOrFail($id);

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
        $overallTotalAmount = (float) ($i->order?->quotation?->total_amount ?? $totalAmount);
        $invoicePaymentProgress = $this->buildInvoicePaymentProgressRows(
            collect($i->order?->invoices ?? []),
            $overallTotalAmount,
        );
        $invoiceTotalAmount = $this->formatService->cleanDecimal((float) collect($invoicePaymentProgress)
            ->sum(fn (array $row): float => (float) ($row['total_amount'] ?? 0)));
        $invoicePaidAmount = $this->formatService->cleanDecimal((float) collect($invoicePaymentProgress)
            ->sum(fn (array $row): float => (float) ($row['amount_paid'] ?? 0)));
        $balanceDueAmount = $this->formatService->cleanDecimal(max(0.0, $invoiceTotalAmount - $invoicePaidAmount));

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
            'quotation_id' => $i->order->quotation->id ?? null,
            'description' => $i->description,
            'amount' => $this->formatService->cleanDecimal($i->amount),
            'payment_plan' => $i->order->quotation->payment_plan,
            'payment_method' => $i->payment_method,
            'placement_fee' => $this->formatService->cleanDecimal($i->order->quotation->total_amount),
            'invoice_date' => $i->invoice_date_formatted,
            'due_date' => $i->due_date_formatted,
            'sales_registration_number' => $i->order->quotation->sales_registration_number,
            'status' => $i->status,
            'is_refund' => InvoiceStatus::isRefund($i->status),
            'subtotal_amount' => $this->formatService->cleanDecimal($subtotalAmount),
            'extension_total_amount' => $this->formatService->cleanDecimal($extensionTotalAmount),
            'total_amount' => $this->formatService->cleanDecimal($totalAmount),
            'invoice_total_amount' => $invoiceTotalAmount,
            'invoice_paid_amount' => $invoicePaidAmount,
            'balance_due_amount' => $balanceDueAmount,
            'invoice_payment_progress' => $invoicePaymentProgress,
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
     * Build invoice payment progress rows showing each payment invoice amount.
     *
     * @param  Collection<int, mixed>  $invoices
     * @return array<int, array{label:string,amount_paid:float,total_amount:float}>
     */
    private function buildInvoicePaymentProgressRows(Collection $invoices, float $totalAmount): array
    {
        $progressInvoices = $invoices
            ->filter(function ($invoice): bool {
                $normalizedStatus = strtolower(trim((string) ($invoice->status ?? '')));

                if ($normalizedStatus === InvoiceStatus::Cancelled || InvoiceStatus::isRefund($invoice->status)) {
                    return false;
                }

                return in_array($normalizedStatus, [
                    InvoiceStatus::Paid,
                    InvoiceStatus::Outstanding,
                    InvoiceStatus::Issued,
                    InvoiceStatus::Overdue,
                ], true);
            })
            ->sortBy(function ($invoice): int {
                return (int) ($invoice->id ?? 0);
            })
            ->values();

        if ($progressInvoices->isEmpty()) {
            return [
                [
                    'label' => 'Pending Payment',
                    'amount_paid' => 0.0,
                    'total_amount' => $this->formatService->cleanDecimal($totalAmount),
                ],
            ];
        }

        return $progressInvoices->map(function ($invoice, int $index): array {
            $invoiceAmount = $this->resolveInvoiceTotalWithExtensions($invoice);
            $normalizedStatus = strtolower(trim((string) ($invoice->status ?? '')));
            $isPaid = $normalizedStatus === InvoiceStatus::Paid;

            return [
                'label' => $this->toOrdinal($index + 1).' Payment',
                'amount_paid' => $isPaid ? $invoiceAmount : 0.0,
                'total_amount' => $invoiceAmount,
            ];
        })->values()->all();
    }

    private function resolveInvoiceTotalWithExtensions($invoice): float
    {
        $baseAmount = $this->formatService->cleanDecimal((float) ($invoice->amount ?? 0));

        if ($baseAmount !== 0.0) {
            // invoice.amount is the source of truth and already includes invoice-level extensions.
            return $baseAmount;
        }

        $extensions = is_array($invoice->extensions ?? null) ? $invoice->extensions : [];

        $extensionsTotal = collect($extensions)->sum(function ($extension): float {
            if (! is_array($extension)) {
                return 0.0;
            }

            return (float) ($extension['amount'] ?? 0);
        });

        return $this->formatService->cleanDecimal($baseAmount + $extensionsTotal);
    }

    private function toOrdinal(int $number): string
    {
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number.'th';
        }

        return match ($number % 10) {
            1 => $number.'st',
            2 => $number.'nd',
            3 => $number.'rd',
            default => $number.'th',
        };
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

                if (! in_array($calculationMode, ['fixed', 'percentage'], true) || $calculationValue === 0.0) {
                    continue;
                }

                $extensionType = $calculationValue < 0 ? 'discount' : 'tax';

                $taxAmount = $calculationMode === 'percentage'
                    ? ($lineAmount * $calculationValue / 100)
                    : $calculationValue;

                $key = implode('|', [
                    (int) ($tax->quotation_extension_master_id ?? 0),
                    strtolower(trim((string) ($tax->name ?? 'Tax'))),
                    $extensionType,
                    $calculationMode,
                    (string) $calculationValue,
                ]);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => null,
                        'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                        'name' => $tax->name ?: 'Tax',
                        'type' => $extensionType,
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
            $query = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
                Invoice::with('order.quotation'),
                'order.quotation'
            );

            // If order_id is provided, scope to that order
            if (! empty($data['order_id'])) {
                $query->where('order_id', $data['order_id']);
            }

            $invoice = $query->findOrFail($id);

            if (InvoiceStatus::isRefund($invoice->status)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Refund invoice cannot be edited.',
                ]);
            }

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
                    $data['delete_missing_quotation_items'] ?? false,
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
        $invoice = Invoice::query()->find($id);

        if (! $invoice) {
            return false;
        }

        if (InvoiceStatus::isRefund($invoice->status)) {
            throw ValidationException::withMessages([
                'invoice' => 'Refund invoice cannot be deleted.',
            ]);
        }

        return $invoice->delete();
    }

    public function isRefundInvoice(int $invoiceId): bool
    {
        $invoice = Invoice::query()->find($invoiceId);

        return $invoice ? InvoiceStatus::isRefund($invoice->status) : false;
    }

    public function recreateReceipt(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $invoiceQuery = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
                Invoice::query()->with('receipt'),
                'order.quotation'
            );

            $invoice = $invoiceQuery->findOrFail($invoiceId);

            if (InvoiceStatus::isRefund($invoice->status)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Refund invoice receipt cannot be recreated.',
                ]);
            }

            if ($invoice->receipt->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice' => 'No receipt found for this invoice.',
                ]);
            }

            foreach ($invoice->receipt as $receipt) {
                $receipt->delete();
            }
        });
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

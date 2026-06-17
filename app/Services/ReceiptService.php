<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\PaymentMethodMaster;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Support\DataScope;
use App\Support\InvoiceStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
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
        return DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
            Receipt::with(['invoice.order.quotation.customer.user', 'invoice.order.quotation.customerConfirmation.enquiry.handledBy:id,name', 'invoice.order.quotation.customerConfirmation.package:id,package_number,name', 'invoice.order.quotation.handledBy:id,name']),
            'invoice.order.quotation'
        )
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('invoice.order.quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('handled_by', $value);
                });
            })
            ->orderBy('receipt_number', 'desc')->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'invoice_id' => $r->invoice_id ?? '-',
                    'invoice_number' => $r->invoice?->invoice_number ?? '-',
                    'invoice_status' => $r->invoice?->status,
                    'invoice_description' => $r->invoice?->description ?? '-',
                    'receipt_number' => $r->receipt_number ?? '-',
                    'customer_id' => $r->invoice?->order->quotation->customer->id ?? '-',
                    'customer_number' => $r->invoice?->order->quotation->customer->customer_number ?? '-',
                    'customer_name' => $r->invoice?->order->quotation->customer->user->name ?? '-',
                    'package_name' => $r->invoice?->order->quotation->customerConfirmation?->package?->name ?? '',
                    'package_number' => $r->invoice?->order->quotation->customerConfirmation?->package?->package_number ?? '',
                    'sales_id' => $r->invoice?->order->quotation->handledBy?->id ?? '-',
                    'sales_name' => $r->invoice?->order->quotation->handledBy?->name ?? '-',
                    'amount' => $this->formatService->cleanDecimal($r->amount),
                    'receipt_date' => $r->receipt_date_formatted,
                    'payment_method' => $r->payment_method,
                    'reference' => $r->reference,
                    'description' => $r->description,
                    'email_sent_at' => $r->email_sent_at ? $r->email_sent_at->toIso8601String() : null,
                    'email_sent_at_formatted' => $r->email_sent_at ? $r->email_sent_at->translatedFormat('d F Y, H:i') : null,
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

        return '';
    }

    public function getPaymentMethodOptions(): array
    {
        return PaymentMethodMaster::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethodMaster $master) => [
                'label' => $master->name,
                'value' => $master->value,
                'is_available_for_refund' => (bool) $master->is_available_for_refund,
            ])
            ->values()
            ->all();
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $invoiceQuery = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
                Invoice::query()->with([
                    'order.quotation.customerConfirmation.package',
                    'quotationItems:id,is_header,customer_confirmation_member_id',
                ]),
                'order.quotation'
            );

            $invoice = $invoiceQuery->findOrFail((int) $data['invoice_id']);

            if (InvoiceStatus::isRefund($invoice->status)) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cannot create receipt for refund invoice.',
                ]);
            }

            $packageStatus = strtolower(trim((string) ($invoice->order?->quotation?->customerConfirmation?->package?->status ?? '')));
            if ($this->shouldBlockReceiptCreationForPackageStatus($invoice, $packageStatus)) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cannot create receipt because the linked package is '.$packageStatus.' and linked invoice members have no payment history.',
                ]);
            }

            $alreadyHasReceipt = Receipt::query()
                ->where('invoice_id', $invoice->id)
                ->exists();

            if ($alreadyHasReceipt) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'A receipt already exists for this invoice.',
                ]);
            }

            $normalizedAmount = $this->resolveNormalizedReceiptAmount($invoice);

            $receipt = Receipt::create([
                'invoice_id' => $invoice->id,
                'receipt_number' => $this->numberingService->ensureNumber(
                    'receipt',
                    $data['receipt_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'amount' => $normalizedAmount,
                'receipt_date' => $data['receipt_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'refund_to' => $data['refund_to'] ?? null,
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
        $r = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
            Receipt::with([
                'invoice.quotationItems.taxes',
                'invoice.quotationItems.confirmationMember.customer.user',
                'invoice.order.quotation.customer.user',
                'invoice.order.invoices.receipt',
                'receiptNotes',
            ]),
            'invoice.order.quotation'
        )->findOrFail($id);

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
                collect(is_array($invoice?->order?->quotation?->extensions ?? null) ? $invoice?->order?->quotation?->extensions : []),
                round($extensionTotalAmount - $itemTaxTotal, 2),
            );

        $extensions = array_values(array_merge($itemTaxExtensions, $quotationExtensions));
        $overallTotalAmount = (float) ($invoice?->order?->quotation?->total_amount ?? $totalAmount);

        $invoicePaymentProgress = $this->buildInvoicePaymentProgressRows(
            collect($invoice?->order?->invoices ?? []),
            $overallTotalAmount,
        );
        $invoiceTotalAmount = $this->formatService->cleanDecimal((float) collect($invoicePaymentProgress)
            ->sum(fn (array $row): float => (float) ($row['total_amount'] ?? 0)));
        $invoicePaidAmount = $this->formatService->cleanDecimal((float) collect($invoicePaymentProgress)
            ->sum(fn (array $row): float => (float) ($row['amount_paid'] ?? 0)));
        $balanceDueAmount = $this->formatService->cleanDecimal(max(0.0, $invoiceTotalAmount - $invoicePaidAmount));
        $isRefundInvoiceReport = InvoiceStatus::isRefund($invoice?->status);
        $amountNotRefunded = $isRefundInvoiceReport
            ? $this->resolveAmountNotRefundedForRefundReport($invoice)
            : null;

        return [
            'id' => $r->id,
            'receipt_number' => $r->receipt_number,
            'receipt_date' => $r->receipt_date_formatted,
            'invoice_id' => $r->invoice_id,
            'invoice_number' => $r->invoice?->invoice_number,
            'order_id' => $r->invoice?->order_id,
            'order_number' => $r->invoice?->order?->order_number,
            'customer_id' => $r->invoice?->order?->quotation?->customer?->id,
            'customer_number' => $r->invoice?->order?->quotation?->customer?->customer_number,
            'customer_name' => $r->invoice?->order?->quotation?->customer?->user?->name,
            'customer_email' => $r->invoice?->order?->quotation?->customer?->user?->email,
            'customer_contact' => $r->invoice?->order?->quotation?->customer?->user?->contact,
            'customer_address' => $r->invoice?->order?->quotation?->customer?->address,
            'amount' => $this->formatService->cleanDecimal($r->amount),
            'payment_method' => $r->payment_method,
            'refund_to' => $r->refund_to,
            'reference' => $r->reference,
            'description' => $r->description,
            'sales_registration_number' => $r->invoice?->order->quotation->sales_registration_number,
            'subtotal_amount' => $this->formatService->cleanDecimal($subtotalAmount),
            'extension_total_amount' => $this->formatService->cleanDecimal($extensionTotalAmount),
            'total_amount' => $this->formatService->cleanDecimal($totalAmount),
            'invoice_total_amount' => $invoiceTotalAmount,
            'invoice_paid_amount' => $invoicePaidAmount,
            'balance_due_amount' => $balanceDueAmount,
            'is_refund_receipt_report' => $isRefundInvoiceReport,
            'amount_not_refunded' => $amountNotRefunded,
            'invoice_payment_progress' => $invoicePaymentProgress,
            'extensions' => $extensions,
            'notes' => $r->receiptNotes->sortBy('sort_order')->values()->toArray(),
            'items' => $r->invoice?->quotationItems->map(fn ($item) => [
                'id' => $item->id,
                'quotation_id' => $item->quotation_id,
                'parent_id' => $item->parent_id,
                'customer_confirmation_member_id' => $item->customer_confirmation_member_id,
                'sharing_plan' => $item->confirmationMember?->sharing_plan,
                'member_name' => $item->confirmationMember?->customer?->user?->name,
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

    private function resolveAmountNotRefundedForRefundReport(?Invoice $invoice): float
    {
        if (! $invoice || ! $invoice->relationLoaded('order') || ! $invoice->order) {
            return 0.0;
        }

        $netAmount = collect($invoice->order->invoices ?? [])
            ->filter(function ($orderInvoice): bool {
                $normalizedStatus = strtolower(trim((string) ($orderInvoice->status ?? '')));

                return $normalizedStatus !== InvoiceStatus::Cancelled;
            })
            ->sum(function ($orderInvoice): float {
                return $this->resolveInvoiceTotalWithExtensions($orderInvoice);
            });

        return $this->formatService->cleanDecimal(max(0.0, (float) $netAmount));
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
            $memberName = $item->confirmationMember?->customer?->user?->name;

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

                $taxName = $tax->name ?: 'Tax';
                if ($memberName) {
                    $taxName = "{$taxName} ({$memberName})";
                }

                $key = implode('|', [
                    (int) ($tax->quotation_extension_master_id ?? 0),
                    strtolower(trim((string) $taxName)),
                    $extensionType,
                    $calculationMode,
                    (string) $calculationValue,
                    $memberName,
                ]);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => null,
                        'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                        'name' => $taxName,
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

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $receiptQuery = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
                Receipt::query(),
                'invoice.order.quotation'
            );

            $receipt = $receiptQuery->findOrFail($id);

            $targetInvoiceId = array_key_exists('invoice_id', $data) && $data['invoice_id'] !== null
                ? (int) $data['invoice_id']
                : (int) $receipt->invoice_id;

            $invoiceQuery = DataScope::applyPaymentCreatorCountryScopeViaQuotationRelation(
                Invoice::query(),
                'order.quotation'
            );

            $targetInvoice = $invoiceQuery->findOrFail($targetInvoiceId);

            if (InvoiceStatus::isRefund($targetInvoice->status) && (int) $targetInvoice->id !== (int) $receipt->invoice_id) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cannot assign receipt to refund invoice.',
                ]);
            }

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

            $amount = $this->resolveNormalizedReceiptAmount($targetInvoice);

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
                'refund_to' => array_key_exists('refund_to', $data) ? $data['refund_to'] : $receipt->refund_to,
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

    private function resolveNormalizedReceiptAmount(Invoice $invoice): float
    {
        return (float) ($this->formatService->cleanDecimal($invoice->amount ?? 0) ?? 0);
    }

    private function shouldBlockReceiptCreationForPackageStatus(
        Invoice $invoice,
        string $packageStatus,
    ): bool {
        if (! in_array($packageStatus, ['full', 'closed', 'completed'], true)) {
            return false;
        }

        $linkedMemberIds = $invoice->quotationItems
            ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($memberId): int => (int) $memberId)
            ->filter(fn (int $memberId): bool => $memberId > 0)
            ->unique()
            ->values();

        if ($linkedMemberIds->isEmpty()) {
            return false;
        }

        $memberStatuses = CustomerConfirmationMember::query()
            ->whereIn('id', $linkedMemberIds->all())
            ->pluck('status', 'id')
            ->mapWithKeys(fn ($status, $memberId): array => [(int) $memberId => strtolower(trim((string) $status))]);

        $hasPaidStatusHistory = $linkedMemberIds->contains(function (int $memberId) use ($memberStatuses): bool {
            $normalizedStatus = (string) ($memberStatuses->get($memberId) ?? '');

            return in_array($normalizedStatus, ['partially_paid', 'fully_paid', 'overpaid'], true);
        });

        if ($hasPaidStatusHistory) {
            return false;
        }

        $memberIdsWithPaidReceiptHistory = QuotationItem::query()
            ->whereIn('customer_confirmation_member_id', $linkedMemberIds->all())
            ->whereHas('invoices.receipt', function ($query): void {
                $query->where('amount', '>', 0);
            })
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($memberId): int => (int) $memberId)
            ->filter(fn (int $memberId): bool => $memberId > 0)
            ->unique();

        return $memberIdsWithPaidReceiptHistory->isEmpty();
    }
}

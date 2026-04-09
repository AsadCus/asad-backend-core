<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Helpers\NumberGenerator;
use App\Models\Order;
use App\Support\DataScope;
use App\Support\InvoiceStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected $invoiceService;

    protected $formatService;

    protected $quotationItemService;

    protected $quotationService;

    protected $numberingService;

    public function __construct(InvoiceService $invoiceService, FormatService $formatService, QuotationItemService $quotationItemService, QuotationService $quotationService, NumberingService $numberingService)
    {
        $this->invoiceService = $invoiceService;
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
        $this->quotationService = $quotationService;
        $this->numberingService = $numberingService;
    }

    public function get()
    {
        return Order::with('quotation')->get();
    }

    public function getForDataTable(array $filters = [])
    {
        return Order::with(['quotation.customer.user', 'quotation.customerConfirmation.package:id,package_number,name', 'quotation.createdBy:id,name', 'invoices.receipt'])
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('quotation', function ($quotationQuery) use ($value) {
                    $quotationQuery->where('created_by', $value);
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
                    'quotation_status' => $o->quotation?->status?->value ?? ($o->quotation?->status ?? null),
                    'customer_id' => $o->quotation->customer->id ?? '-',
                    'customer_number' => $o->quotation->customer->customer_number ?? '-',
                    'customer_name' => $o->quotation->customer->user->name ?? '-',
                    'package_number' => $o->quotation->customerConfirmation?->package?->package_number ?? '',
                    'package_name' => $o->quotation->customerConfirmation?->package?->name ?? '',
                    'sales_id' => $o->quotation->createdBy?->id ?? '-',
                    'sales_name' => $o->quotation->createdBy?->name ?? '-',
                    'payment_plan' => $o->payment_plan,
                    'created_at' => $o->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $o->updated_at?->translatedFormat('d F Y'),
                    'has_receipts' => $hasReceipts,
                    'invoices' => $o->invoices
                        ->values()
                        ->map(function ($i) {
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
                                'sales_id' => $i->order->quotation->createdBy?->id ?? '-',
                                'sales_name' => $i->order->quotation->createdBy?->name ?? '-',
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

    /**
     * @return array{model_key:string, mode:string, format_id:int|null, numbers:array<int, string>, next_increment:int|null}
     */
    public function suggestDraftInvoiceNumbers(int $count): array
    {
        return $this->numberingService->suggestBatchNumbers(
            'invoice',
            max(1, $count),
            null,
            'format',
            [],
        );
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $incomingInvoices = $this->normalizeIncomingInvoices(array_values($data['invoices'] ?? []));
            $incomingInvoices = $this->quotationService->ensureInvoiceExtensionsHaveMasters($incomingInvoices);

            $order = Order::create([
                'order_number' => $this->numberingService->ensureNumber(
                    'order',
                    $data['order_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'quotation_id' => $data['quotation_id'],
                'payment_plan' => $data['payment_plan'],
            ]);

            $this->quotationService->converted($order->quotation->id);

            // Clear quotation extensions since they have been moved to invoices
            $order->quotation->update(['extensions' => []]);

            foreach ($incomingInvoices as $invoice) {
                $this->invoiceService->store([
                    'order_id' => $order->id,
                    'invoice_number' => $invoice['invoice_number'] ?? null,
                    'number_format_id' => isset($invoice['number_format_id']) ? (int) $invoice['number_format_id'] : null,
                    'description' => $invoice['description'],
                    'payment_method' => $invoice['payment_method'] ?? null,
                    'extensions' => $invoice['extensions'] ?? [],
                    'amount' => $invoice['amount'],
                    'invoice_date' => $invoice['invoice_date'],
                    'due_date' => $invoice['due_date'],
                    'status' => $invoice['status'] ?? 'issued',
                    'items' => $invoice['items'] ?? [],
                    'delete_missing_quotation_items' => false,
                ]);
            }

            $order->load('invoices.quotationItems', 'quotation');

            $incomingInvoiceIds = $order->invoices
                ->pluck('id')
                ->map(fn ($invoiceId) => (int) $invoiceId)
                ->values()
                ->all();

            $this->quotationService->syncQuotationItemsToRelevantInvoicesBySortOrder(
                $order->quotation,
                $incomingInvoiceIds,
            );

            $order->load('invoices.quotationItems');

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

            activity()
                ->performedOn($order)
                ->withProperties(['subject_type' => 'Order', 'subject_id' => $order->id ?? null])
                ->log('Order created successfully #'.($order->id ?? null));

            return $order;
        });
    }

    public function getForEditShow($id)
    {
        $query = Order::with([
            'quotation',
            'invoices.receipt',
            'invoices.quotationItems.confirmationMember',
            'invoices.quotationItems.taxes',
        ]);

        if (DataScope::shouldScopeSalesOwnership()) {
            $query->whereHas('quotation', function ($quotationQuery) {
                $quotationQuery->where('created_by', auth()->id());
            });
        }

        $o = $query->findOrFail($id);

        return [
            'id' => $o->id,
            'order_number' => $o->order_number ?? '-',
            'quotation_id' => $o->quotation_id ?? '-',
            'quotation_number' => $o->quotation->quotation_number ?? '-',
            'payment_plan' => $o->payment_plan,
            'invoices' => $o->invoices
                ->values()
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $invoice->order_id,
                    'description' => $invoice->description,
                    'payment_method' => $invoice->payment_method,
                    'extensions' => collect($invoice->extensions ?? [])->map(function ($extension) {
                        return [
                            'id' => $extension['id'] ?? null,
                            'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                            'name' => $extension['name'] ?? '',
                            'type' => $extension['type'] ?? 'discount',
                            'calculation_mode' => $extension['calculation_mode'] ?? 'fixed',
                            'calculation_value' => $this->formatService->cleanDecimal($extension['calculation_value'] ?? null),
                            'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? null),
                            'sort_order' => $extension['sort_order'] ?? null,
                        ];
                    })->values(),
                    'amount' => $this->formatService->cleanDecimal($invoice->amount),
                    'invoice_date' => $invoice->invoice_date_formatted,
                    'due_date' => $invoice->due_date_formatted,
                    'status' => $invoice->status,
                    'is_refund' => InvoiceStatus::isRefund($invoice->status),
                    'has_receipt' => $invoice->receipt->isNotEmpty(),
                    'receipt_id' => $invoice->receipt->first()?->id,
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
                ]),
        ];
    }

    public function update(array $data, int $id): Order
    {
        return DB::transaction(function () use ($data, $id) {
            $orderQuery = Order::with(['invoices.receipt', 'quotation']);

            if (DataScope::shouldScopeSalesOwnership()) {
                $orderQuery->whereHas('quotation', function ($quotationQuery) {
                    $quotationQuery->where('created_by', auth()->id());
                });
            }

            $order = $orderQuery->findOrFail($id);
            $order->quotation()->where('is_locked', false)->update(['is_locked' => true]);

            $editableExistingInvoices = $order->invoices
                ->reject(fn ($invoice) => InvoiceStatus::isRefund($invoice->status))
                ->values();
            $refundExistingInvoices = $order->invoices
                ->filter(fn ($invoice) => InvoiceStatus::isRefund($invoice->status))
                ->values();

            $currentPaymentPlan = strtolower((string) ($order->payment_plan ?? ''));
            $incomingPaymentPlan = strtolower((string) ($data['payment_plan'] ?? $order->payment_plan ?? ''));

            $paidInvoiceCount = $order->invoices
                ->filter(fn ($invoice) => strtolower((string) ($invoice->status ?? '')) === 'paid')
                ->count();

            if (
                $currentPaymentPlan === 'installment'
                && in_array($incomingPaymentPlan, ['full', 'direct'], true)
                && $paidInvoiceCount > 1
            ) {
                throw ValidationException::withMessages([
                    'payment_plan' => 'Cannot change payment plan from installment when more than one installment invoice is already paid.',
                ]);
            }

            $resolvedOrderNumber = array_key_exists('order_number', $data)
                ? $this->numberingService->ensureNumber(
                    'order',
                    $data['order_number'],
                    (int) $order->id,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                )
                : $order->order_number;

            $order->update([
                'order_number' => $resolvedOrderNumber,
                'payment_plan' => $data['payment_plan'],
            ]);

            $incomingInvoiceIds = [];
            $seenInvoiceIds = [];
            $incomingInvoices = $this->normalizeIncomingInvoices(array_values($data['invoices'] ?? []));
            $incomingInvoices = $this->quotationService->ensureInvoiceExtensionsHaveMasters($incomingInvoices);
            $existingInvoicesByNumber = $editableExistingInvoices
                ->filter(fn ($invoice) => ! empty($invoice->invoice_number))
                ->keyBy('invoice_number');
            $refundExistingInvoicesByNumber = $refundExistingInvoices
                ->filter(fn ($invoice) => ! empty($invoice->invoice_number))
                ->keyBy('invoice_number');
            $existingInvoicesByIndex = $editableExistingInvoices
                ->sortBy('id')
                ->values();

            foreach ($incomingInvoices as $index => $invoiceData) {
                $existingInvoiceId = $invoiceData['id'] ?? null;
                $incomingInvoiceNumber = trim((string) ($invoiceData['invoice_number'] ?? ''));
                $isIncomingRefundRow = InvoiceStatus::isRefund($invoiceData['status'] ?? null)
                    || (bool) ($invoiceData['is_refund'] ?? false);

                if ($isIncomingRefundRow) {
                    $matchedRefundInvoiceId = null;

                    if (! empty($existingInvoiceId)) {
                        $candidateInvoiceId = (int) $existingInvoiceId;

                        if ($refundExistingInvoices->contains('id', $candidateInvoiceId)) {
                            $matchedRefundInvoiceId = $candidateInvoiceId;
                        }
                    }

                    if ($matchedRefundInvoiceId === null && $incomingInvoiceNumber !== '') {
                        $matchedRefundInvoiceId = (int) ($refundExistingInvoicesByNumber
                            ->get($incomingInvoiceNumber)
                            ?->id ?? 0);

                        if ($matchedRefundInvoiceId <= 0) {
                            $matchedRefundInvoiceId = null;
                        }
                    }

                    if ($matchedRefundInvoiceId === null) {
                        throw ValidationException::withMessages([
                            "invoices.{$index}.invoice_number" => 'Refund invoice row is invalid. Please refresh and try again.',
                        ]);
                    }

                    if (in_array($matchedRefundInvoiceId, $seenInvoiceIds, true)) {
                        throw ValidationException::withMessages([
                            'invoices' => 'Duplicate invoice rows detected. Please refresh and try again.',
                        ]);
                    }

                    $seenInvoiceIds[] = $matchedRefundInvoiceId;

                    continue;
                }

                if (! empty($existingInvoiceId)) {
                    $existingInvoiceId = (int) $existingInvoiceId;

                    if ($refundExistingInvoices->contains('id', $existingInvoiceId)) {
                        if (in_array($existingInvoiceId, $seenInvoiceIds, true)) {
                            throw ValidationException::withMessages([
                                'invoices' => 'Duplicate invoice rows detected. Please refresh and try again.',
                            ]);
                        }

                        $seenInvoiceIds[] = $existingInvoiceId;

                        continue;
                    }
                }

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

                    if (! $editableExistingInvoices->contains('id', $existingInvoiceId)) {
                        throw ValidationException::withMessages([
                            'invoices' => 'One or more invoices do not belong to this order.',
                        ]);
                    }

                    try {
                        $invoice = $this->invoiceService->update(
                            [
                                ...$invoiceData,
                                'invoice_number' => $invoiceData['invoice_number'] ?? null,
                                'number_format_id' => isset($invoiceData['number_format_id']) ? (int) $invoiceData['number_format_id'] : null,
                                'payment_method' => $invoiceData['payment_method'] ?? null,
                                'extensions' => $invoiceData['extensions'] ?? [],
                                'delete_missing_quotation_items' => false,
                            ],
                            $existingInvoiceId
                        );
                    } catch (ValidationException $exception) {
                        $this->rethrowInvoiceValidationExceptionForRow($exception, $index);
                    }

                    $seenInvoiceIds[] = $existingInvoiceId;
                } else {
                    try {
                        $invoice = $this->invoiceService->store([
                            ...$invoiceData,
                            'invoice_number' => $invoiceData['invoice_number'] ?? null,
                            'number_format_id' => isset($invoiceData['number_format_id']) ? (int) $invoiceData['number_format_id'] : null,
                            'payment_method' => $invoiceData['payment_method'] ?? null,
                            'extensions' => $invoiceData['extensions'] ?? [],
                            'order_id' => $order->id,
                            'delete_missing_quotation_items' => false,
                        ]);
                    } catch (ValidationException $exception) {
                        $this->rethrowInvoiceValidationExceptionForRow($exception, $index);
                    }
                }

                $incomingInvoiceIds[] = $invoice->id;
            }

            $removableInvoices = $order->invoices()
                ->whereNotIn('id', $incomingInvoiceIds)
                ->where('status', '!=', InvoiceStatus::Refund)
                ->get();

            $paidRemovableInvoices = $removableInvoices
                ->filter(fn ($invoice) => strtolower((string) ($invoice->status ?? '')) === 'paid')
                ->values();

            if ($paidRemovableInvoices->isNotEmpty()) {
                $invoiceNumbers = $paidRemovableInvoices
                    ->pluck('invoice_number')
                    ->filter()
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'invoices' => $invoiceNumbers
                        ? "Cannot remove paid invoice(s): {$invoiceNumbers}."
                        : 'Cannot remove paid invoice(s).',
                ]);
            }

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
                array_values(array_filter(
                    $incomingInvoiceIds,
                    fn ($invoiceId) => ! $refundExistingInvoices->contains('id', (int) $invoiceId),
                )),
            );

            $order->load('invoices.quotationItems');

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

    private function normalizeIncomingInvoices(array $incomingInvoices): array
    {
        $seenItemIds = [];

        return array_map(function (array $invoice) use (&$seenItemIds) {
            $rawItems = array_values($invoice['items'] ?? []);
            $normalizedItems = [];
            $seenFingerprints = [];

            foreach ($rawItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemId = isset($item['id']) && is_numeric($item['id'])
                    ? (int) $item['id']
                    : null;

                if ($itemId && isset($seenItemIds[$itemId])) {
                    if (! empty($item['is_header'])) {
                        $item['id'] = null;
                        $itemId = null;
                    } else {
                        continue;
                    }
                }

                if (! $itemId) {
                    $taxFingerprint = collect($item['taxes'] ?? [])
                        ->filter(fn ($tax) => is_array($tax))
                        ->map(function (array $tax) {
                            return implode(':', [
                                (string) ($tax['quotation_extension_master_id'] ?? ''),
                                strtolower(trim((string) ($tax['name'] ?? ''))),
                                strtolower(trim((string) ($tax['calculation_mode'] ?? ''))),
                                (string) ($tax['calculation_value'] ?? ''),
                                (string) ($tax['sort_order'] ?? ''),
                            ]);
                        })
                        ->values()
                        ->implode(',');

                    $fingerprint = implode('|', [
                        (string) ($item['parent_id'] ?? ''),
                        strtolower(trim((string) ($item['parent_key'] ?? ''))),
                        (string) ($item['customer_confirmation_member_id'] ?? ''),
                        strtolower(trim((string) ($item['description'] ?? ''))),
                        (string) ((int) (! empty($item['is_header']))),
                        (string) ($item['quantity'] ?? ''),
                        (string) ($item['rate'] ?? ''),
                        $taxFingerprint,
                        (string) ($item['sort_order'] ?? ''),
                    ]);

                    if (isset($seenFingerprints[$fingerprint])) {
                        continue;
                    }

                    $seenFingerprints[$fingerprint] = true;
                } else {
                    $seenItemIds[$itemId] = true;
                }

                $normalizedItems[] = $item;
            }

            $invoice['items'] = array_values($normalizedItems);

            return $invoice;
        }, $incomingInvoices);
    }

    private function rethrowInvoiceValidationExceptionForRow(ValidationException $exception, int $rowIndex): void
    {
        $mappedErrors = [];

        foreach ($exception->errors() as $key => $messages) {
            $targetKey = in_array($key, ['invoice_number', 'number_format_id'], true)
                ? "invoices.{$rowIndex}.{$key}"
                : $key;

            $mappedErrors[$targetKey] = $messages;
        }

        throw ValidationException::withMessages($mappedErrors);
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

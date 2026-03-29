<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Helpers\FormatService;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\FinancialTransaction;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\PaymentMethodMaster;
use App\Models\Quotation;
use App\Models\QuotationExtensionMaster;
use App\Models\QuotationItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    protected $formatService;

    protected $quotationItemService;

    protected $paymentStatusService;

    protected $numberingService;

    public function __construct(FormatService $formatService, QuotationItemService $quotationItemService, PaymentStatusService $paymentStatusService, NumberingService $numberingService)
    {
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
        $this->paymentStatusService = $paymentStatusService;
        $this->numberingService = $numberingService;
    }

    public function getForDataTable(array $filters = [])
    {
        $data = Quotation::with(['customer.user', 'customer.handledBy', 'customerConfirmation.enquiry.handledBy:id,name', 'quotationItems', 'order'])->withTrashed()
            ->when($filters['sales_id'] ?? null, function ($q, $value) {
                $q->whereHas('customerConfirmation.enquiry', function ($enquiryQuery) use ($value) {
                    $enquiryQuery->where('handled_by', $value);
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
                    'sales_id' => $q->customerConfirmation?->enquiry?->handledBy?->id ?? '-',
                    'sales_name' => $q->customerConfirmation?->enquiry?->handledBy?->name ?? '-',
                    'description' => $q->description ?? '-',
                    'quotation_date' => $q->quotation_date_formatted,
                    'expiry_date' => $q->expiry_date_formatted,
                    'items_count' => $q->quotationItems->count(),
                    'total_amount' => $this->formatService->cleanDecimal($q->total_amount),
                    'payment_plan' => $q->payment_plan_label,
                    'status' => $q->status?->value,
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
        $data = Quotation::select('id', 'quotation_number')->whereIn('status', [
            QuotationStatus::Ready->value,
            QuotationStatus::Accepted->value,
        ])->whereDoesntHave('order')->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->quotation_number,
            ];
        });

        return $data;
    }

    public function getPaymentMethodOptions(): array
    {
        $methods = PaymentMethodMaster::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethodMaster $master) => [
                'label' => $master->name,
                'value' => $master->value,
            ])
            ->values()
            ->all();

        if (empty($methods)) {
            return [];
        }

        return $methods;
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

    public function getPaymentMethodMastersForMasterPage(): array
    {
        return PaymentMethodMaster::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (PaymentMethodMaster $master) {
                return [
                    'id' => $master->id,
                    'name' => $master->name,
                    'value' => $master->value,
                    'is_active' => (bool) $master->is_active,
                    'is_default' => (bool) $master->is_default,
                    'sort_order' => (int) ($master->sort_order ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function storePaymentMethodMasters(array $paymentMethods): void
    {
        DB::transaction(function () use ($paymentMethods) {
            $sanitized = collect($paymentMethods)
                ->filter(fn ($row) => is_array($row))
                ->map(function (array $row, int $index) {
                    $name = trim((string) ($row['name'] ?? ''));
                    $value = Str::of($name)->lower()->slug('_')->value();

                    return [
                        'id' => ! empty($row['id']) ? (int) $row['id'] : null,
                        'name' => $name,
                        'value' => $value,
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'is_default' => (bool) ($row['is_default'] ?? false),
                        'sort_order' => (int) ($row['sort_order'] ?? ($index + 1)),
                    ];
                })
                ->filter(fn ($row) => $row['name'] !== '' && $row['value'] !== '')
                ->unique('value')
                ->values();

            $defaultIndex = $sanitized->search(
                fn ($row) => $row['is_active'] && $row['is_default'],
            );

            if ($defaultIndex === false) {
                $defaultIndex = $sanitized->search(
                    fn ($row) => $row['is_active'],
                );
            }

            $sanitized = $sanitized
                ->values()
                ->map(function (array $row, int $index) use ($defaultIndex) {
                    return [
                        ...$row,
                        'is_default' => $defaultIndex !== false
                            ? $index === (int) $defaultIndex
                            : false,
                    ];
                })
                ->values();

            $incomingIds = $sanitized
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            PaymentMethodMaster::query()
                ->when(! empty($incomingIds), fn ($query) => $query->whereNotIn('id', $incomingIds))
                ->when(empty($incomingIds), fn ($query) => $query)
                ->delete();

            foreach ($sanitized as $row) {
                if ($row['id']) {
                    PaymentMethodMaster::query()
                        ->where('id', $row['id'])
                        ->update([
                            'name' => $row['name'],
                            'value' => $row['value'],
                            'is_active' => $row['is_active'],
                            'is_default' => $row['is_default'],
                            'sort_order' => $row['sort_order'],
                        ]);

                    continue;
                }

                PaymentMethodMaster::query()->create([
                    'name' => $row['name'],
                    'value' => $row['value'],
                    'is_active' => $row['is_active'],
                    'is_default' => $row['is_default'],
                    'sort_order' => $row['sort_order'],
                ]);
            }
        });
    }

    public function getSupportedPaymentMethodValues(): array
    {
        return collect($this->getPaymentMethodOptions())
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
    }

    public function getExtensionMastersForMasterPage(): array
    {
        $supportedPaymentMethods = $this->getSupportedPaymentMethodValues();

        return QuotationExtensionMaster::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (QuotationExtensionMaster $master) use ($supportedPaymentMethods) {
                return [
                    'id' => $master->id,
                    'name' => $master->name,
                    'type' => $master->type,
                    'calculation_mode' => $master->calculation_mode,
                    'calculation_value' => $this->formatService->cleanDecimal($master->calculation_value),
                    'payment_methods' => array_values(array_filter(
                        (array) ($master->payment_methods ?? []),
                        fn ($method) => in_array((string) $method, $supportedPaymentMethods, true)
                    )),
                    'is_active' => (bool) $master->is_active,
                    'sort_order' => (int) ($master->sort_order ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function storeExtensionMasters(array $extensions): void
    {
        $supportedPaymentMethods = $this->getSupportedPaymentMethodValues();

        DB::transaction(function () use ($extensions, $supportedPaymentMethods) {
            $sanitized = collect($extensions)
                ->filter(fn ($row) => is_array($row))
                ->map(function (array $row, int $index) use ($supportedPaymentMethods) {
                    $type = (string) ($row['type'] ?? 'discount');

                    $paymentMethods = collect((array) ($row['payment_methods'] ?? []))
                        ->map(fn ($method) => (string) $method)
                        ->filter(fn ($method) => in_array($method, $supportedPaymentMethods, true))
                        ->unique()
                        ->values()
                        ->all();

                    if (in_array($type, ['discount', 'tax'], true)) {
                        $paymentMethods = [];
                    }

                    return [
                        'id' => ! empty($row['id']) ? (int) $row['id'] : null,
                        'name' => trim((string) ($row['name'] ?? '')),
                        'type' => $type,
                        'calculation_mode' => (string) ($row['calculation_mode'] ?? 'fixed'),
                        'calculation_value' => $this->formatService->cleanDecimal($row['calculation_value'] ?? 0) ?? 0,
                        'payment_methods' => $paymentMethods,
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'sort_order' => (int) ($row['sort_order'] ?? ($index + 1)),
                    ];
                })
                ->filter(fn ($row) => $row['name'] !== '')
                ->values();

            $incomingIds = $sanitized
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            QuotationExtensionMaster::query()
                ->when(! empty($incomingIds), fn ($query) => $query->whereNotIn('id', $incomingIds))
                ->when(empty($incomingIds), fn ($query) => $query)
                ->delete();

            foreach ($sanitized as $row) {
                if ($row['id']) {
                    QuotationExtensionMaster::query()
                        ->where('id', $row['id'])
                        ->update([
                            'name' => $row['name'],
                            'type' => $row['type'],
                            'calculation_mode' => $row['calculation_mode'],
                            'calculation_value' => $row['calculation_value'],
                            'payment_methods' => $row['payment_methods'],
                            'is_active' => $row['is_active'],
                            'sort_order' => $row['sort_order'],
                        ]);

                    continue;
                }

                QuotationExtensionMaster::query()->create([
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'calculation_mode' => $row['calculation_mode'],
                    'calculation_value' => $row['calculation_value'],
                    'payment_methods' => $row['payment_methods'],
                    'is_active' => $row['is_active'],
                    'sort_order' => $row['sort_order'],
                ]);
            }
        });
    }

    public function getDefaultExtensionsForCreate(?string $paymentMethod = null): array
    {
        $discountIncluded = false;

        return QuotationExtensionMaster::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(function (QuotationExtensionMaster $master) use ($paymentMethod): bool {
                $methods = collect((array) ($master->payment_methods ?? []))
                    ->map(fn ($method) => (string) $method)
                    ->filter(fn ($method) => $method !== '')
                    ->values()
                    ->all();

                if (empty($methods)) {
                    return true;
                }

                if (! $paymentMethod) {
                    return false;
                }

                return in_array((string) $paymentMethod, $methods, true);
            })
            ->filter(function (QuotationExtensionMaster $master) use (&$discountIncluded): bool {
                if ($master->type === 'tax') {
                    return false;
                }

                if ($master->type === 'discount') {
                    if ($discountIncluded) {
                        return false;
                    }

                    $discountIncluded = true;
                }

                return true;
            })
            ->values()
            ->map(function (QuotationExtensionMaster $master, int $index) {
                $amount = (float) ($master->calculation_mode === 'fixed'
                    ? ($master->calculation_value ?? 0)
                    : 0);

                return [
                    'id' => null,
                    'quotation_extension_master_id' => $master->id,
                    'name' => $master->name,
                    'type' => $master->type,
                    'calculation_mode' => $master->calculation_mode,
                    'calculation_value' => $this->formatService->cleanDecimal($master->calculation_value),
                    'amount' => $this->formatService->cleanDecimal($amount),
                    'sort_order' => $index + 1,
                ];
            })
            ->all();
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
                'quotation_number' => $this->numberingService->ensureNumber(
                    'quotation',
                    $data['quotation_number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_confirmation_id' => $data['customer_confirmation_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? QuotationStatus::Draft->value,
                'reason' => $data['reason'] ?? null,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->quotationItemService->storeQuotationItems($quotation->id, $data['items']);
            }

            if (array_key_exists('extensions', $data) && is_array($data['extensions'])) {
                $this->syncQuotationExtensions($quotation, $data['extensions']);
            }

            $linkedMemberIds = $quotation->quotationItems()
                ->whereNotNull('customer_confirmation_member_id')
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->syncLinkedMemberStatuses($linkedMemberIds, []);

            activity()
                ->performedOn($quotation)
                ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id ?? null])
                ->log('Quotation created successfully #'.($quotation->id ?? null));

            return $quotation;
        });
    }

    public function getForEditShow($id): array
    {
        $quotation = Quotation::with([
            'customer.user',
            'quotationItems.confirmationMember',
            'quotationItems.taxes',
            'customerConfirmation.package',
            'order.invoices',
        ])->findOrFail($id);

        $invoiceExtensions = $this->buildInvoiceExtensionsForQuotationDisplay($quotation);

        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_confirmation_id' => $quotation->customer_confirmation_id,
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
            'subtotal_amount' => $this->formatService->cleanDecimal($quotation->item_subtotal_amount),
            'extension_total_amount' => $this->formatService->cleanDecimal($quotation->extension_total_amount),
            'total_amount' => $this->formatService->cleanDecimal($quotation->total_amount),
            'payment_plan' => $quotation->payment_plan,
            'status' => $quotation->status?->value,
            'reason' => $quotation->reason,
            'have_invoices' => $quotation->order?->invoices()->exists() ?? false,
            'package_name' => $quotation->customerConfirmation?->package?->name,
            'package_departure_date' => $quotation->customerConfirmation?->package?->departure_date?->format('Y-m-d'),
            'package_price_single' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_single),
            'package_price_double' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_double),
            'package_price_triple' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_triple),
            'package_price_quad' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_quad),
            'sales_registration_number' => $quotation->sales_registration_number,
            'model' => 'quotation',
            'notes' => $quotation->quotationNotes->sortBy('sort_order')->values()->toArray(),
            'extensions' => collect(is_array($quotation->extensions) ? $quotation->extensions : [])->sortBy('sort_order')->values()->map(function (array $extension) {
                return [
                    'id' => $extension['id'] ?? null,
                    'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                    'name' => $extension['name'] ?? null,
                    'type' => $extension['type'] ?? null,
                    'calculation_mode' => $extension['calculation_mode'] ?? null,
                    'calculation_value' => $this->formatService->cleanDecimal($extension['calculation_value'] ?? null),
                    'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? 0),
                    'sort_order' => $extension['sort_order'] ?? null,
                ];
            })->values()->toArray(),
            'invoice_extensions' => $invoiceExtensions,
            'items' => $quotation->quotationItems->sortBy('sort_order')->map(function (QuotationItem $it) {
                return [
                    'id' => $it->id,
                    'parent_id' => $it->parent_id,
                    'customer_confirmation_member_id' => $it->customer_confirmation_member_id,
                    'sharing_plan' => $it->confirmationMember?->sharing_plan,
                    'description' => $it->description,
                    'is_header' => $it->is_header,
                    'is_optional' => $it->is_optional,
                    'quantity' => $this->formatService->cleanDecimal($it->quantity),
                    'rate' => $this->formatService->cleanDecimal($it->rate),
                    'taxes' => $it->taxes->map(function ($tax) {
                        return [
                            'id' => $tax->id,
                            'quotation_item_id' => $tax->quotation_item_id,
                            'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                            'name' => $tax->name,
                            'calculation_mode' => $tax->calculation_mode,
                            'calculation_value' => $this->formatService->cleanDecimal($tax->calculation_value),
                            'sort_order' => $tax->sort_order,
                        ];
                    })->values()->toArray(),
                    'sort_order' => $it->sort_order,
                ];
            })->values()->toArray(),
        ];
    }

    public function update(array $data, int $id): Quotation
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::findOrFail($id);
            $requestedStatus = strtolower((string) ($data['status'] ?? $quotation->status?->value ?? ''));

            $previousLinkedMemberIds = $quotation->quotationItems()
                ->whereNotNull('customer_confirmation_member_id')
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($memberId) => (int) $memberId)
                ->unique()
                ->values()
                ->all();

            if (! empty($data['quotation_date'])) {
                $data['quotation_date'] = Carbon::parse($data['quotation_date'])->format('Y-m-d');
            }
            if (! empty($data['expiry_date'])) {
                $data['expiry_date'] = Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }

            $quotation->update([
                'quotation_number' => array_key_exists('quotation_number', $data)
                    ? $this->numberingService->ensureNumber(
                        'quotation',
                        $data['quotation_number'],
                        (int) $quotation->id,
                        isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                    )
                    : $quotation->quotation_number,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_confirmation_id' => $data['customer_confirmation_id'] ?? $quotation->customer_confirmation_id,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $quotation->status?->value,
                'reason' => $data['reason'] ?? $quotation->reason,
            ]);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->quotationItemService->replaceQuotationItems($quotation->id, $data['items']);
            }

            $hasOrderInvoices = $quotation->order()
                ->whereHas('invoices')
                ->exists();

            if (
                ! $hasOrderInvoices
                && array_key_exists('extensions', $data)
                && is_array($data['extensions'])
            ) {
                $this->syncQuotationExtensions($quotation, $data['extensions']);
            }

            $currentLinkedMemberIds = $quotation->quotationItems()
                ->whereNotNull('customer_confirmation_member_id')
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($memberId) => (int) $memberId)
                ->unique()
                ->values()
                ->all();

            $removedLinkedMemberIds = array_values(array_diff(
                $previousLinkedMemberIds,
                $currentLinkedMemberIds
            ));

            $this->syncLinkedMemberStatuses($currentLinkedMemberIds, $removedLinkedMemberIds);

            $this->syncQuotationItemsToRelevantInvoicesBySortOrder($quotation);
            $this->syncConvertedOrderInvoiceAndReceiptAmounts($quotation);

            if ($requestedStatus === QuotationStatus::Rejected->value) {
                $linkedMemberIds = $this->getLinkedMemberIds($quotation);

                if (! empty($linkedMemberIds)) {
                    CustomerConfirmationMember::query()
                        ->whereIn('id', $linkedMemberIds)
                        ->where('status', '!=', 'cancelled')
                        ->update(['status' => 'pending_payment']);
                }

                $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
                $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);
            }

            if ($requestedStatus === QuotationStatus::Expired->value) {
                $linkedMemberIds = $this->getLinkedMemberIds($quotation);

                $this->resetLinkedMembersToDraft($linkedMemberIds);
                $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
                $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);
            }

            if ($requestedStatus === QuotationStatus::Cancelled->value) {
                $linkedMemberIds = $this->getLinkedMemberIds($quotation);

                $this->resetLinkedMembersToDraft($linkedMemberIds);
                $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
                $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);
            }

            return $quotation->fresh();
        });
    }

    public function syncQuotationItemsToRelevantInvoicesBySortOrder(
        Quotation $quotation,
        ?array $preferredInvoiceOrderIds = null
    ): void {
        $order = $quotation->order()->with('invoices.quotationItems')->first();

        if (! $order || $order->invoices->isEmpty()) {
            return;
        }

        $orderedInvoices = $this->orderInvoicesForSync($order->invoices, $preferredInvoiceOrderIds);
        $invoiceIds = $orderedInvoices
            ->pluck('id')
            ->map(fn ($invoiceId) => (int) $invoiceId)
            ->values()
            ->all();

        if (empty($invoiceIds)) {
            return;
        }

        $invoicePosition = array_flip($invoiceIds);
        $invoicesById = $orderedInvoices->keyBy('id');

        $currentInvoiceByItemId = [];
        foreach ($orderedInvoices as $invoice) {
            foreach ($invoice->quotationItems as $item) {
                if ((int) $item->quotation_id !== (int) $quotation->id) {
                    continue;
                }

                $itemId = (int) $item->id;
                if (! isset($currentInvoiceByItemId[$itemId])) {
                    $currentInvoiceByItemId[$itemId] = (int) $invoice->id;
                }
            }
        }

        $quotationItems = $quotation->quotationItems()
            ->select('id', 'parent_id', 'sort_order', 'description', 'customer_confirmation_member_id', 'is_header')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($quotationItems->isEmpty()) {
            foreach ($orderedInvoices as $invoice) {
                $invoice->quotationItems()->sync([]);
            }

            return;
        }

        $itemsById = $quotationItems->keyBy('id');
        $toInstallmentBaseDescription = static function (?string $description): string {
            $value = strtolower(trim((string) ($description ?? '')));

            return preg_replace('/\s*\((deposit|50%|balance)\)$/i', '', $value) ?? $value;
        };

        $hasInstallmentSuffix = static function (?string $description): bool {
            return preg_match('/\((deposit|50%|balance)\)\s*$/i', (string) ($description ?? '')) === 1;
        };

        $linkedInstallmentSignatures = [];
        foreach ($quotationItems as $item) {
            $itemId = (int) $item->id;

            if (! isset($currentInvoiceByItemId[$itemId])) {
                continue;
            }

            if ((bool) ($item->is_header ?? false)) {
                continue;
            }

            $description = (string) ($item->description ?? '');
            if (! $hasInstallmentSuffix($description)) {
                continue;
            }

            $signature = implode('|', [
                (int) ($item->customer_confirmation_member_id ?? 0),
                $toInstallmentBaseDescription($description),
            ]);

            $linkedInstallmentSignatures[$signature] = true;
        }

        $childrenByParentId = $quotationItems
            ->filter(fn ($item) => ! empty($item->parent_id))
            ->groupBy('parent_id')
            ->map(function (Collection $children) {
                return $children
                    ->sort(function ($left, $right) {
                        $leftSort = (int) ($left->sort_order ?? 0);
                        $rightSort = (int) ($right->sort_order ?? 0);

                        if ($leftSort === $rightSort) {
                            return (int) $left->id <=> (int) $right->id;
                        }

                        return $leftSort <=> $rightSort;
                    })
                    ->values();
            });

        $orderedItemIds = [];
        $visited = [];

        $appendTree = function ($item) use (&$appendTree, &$orderedItemIds, &$visited, $childrenByParentId): void {
            $itemId = (int) $item->id;

            if (isset($visited[$itemId])) {
                return;
            }

            $visited[$itemId] = true;
            $orderedItemIds[] = $itemId;

            $children = $childrenByParentId->get($itemId, collect());
            foreach ($children as $child) {
                $appendTree($child);
            }
        };

        $rootItems = $quotationItems
            ->filter(function ($item) use ($itemsById) {
                if (empty($item->parent_id)) {
                    return true;
                }

                return ! $itemsById->has((int) $item->parent_id);
            })
            ->sort(function ($left, $right) {
                $leftSort = (int) ($left->sort_order ?? 0);
                $rightSort = (int) ($right->sort_order ?? 0);

                if ($leftSort === $rightSort) {
                    return (int) $left->id <=> (int) $right->id;
                }

                return $leftSort <=> $rightSort;
            })
            ->values();

        foreach ($rootItems as $rootItem) {
            $appendTree($rootItem);
        }

        foreach ($quotationItems as $item) {
            $itemId = (int) $item->id;
            if (! isset($visited[$itemId])) {
                $appendTree($item);
            }
        }

        $invoiceByItemId = [];
        $defaultInvoiceId = (int) $invoiceIds[0];
        $lastInvoiceId = $defaultInvoiceId;
        $strictToCurrentLinks = ! empty($preferredInvoiceOrderIds);

        foreach ($orderedItemIds as $itemId) {
            $item = $itemsById->get($itemId);
            if (! $item) {
                continue;
            }

            if ($strictToCurrentLinks) {
                $parentId = ! empty($item->parent_id) ? (int) $item->parent_id : null;
                $hasCurrentLink = isset($currentInvoiceByItemId[$itemId]);

                if ($parentId && isset($invoiceByItemId[$parentId])) {
                    if (! $hasCurrentLink) {
                        $candidateSignature = implode('|', [
                            (int) ($item->customer_confirmation_member_id ?? 0),
                            $toInstallmentBaseDescription((string) ($item->description ?? '')),
                        ]);

                        $isHeaderItem = (bool) ($item->is_header ?? false);
                        if (! $isHeaderItem && isset($linkedInstallmentSignatures[$candidateSignature])) {
                            continue;
                        }
                    }

                    $candidateInvoiceId = (int) $invoiceByItemId[$parentId];
                    if (! isset($invoicePosition[$candidateInvoiceId])) {
                        continue;
                    }

                    $invoiceByItemId[$itemId] = $candidateInvoiceId;
                    $lastInvoiceId = $candidateInvoiceId;

                    continue;
                }

                if (! $hasCurrentLink) {
                    continue;
                }

                $candidateInvoiceId = (int) $currentInvoiceByItemId[$itemId];
                if (! isset($invoicePosition[$candidateInvoiceId])) {
                    continue;
                }

                $invoiceByItemId[$itemId] = $candidateInvoiceId;
                $lastInvoiceId = $candidateInvoiceId;

                continue;
            }

            $parentId = ! empty($item->parent_id) ? (int) $item->parent_id : null;
            $targetInvoiceId = null;

            if ($parentId && isset($invoiceByItemId[$parentId])) {
                $targetInvoiceId = (int) $invoiceByItemId[$parentId];
            } elseif (isset($currentInvoiceByItemId[$itemId])) {
                $candidateInvoiceId = (int) $currentInvoiceByItemId[$itemId];
                if (isset($invoicePosition[$candidateInvoiceId])) {
                    $targetInvoiceId = $candidateInvoiceId;
                }
            }

            if (! $targetInvoiceId) {
                if ($strictToCurrentLinks) {
                    continue;
                }

                $targetInvoiceId = $lastInvoiceId;
            }

            if (! isset($invoicePosition[$targetInvoiceId])) {
                if ($strictToCurrentLinks) {
                    continue;
                }

                $targetInvoiceId = $defaultInvoiceId;
            }

            $invoiceByItemId[$itemId] = $targetInvoiceId;
            $lastInvoiceId = $targetInvoiceId;
        }

        $invoiceItemIds = [];
        foreach ($invoiceIds as $invoiceId) {
            $invoiceItemIds[$invoiceId] = [];
        }

        foreach ($orderedItemIds as $itemId) {
            if (! isset($invoiceByItemId[$itemId])) {
                continue;
            }

            $assignedInvoiceId = $invoiceByItemId[$itemId];

            if (! isset($invoiceItemIds[$assignedInvoiceId])) {
                $invoiceItemIds[$assignedInvoiceId] = [];
            }

            $invoiceItemIds[$assignedInvoiceId][] = $itemId;
        }

        foreach (array_values($invoiceIds) as $invoiceIndex => $invoiceId) {
            $itemIds = $invoiceItemIds[$invoiceId] ?? [];
            foreach (array_values($itemIds) as $itemIndex => $itemId) {
                $encodedSortOrder = (($invoiceIndex + 1) * 1000) + ($itemIndex + 1);

                QuotationItem::query()
                    ->where('id', $itemId)
                    ->where('quotation_id', $quotation->id)
                    ->update(['sort_order' => $encodedSortOrder]);
            }
        }

        foreach ($invoiceItemIds as $invoiceId => $itemIds) {
            $invoice = $invoicesById->get((int) $invoiceId);
            if (! $invoice) {
                continue;
            }

            $invoice->quotationItems()->sync($itemIds);
        }
    }

    private function orderInvoicesForSync(Collection $invoices, ?array $preferredInvoiceOrderIds): Collection
    {
        if (! empty($preferredInvoiceOrderIds)) {
            $positions = [];
            foreach (array_values($preferredInvoiceOrderIds) as $index => $invoiceId) {
                $positions[(int) $invoiceId] = $index;
            }

            return $invoices
                ->sort(function ($left, $right) use ($positions) {
                    $leftId = (int) $left->id;
                    $rightId = (int) $right->id;
                    $leftPos = $positions[$leftId] ?? PHP_INT_MAX;
                    $rightPos = $positions[$rightId] ?? PHP_INT_MAX;

                    if ($leftPos === $rightPos) {
                        return $leftId <=> $rightId;
                    }

                    return $leftPos <=> $rightPos;
                })
                ->values();
        }

        return $invoices
            ->sort(function ($left, $right) {
                $leftDueDate = $left->due_date ? $left->due_date->timestamp : PHP_INT_MAX;
                $rightDueDate = $right->due_date ? $right->due_date->timestamp : PHP_INT_MAX;

                if ($leftDueDate === $rightDueDate) {
                    return (int) $left->id <=> (int) $right->id;
                }

                return $leftDueDate <=> $rightDueDate;
            })
            ->values();
    }

    private function syncLinkedMemberStatuses(array $currentMemberIds, array $removedMemberIds): void
    {
        if (! empty($currentMemberIds)) {
            CustomerConfirmationMember::query()
                ->whereIn('id', $currentMemberIds)
                ->whereIn('status', ['pending_payment'])
                ->update(['status' => 'pending_payment']);
        }

        if (empty($removedMemberIds)) {
            return;
        }

        $stillLinkedMemberIds = QuotationItem::query()
            ->whereIn('customer_confirmation_member_id', $removedMemberIds)
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($memberId) => (int) $memberId)
            ->unique()
            ->values()
            ->all();

        $toRevertMemberIds = array_values(array_diff($removedMemberIds, $stillLinkedMemberIds));

        if (empty($toRevertMemberIds)) {
            return;
        }

        CustomerConfirmationMember::query()
            ->whereIn('id', $toRevertMemberIds)
            ->where('status', 'pending_payment')
            ->update(['status' => 'pending_payment']);
    }

    public function getCustomerConfirmationCreateOptions(?int $includeConfirmationId = null): array
    {
        return CustomerConfirmation::query()
            ->with(['members.customer.user', 'members.quotationItems'])
            ->where(function ($query) use ($includeConfirmationId) {
                $query->whereHas('members', function ($memberQuery) {
                    $memberQuery->whereIn('status', ['pending_payment'])
                        ->whereDoesntHave('quotationItems');
                });

                if ($includeConfirmationId) {
                    $query->orWhere('id', $includeConfirmationId);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (CustomerConfirmation $confirmation) {
                $leader = $confirmation->members->firstWhere('is_leader', true)
                    ?? $confirmation->members->first();

                return [
                    'value' => $confirmation->id,
                    'label' => 'CC-'.$confirmation->id.' - '.($leader?->customer?->user?->name ?? 'Unknown'),
                ];
            })
            ->values()
            ->all();
    }

    public function getActiveCustomerOptions(): array
    {
        return Customer::query()
            ->with('user')
            ->where('is_active', true)
            ->whereHas('user', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'value' => $customer->id,
                    'label' => trim(($customer->customer_number ? $customer->customer_number.' - ' : '').($customer->user?->name ?? 'Unknown')),
                    'name' => $customer->user?->name ?? '',
                    'contact' => $customer->user?->contact,
                    'address' => $customer->address,
                    'email' => $customer->user?->email,
                ];
            })
            ->values()
            ->all();
    }

    public function accept($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => QuotationStatus::Accepted->value]);

            return $quotation;
        });
    }

    public function converted($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => QuotationStatus::Converted->value]);

            return $quotation;
        });
    }

    public function reject($data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::findOrFail($id);

            $linkedMemberIds = $this->getLinkedMemberIds($quotation);

            if (! empty($linkedMemberIds)) {
                CustomerConfirmationMember::query()
                    ->whereIn('id', $linkedMemberIds)
                    ->where('status', '!=', 'cancelled')
                    ->update(['status' => 'pending_payment']);
            }

            $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
            $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);

            $quotation->update([
                'status' => QuotationStatus::Rejected->value,
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
                'status' => QuotationStatus::Ready->value,
            ]);

            return $quotation->fresh();
        });
    }

    public function expire($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $linkedMemberIds = $this->getLinkedMemberIds($quotation);

            $this->resetLinkedMembersToDraft($linkedMemberIds);
            $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
            $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);

            $quotation->update([
                'status' => QuotationStatus::Expired->value,
            ]);

            return $quotation;
        });
    }

    public function cancel($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $linkedMemberIds = $this->getLinkedMemberIds($quotation);

            $this->resetLinkedMembersToDraft($linkedMemberIds);
            $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
            $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);

            $quotation->update(['status' => QuotationStatus::Cancelled->value]);

            return $quotation;
        });
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::find($id);

            if (! $quotation) {
                return false;
            }

            $linkedMemberIds = $this->getLinkedMemberIds($quotation);

            $this->resetLinkedMembersToDraft($linkedMemberIds);
            $this->removeLinkedMembersFromPackageManifest($quotation, $linkedMemberIds);
            $this->cancelLinkedInvoicesAndDropReceiptTransactions($quotation);

            $quotation->update(['status' => QuotationStatus::Expired->value]);
            $quotation->delete();

            return true;
        });
    }

    public function draft($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);

            if (! in_array($quotation->status?->value, [QuotationStatus::Rejected->value, QuotationStatus::Expired->value], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only rejected or expired quotations can be moved back to draft.',
                ]);
            }

            $linkedMemberIds = $this->getLinkedMemberIds($quotation);
            $conflictExists = false;

            if (! empty($linkedMemberIds)) {
                $conflictExists = QuotationItem::query()
                    ->whereIn('customer_confirmation_member_id', $linkedMemberIds)
                    ->whereHas('quotation', function ($query) use ($quotation) {
                        $query->where('id', '!=', $quotation->id)
                            ->whereNull('deleted_at')
                            ->whereNotIn('status', [
                                QuotationStatus::Cancelled->value,
                                QuotationStatus::Expired->value,
                                QuotationStatus::Rejected->value,
                            ]);
                    })
                    ->exists();
            }

            if ($conflictExists) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot move to draft because one or more members are already linked to another active quotation.',
                ]);
            }

            $quotation->update([
                'status' => QuotationStatus::Draft->value,
                'reason' => null,
            ]);

            return $quotation->fresh();
        });
    }

    /**
     * @return array<int>
     */
    private function getLinkedMemberIds(Quotation $quotation): array
    {
        return $quotation->quotationItems()
            ->whereNotNull('customer_confirmation_member_id')
            ->pluck('customer_confirmation_member_id')
            ->map(fn ($memberId) => (int) $memberId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $memberIds
     */
    private function resetLinkedMembersToDraft(array $memberIds): void
    {
        if (empty($memberIds)) {
            return;
        }

        CustomerConfirmationMember::query()
            ->whereIn('id', $memberIds)
            ->whereIn('status', ['pending_payment', 'partially_paid', 'fully_paid'])
            ->update(['status' => 'pending_payment']);
    }

    /**
     * @param  array<int>  $memberIds
     */
    private function removeLinkedMembersFromPackageManifest(Quotation $quotation, array $memberIds): void
    {
        if (empty($memberIds)) {
            return;
        }

        $packageId = (int) ($quotation->customerConfirmation?->package_id ?? 0);
        if ($packageId <= 0) {
            return;
        }

        $manifestIds = Manifest::query()
            ->where('package_id', $packageId)
            ->pluck('id')
            ->map(fn ($manifestId) => (int) $manifestId)
            ->filter(fn ($manifestId) => $manifestId > 0)
            ->values()
            ->all();

        if (empty($manifestIds)) {
            return;
        }

        $members = ManifestMember::query()
            ->whereIn('manifest_id', $manifestIds)
            ->whereIn('customer_confirmation_member_id', $memberIds)
            ->get();

        foreach ($members as $member) {
            $member->roomMembers()->delete();
            $member->collectionItem()?->delete();
            $member->delete();
        }

        app(PackageSeatService::class)->recalculateForPackageId($packageId);
    }

    private function cancelLinkedInvoicesAndDropReceiptTransactions(Quotation $quotation): void
    {
        $quotation->loadMissing('order.invoices.receipt');

        if (! $quotation->order) {
            return;
        }

        $invoices = $quotation->order->invoices;

        foreach ($invoices as $invoice) {
            $receiptIds = $invoice->receipt->pluck('id');

            if ($receiptIds->isNotEmpty()) {
                FinancialTransaction::where('reference_type', 'App\Models\Receipt')
                    ->whereIn('reference_id', $receiptIds)
                    ->delete();
            }

            $invoice->update(['status' => 'cancelled']);
        }
    }

    public function syncQuotationExtensionsFromOrderInvoices(Quotation $quotation, array $invoices): void
    {
        $aggregatedExtensions = collect($invoices)
            ->filter(fn ($invoice) => is_array($invoice))
            ->flatMap(function (array $invoice) {
                return collect($invoice['extensions'] ?? [])->filter(fn ($extension) => is_array($extension));
            })
            ->values()
            ->reduce(function (Collection $carry, array $extension) {
                $type = strtolower(trim((string) ($extension['type'] ?? 'discount')));

                // Tax extensions are item-level and should not be persisted as quotation-level extensions.
                if ($type === 'tax') {
                    return $carry;
                }

                $calculationMode = strtolower(trim((string) ($extension['calculation_mode'] ?? 'fixed')));
                if (! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    $calculationMode = 'fixed';
                }

                $name = trim((string) ($extension['name'] ?? ''));
                if ($name === '') {
                    $name = Str::headline(str_replace('_', ' ', $type));
                }

                $masterId = ! empty($extension['quotation_extension_master_id'])
                    ? (int) $extension['quotation_extension_master_id']
                    : null;

                $normalizedValue = $this->formatService->cleanDecimal(
                    $extension['calculation_value'] ?? $extension['amount'] ?? 0
                ) ?? 0;

                $groupKey = implode('|', [
                    (string) ($masterId ?? 0),
                    strtolower($name),
                    $type,
                    $calculationMode,
                ]);

                if (! $carry->has($groupKey)) {
                    $carry->put($groupKey, [
                        'quotation_extension_master_id' => $masterId,
                        'name' => $name,
                        'type' => $type,
                        'calculation_mode' => $calculationMode,
                        'calculation_value' => 0,
                        'sort_order' => $carry->count() + 1,
                    ]);
                }

                $current = $carry->get($groupKey);

                if (! is_array($current)) {
                    return $carry;
                }

                if ($calculationMode === 'percentage') {
                    $current['calculation_value'] = abs((float) $normalizedValue);
                } else {
                    $current['calculation_value'] =
                        (float) ($current['calculation_value'] ?? 0) + (float) $normalizedValue;
                }

                $carry->put($groupKey, $current);

                return $carry;
            }, collect())
            ->values()
            ->all();

        $this->syncQuotationExtensions($quotation, $aggregatedExtensions);
        $this->syncConvertedOrderInvoiceAndReceiptAmounts($quotation);
    }

    private function syncQuotationExtensions(Quotation $quotation, array $extensions): void
    {
        $itemSubtotal = (float) ($quotation->quotationItems()
            ->where('is_header', false)
            ->get()
            ->sum(function (QuotationItem $item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            }));

        $incomingExtensions = collect($extensions)
            ->filter(fn ($extension) => is_array($extension))
            ->map(function (array $extension, int $index) {
                $calculationMode = (string) ($extension['calculation_mode'] ?? 'fixed');
                if (! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    $calculationMode = 'fixed';
                }

                $calculationValue = $this->formatService->cleanDecimal(
                    $extension['calculation_value'] ?? $extension['amount'] ?? 0
                ) ?? 0;

                return [
                    'id' => ! empty($extension['id']) ? (int) $extension['id'] : null,
                    'quotation_extension_master_id' => ! empty($extension['quotation_extension_master_id'])
                        ? (int) $extension['quotation_extension_master_id']
                        : null,
                    'name' => (string) ($extension['name'] ?? ''),
                    'type' => (string) ($extension['type'] ?? 'discount'),
                    'calculation_mode' => $calculationMode,
                    'calculation_value' => $calculationValue,
                    'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
                ];
            })
            ->values();

        $nonDiscountExtensions = $incomingExtensions
            ->filter(fn (array $extension) => (string) ($extension['type'] ?? 'discount') !== 'discount')
            ->values()
            ->map(function (array $extension) use ($itemSubtotal) {
                $amount = $extension['calculation_mode'] === 'percentage'
                    ? ($itemSubtotal * ((float) $extension['calculation_value'] / 100))
                    : (float) $extension['calculation_value'];

                $extension['amount'] = $this->formatService->cleanDecimal($amount) ?? 0;

                return $extension;
            })
            ->values();

        $discountBaseAmount = $itemSubtotal;

        $discountExtension = $incomingExtensions
            ->filter(fn (array $extension) => (string) ($extension['type'] ?? 'discount') === 'discount')
            ->sortBy('sort_order')
            ->values()
            ->first();

        $discountExtensions = collect();

        if (is_array($discountExtension)) {
            $normalizedValue = abs((float) ($discountExtension['calculation_value'] ?? 0));
            $computedAmount = $discountExtension['calculation_mode'] === 'percentage'
                ? ($discountBaseAmount * $normalizedValue / 100)
                : $normalizedValue;

            $discountExtensions = collect([[
                ...$discountExtension,
                'calculation_value' => $this->formatService->cleanDecimal($normalizedValue) ?? $normalizedValue,
                'amount' => $this->formatService->cleanDecimal(-abs($computedAmount)) ?? -abs($computedAmount),
            ]]);
        }

        $sanitizedExtensions = $nonDiscountExtensions
            ->concat($discountExtensions)
            ->sortBy('sort_order')
            ->values()
            ->map(function (array $extension, int $index) {
                return [
                    ...$extension,
                    'sort_order' => $index + 1,
                ];
            })
            ->values();

        $normalizedExtensions = $sanitizedExtensions
            ->values()
            ->map(function (array $extension, int $index): array {
                return [
                    'id' => null,
                    'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                    'name' => (string) ($extension['name'] ?? ''),
                    'type' => (string) ($extension['type'] ?? 'discount'),
                    'calculation_mode' => (string) ($extension['calculation_mode'] ?? 'fixed'),
                    'calculation_value' => $this->formatService->cleanDecimal($extension['calculation_value'] ?? 0) ?? 0,
                    'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? 0) ?? 0,
                    'sort_order' => $index + 1,
                ];
            })
            ->all();

        $quotation->update([
            'extensions' => $normalizedExtensions,
        ]);
    }

    private function syncConvertedOrderInvoiceAndReceiptAmounts(Quotation $quotation): void
    {
        $order = $quotation->order()
            ->with([
                'invoices.quotationItems.taxes',
                'invoices.receipt',
            ])
            ->first();

        if (! $order || $order->invoices->isEmpty()) {
            return;
        }

        $invoices = $order->invoices->sortBy('id')->values();
        foreach ($invoices as $invoice) {
            $invoiceId = (int) $invoice->id;

            $subtotalAmount = (float) $invoice->quotationItems
                ->filter(fn ($item) => ! $item->is_header)
                ->sum(function ($item): float {
                    $quantity = (float) ($item->quantity ?? 0);
                    $rate = (float) ($item->rate ?? 0);

                    return $quantity * $rate;
                });

            $itemTaxTotal = (float) $invoice->quotationItems
                ->filter(fn ($item) => ! $item->is_header)
                ->sum(function ($item): float {
                    $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

                    return (float) $item->taxes->sum(function ($tax) use ($lineAmount): float {
                        $calculationMode = (string) ($tax->calculation_mode ?? '');
                        $calculationValue = (float) ($tax->calculation_value ?? 0);

                        if (! in_array($calculationMode, ['fixed', 'percentage'], true) || $calculationValue <= 0) {
                            return 0;
                        }

                        return $calculationMode === 'percentage'
                            ? ($lineAmount * $calculationValue / 100)
                            : $calculationValue;
                    });
                });

            $normalizedExtensions = collect(is_array($invoice->extensions) ? $invoice->extensions : [])
                ->filter(fn ($extension) => is_array($extension))
                ->values()
                ->map(function (array $extension, int $index) use ($subtotalAmount) {
                    $type = strtolower(trim((string) ($extension['type'] ?? 'discount')));
                    if ($type === 'tax') {
                        return null;
                    }

                    $calculationMode = strtolower(trim((string) ($extension['calculation_mode'] ?? 'fixed')));
                    if (! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                        $calculationMode = 'fixed';
                    }

                    $calculationValue = (float) ($this->formatService->cleanDecimal(
                        $extension['calculation_value'] ?? $extension['amount'] ?? 0
                    ) ?? 0);

                    $computedAmount = $calculationMode === 'percentage'
                        ? ($subtotalAmount * $calculationValue / 100)
                        : $calculationValue;

                    $amount = $type === 'discount'
                        ? -abs($computedAmount)
                        : $computedAmount;

                    return [
                        'id' => $extension['id'] ?? null,
                        'quotation_extension_master_id' => ! empty($extension['quotation_extension_master_id'])
                            ? (int) $extension['quotation_extension_master_id']
                            : null,
                        'name' => (string) ($extension['name'] ?? 'Extension'),
                        'type' => $type,
                        'calculation_mode' => $calculationMode,
                        'calculation_value' => $this->formatService->cleanDecimal($calculationValue) ?? 0,
                        'amount' => $this->formatService->cleanDecimal($amount) ?? 0,
                        'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
                    ];
                })
                ->filter(fn ($extension) => is_array($extension))
                ->values()
                ->all();

            $invoiceExtensionTotal = (float) collect($normalizedExtensions)
                ->sum(fn (array $extension): float => (float) ($extension['amount'] ?? 0));

            $newInvoiceAmount = $subtotalAmount + $itemTaxTotal + $invoiceExtensionTotal;
            $newInvoiceAmount = (float) ($this->formatService->cleanDecimal($newInvoiceAmount) ?? $newInvoiceAmount);

            if ((float) $invoice->amount !== (float) $newInvoiceAmount) {
                $invoice->update([
                    'amount' => $newInvoiceAmount,
                    'extensions' => $normalizedExtensions,
                ]);
            } elseif ($invoice->extensions !== $normalizedExtensions) {
                $invoice->update(['extensions' => $normalizedExtensions]);
            }

            if ($invoice->receipt->isNotEmpty()) {
                $invoice->receipt()->update(['amount' => $newInvoiceAmount]);
                $this->paymentStatusService->syncAfterReceiptMutation($invoiceId);
            }
        }
    }

    private function buildInvoiceExtensionsForQuotationDisplay(Quotation $quotation): array
    {
        $order = $quotation->order;

        if (! $order) {
            return [];
        }

        $aggregated = collect($order->invoices ?? [])
            ->flatMap(function ($invoice) {
                $extensions = is_array($invoice->extensions ?? null)
                    ? $invoice->extensions
                    : [];

                return collect($extensions)->filter(fn ($extension) => is_array($extension));
            })
            ->reduce(function (Collection $carry, array $extension) {
                $type = strtolower(trim((string) ($extension['type'] ?? 'discount')));

                if ($type === 'tax') {
                    return $carry;
                }

                $calculationMode = strtolower(trim((string) ($extension['calculation_mode'] ?? 'fixed')));
                if (! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    $calculationMode = 'fixed';
                }

                $name = trim((string) ($extension['name'] ?? ''));
                if ($name === '') {
                    $name = Str::headline(str_replace('_', ' ', $type));
                }

                $masterId = ! empty($extension['quotation_extension_master_id'])
                    ? (int) $extension['quotation_extension_master_id']
                    : null;

                $calculationValue = $this->formatService->cleanDecimal(
                    $extension['calculation_value'] ?? 0
                ) ?? 0;

                $amount = $this->formatService->cleanDecimal(
                    $extension['amount'] ?? 0
                ) ?? 0;

                $groupKey = implode('|', [
                    (string) ($masterId ?? 0),
                    strtolower($name),
                    $type,
                    $calculationMode,
                    (string) $calculationValue,
                ]);

                if (! $carry->has($groupKey)) {
                    $carry->put($groupKey, [
                        'id' => null,
                        'quotation_extension_master_id' => $masterId,
                        'name' => $name,
                        'type' => $type,
                        'calculation_mode' => $calculationMode,
                        'calculation_value' => $calculationValue,
                        'amount' => 0,
                    ]);
                }

                $current = $carry->get($groupKey);

                if (! is_array($current)) {
                    return $carry;
                }

                $current['amount'] = (float) ($current['amount'] ?? 0) + (float) $amount;

                $carry->put($groupKey, $current);

                return $carry;
            }, collect())
            ->values()
            ->map(function (array $extension, int $index) {
                return [
                    ...$extension,
                    'amount' => $this->formatService->cleanDecimal($extension['amount'] ?? 0),
                    'sort_order' => $index + 1,
                ];
            })
            ->values()
            ->all();

        return $aggregated;
    }
}

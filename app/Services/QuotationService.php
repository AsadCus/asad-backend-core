<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Helpers\FormatService;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\FinancialTransaction;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
                'customer_confirmation_id' => $data['customer_confirmation_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? QuotationStatus::Draft->value,
                'reason' => $data['reason'] ?? null,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->quotationItemService->storeQuotationItems($quotation->id, $data['items']);
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
        $quotation = Quotation::with(['customer.user', 'quotationItems.confirmationMember', 'customerConfirmation.package'])->findOrFail($id);

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
            'total_amount' => $this->formatService->cleanDecimal($quotation->total_amount),
            'payment_plan' => $quotation->payment_plan,
            'payment_method' => $quotation->payment_method,
            'status' => $quotation->status?->value,
            'reason' => $quotation->reason,
            'package_name' => $quotation->customerConfirmation?->package?->name,
            'package_price_single' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_single),
            'package_price_double' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_double),
            'package_price_triple' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_triple),
            'package_price_quad' => $this->formatService->cleanDecimal($quotation->customerConfirmation?->package?->price_quad),
            'sales_registration_number' => $quotation->sales_registration_number,
            'model' => 'quotation',
            'notes' => $quotation->quotationNotes->sortBy('sort_order')->values()->toArray(),
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
                    'sort_order' => $it->sort_order,
                ];
            })->values()->toArray(),
        ];
    }

    public function update(array $data, int $id): Quotation
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::findOrFail($id);

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
                'customer_id' => $data['customer_id'] ?? null,
                'customer_confirmation_id' => $data['customer_confirmation_id'] ?? $quotation->customer_confirmation_id,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $quotation->status?->value,
                'reason' => $data['reason'] ?? $quotation->reason,
            ]);

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->quotationItemService->replaceQuotationItems($quotation->id, $data['items']);
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
            ->select('id', 'parent_id', 'sort_order')
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

        foreach ($orderedItemIds as $itemId) {
            $item = $itemsById->get($itemId);
            if (! $item) {
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
                $targetInvoiceId = $lastInvoiceId;
            }

            if (! isset($invoicePosition[$targetInvoiceId])) {
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
            $assignedInvoiceId = $invoiceByItemId[$itemId] ?? $defaultInvoiceId;

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
                ->where('status', 'draft')
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
            ->update(['status' => 'draft']);
    }

    public function getCustomerConfirmationCreateOptions(?int $includeConfirmationId = null): array
    {
        return CustomerConfirmation::query()
            ->with(['members.customer.user', 'members.quotationItems'])
            ->where(function ($query) use ($includeConfirmationId) {
                $query->whereHas('members', function ($memberQuery) {
                    $memberQuery->where('status', 'draft')
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

                $eligibleCount = $confirmation->members
                    ->filter(fn (CustomerConfirmationMember $member) => $member->status === 'draft' && $member->quotationItems->isEmpty())
                    ->count();

                return [
                    'value' => $confirmation->id,
                    'label' => 'CC-'.$confirmation->id.' - '.($leader?->customer?->user?->name ?? 'Unknown').' ('.$eligibleCount.' member(s))',
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

            $linkedMemberIds = $quotation->quotationItems()
                ->whereNotNull('customer_confirmation_member_id')
                ->pluck('customer_confirmation_member_id')
                ->unique()
                ->values();

            if ($linkedMemberIds->isNotEmpty()) {
                CustomerConfirmationMember::query()
                    ->whereIn('id', $linkedMemberIds)
                    ->where('status', '!=', 'cancelled')
                    ->update(['status' => 'draft']);
            }

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

            $this->resetLinkedMembersToDraft($this->getLinkedMemberIds($quotation));

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

            $quotation->update(['status' => QuotationStatus::Cancelled->value]);

            return $quotation;
        });
    }

    public function delete($id)
    {
        $quotation = Quotation::find($id);

        if (! $quotation) {
            return false;
        }

        $this->resetLinkedMembersToDraft($this->getLinkedMemberIds($quotation));

        $quotation->update(['status' => QuotationStatus::Expired->value]);

        return activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id ?? null])
            ->log('Quotation deleted successfully #'.($quotation->id ?? null));

        $quotation->delete();
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
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'draft']);
    }
}

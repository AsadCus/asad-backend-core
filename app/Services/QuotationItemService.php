<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\QuotationItem;
use App\Models\QuotationItemMaster;
use Illuminate\Support\Facades\DB;

class QuotationItemService
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function getQuotationItemMasters(?bool $isOptional = null)
    {
        $items = QuotationItemMaster::query()
            ->when($isOptional !== null, function ($q) use ($isOptional) {
                $q->where(function ($q) use ($isOptional) {
                    $q->whereNull('parent_id')
                        ->where('is_optional', $isOptional)
                        ->orWhereIn('parent_id', function ($sub) use ($isOptional) {
                            $sub->select('id')
                                ->from('quotation_item_masters')
                                ->where('is_optional', $isOptional)
                                ->whereNull('parent_id');
                        });
                });
            })
            ->orderBy('sort_order')
            ->get()
            ->map(fn (QuotationItemMaster $m) => [
                'id' => $m->id,
                'parent_id' => $m->parent_id,
                'description' => $m->description,
                'is_header' => $m->is_header,
                'is_optional' => $m->is_optional,
                'quantity' => $this->formatService->cleanDecimal($m->quantity),
                'rate' => $this->formatService->cleanDecimal($m->rate),
                'sort_order' => $m->sort_order,
            ]);

        return $items;
    }

    public function getItemForFilter()
    {
        $data = QuotationItem::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->description,
            ];
        });

        return $data;
    }

    public function getItemMasterForFilter()
    {
        $data = QuotationItemMaster::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->description,
            ];
        });

        return $data;
    }

    public function getItemForSelect()
    {
        $data = QuotationItem::where('parent_id', false)->get();

        return $data;
    }

    public function storeQuotationItems(int $quotationId, array $items = []): array
    {
        return DB::transaction(function () use ($quotationId, $items) {
            $keyToId = [];
            $masterIdToQuotationId = [];
            $createdIds = [];

            // pass 1 — root items
            foreach ($items as $row) {
                $item = $row['item'] ?? $row;

                if (! empty($item['parent_key']) || ! empty($item['parent_id'])) {
                    continue;
                }

                $quotationItem = QuotationItem::create([
                    'quotation_id' => $quotationId,
                    'customer_confirmation_member_id' => $item['customer_confirmation_member_id'] ?? null,
                    'parent_id' => null,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'] ?? false,
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]);

                $createdIds[] = $quotationItem->id;

                if (! empty($item['_key'])) {
                    $keyToId[$item['_key']] = $quotationItem->id;
                }

                if (! empty($item['id'])) {
                    $masterIdToQuotationId[$item['id']] = $quotationItem->id;
                }
            }

            // pass 2 — child items
            foreach ($items as $row) {
                $item = $row['item'] ?? $row;

                // resolve parent
                $parentId = null;

                if (! empty($item['parent_key'])) {
                    $parentId = $keyToId[$item['parent_key']] ?? null;
                } elseif (! empty($item['parent_id'])) {
                    $parentId = $masterIdToQuotationId[$item['parent_id']] ?? null;
                }

                if (! $parentId) {
                    continue;
                }

                $quotationItem = QuotationItem::create([
                    'quotation_id' => $quotationId,
                    'customer_confirmation_member_id' => $item['customer_confirmation_member_id'] ?? null,
                    'parent_id' => $parentId,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'] ?? false,
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]);

                $createdIds[] = $quotationItem->id;

                if (! empty($item['_key'])) {
                    $keyToId[$item['_key']] = $quotationItem->id;
                }

                if (! empty($item['id'])) {
                    $masterIdToQuotationId[$item['id']] = $quotationItem->id;
                }
            }

            return $createdIds;
        });
    }

    public function replaceQuotationItems(int $quotationId, array $items = [], bool $deleteMissing = true): array
    {
        return DB::transaction(function () use ($quotationId, $items, $deleteMissing) {
            $keyToId = [];
            $masterIdToQuotationId = [];
            $incomingIds = [];
            $usedIds = [];
            $existingItems = QuotationItem::query()
                ->where('quotation_id', $quotationId)
                ->get(['id', 'parent_id', 'customer_confirmation_member_id', 'description', 'is_header']);
            $existingIdsBySignature = [];

            foreach ($existingItems as $existingItem) {
                $signature = $this->buildItemSignature(
                    (int) ($existingItem->parent_id ?? 0) ?: null,
                    $existingItem->customer_confirmation_member_id,
                    (string) ($existingItem->description ?? ''),
                    (bool) $existingItem->is_header,
                );

                if (! isset($existingIdsBySignature[$signature])) {
                    $existingIdsBySignature[$signature] = [];
                }

                $existingIdsBySignature[$signature][] = (int) $existingItem->id;
            }

            $existingMemberIds = QuotationItem::where('quotation_id', $quotationId)
                ->pluck('customer_confirmation_member_id', 'id');
            $existingSortOrders = QuotationItem::query()
                ->where('quotation_id', $quotationId)
                ->pluck('sort_order', 'id')
                ->map(fn ($sortOrder) => (int) $sortOrder)
                ->all();
            $nextSortOrder = ! empty($existingSortOrders)
                ? (max($existingSortOrders) + 1)
                : 1;

            // pass 1 — root items
            foreach ($items as $row) {
                $item = $row['item'] ?? $row;

                if (! empty($item['parent_key']) || ! empty($item['parent_id'])) {
                    continue;
                }

                $id = isset($item['id']) ? (int) $item['id'] : null;
                if ($id && in_array($id, $usedIds, true)) {
                    $id = null;
                }

                if (! $id) {
                    $id = $this->popMatchingExistingItemId(
                        $existingIdsBySignature,
                        $usedIds,
                        null,
                        $item['customer_confirmation_member_id'] ?? null,
                        (string) ($item['description'] ?? ''),
                        (bool) ($item['is_header'] ?? false),
                    );
                }

                $customerConfirmationMemberId = array_key_exists('customer_confirmation_member_id', $item)
                    ? $item['customer_confirmation_member_id']
                    : ($id ? $existingMemberIds->get($id) : null);

                $payload = [
                    'quotation_id' => $quotationId,
                    'customer_confirmation_member_id' => $customerConfirmationMemberId,
                    'parent_id' => null,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'] ?? false,
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => $this->resolveSortOrder(
                        $item,
                        $id,
                        $existingSortOrders,
                        $nextSortOrder,
                    ),
                ];

                $quotationItem = $id
                    ? QuotationItem::updateOrCreate(
                        ['id' => $id, 'quotation_id' => $quotationId],
                        $payload
                    )
                    : QuotationItem::create($payload);

                if ($id) {
                    $usedIds[] = (int) $id;
                }

                $incomingIds[] = $quotationItem->id;

                if (! empty($item['_key'])) {
                    $keyToId[$item['_key']] = $quotationItem->id;
                }

                if (! empty($item['id']) && ! isset($masterIdToQuotationId[$item['id']])) {
                    $masterIdToQuotationId[$item['id']] = $quotationItem->id;
                }
            }

            // pass 2 — child items
            foreach ($items as $row) {
                $item = $row['item'] ?? $row;

                $parentId = null;

                if (! empty($item['parent_key'])) {
                    $parentId = $keyToId[$item['parent_key']] ?? null;
                } elseif (! empty($item['parent_id'])) {
                    $parentId = $masterIdToQuotationId[$item['parent_id']] ?? null;
                }

                if (! $parentId) {
                    continue;
                }

                $id = isset($item['id']) ? (int) $item['id'] : null;
                if ($id && in_array($id, $usedIds, true)) {
                    $id = null;
                }

                if (! $id) {
                    $id = $this->popMatchingExistingItemId(
                        $existingIdsBySignature,
                        $usedIds,
                        (int) $parentId,
                        $item['customer_confirmation_member_id'] ?? null,
                        (string) ($item['description'] ?? ''),
                        (bool) ($item['is_header'] ?? false),
                    );
                }

                $customerConfirmationMemberId = array_key_exists('customer_confirmation_member_id', $item)
                    ? $item['customer_confirmation_member_id']
                    : ($id ? $existingMemberIds->get($id) : null);

                $payload = [
                    'quotation_id' => $quotationId,
                    'customer_confirmation_member_id' => $customerConfirmationMemberId,
                    'parent_id' => $parentId,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'] ?? false,
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => $this->resolveSortOrder(
                        $item,
                        $id,
                        $existingSortOrders,
                        $nextSortOrder,
                    ),
                ];

                $quotationItem = $id
                    ? QuotationItem::updateOrCreate(
                        ['id' => $id, 'quotation_id' => $quotationId],
                        $payload
                    )
                    : QuotationItem::create($payload);

                if ($id) {
                    $usedIds[] = (int) $id;
                }

                $incomingIds[] = $quotationItem->id;
            }

            if ($deleteMissing) {
                $this->deleteUnusedQuotationItems($quotationId, $incomingIds);
            }

            return $incomingIds;
        });
    }

    private function buildItemSignature(
        ?int $parentId,
        mixed $customerConfirmationMemberId,
        string $description,
        bool $isHeader
    ): string {
        return implode('|', [
            $parentId ?? 0,
            (int) ($customerConfirmationMemberId ?? 0),
            strtolower(trim($description)),
            (int) $isHeader,
        ]);
    }

    /**
     * @param  array<string, array<int>>  $existingIdsBySignature
     * @param  array<int>  $usedIds
     */
    private function popMatchingExistingItemId(
        array &$existingIdsBySignature,
        array $usedIds,
        ?int $parentId,
        mixed $customerConfirmationMemberId,
        string $description,
        bool $isHeader
    ): ?int {
        $signature = $this->buildItemSignature(
            $parentId,
            $customerConfirmationMemberId,
            $description,
            $isHeader,
        );

        $candidateIds = $existingIdsBySignature[$signature] ?? [];

        while (! empty($candidateIds)) {
            $candidateId = (int) array_shift($candidateIds);

            if (! in_array($candidateId, $usedIds, true)) {
                $existingIdsBySignature[$signature] = $candidateIds;

                return $candidateId;
            }
        }

        $existingIdsBySignature[$signature] = $candidateIds;

        return null;
    }

    public function deleteUnusedQuotationItems(int $quotationId, array $keepIds = []): void
    {
        QuotationItem::where('quotation_id', $quotationId)
            ->when(! empty($keepIds), function ($query) use ($keepIds) {
                $query->whereNotIn('id', $keepIds);
            })
            ->whereDoesntHave('invoices')
            ->delete();
    }

    public function storeQuotationItemMaster(array $data = []): void
    {
        DB::transaction(function () use ($data) {
            $incomingIds = [];
            $keyToId = [];

            // pass 1 — create / update root items (no parent_key)
            foreach ($data as $d) {
                $item = is_array($d) ? $d : (array) $d;

                if (! empty($item['parent_key'])) {
                    continue;
                }

                $id = ! empty($item['id']) ? (int) $item['id'] : null;

                $payload = [
                    'parent_id' => $item['parent_id'] ?? null,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'],
                    'is_optional' => $item['is_optional'],
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ];

                if ($id) {
                    $master = QuotationItemMaster::find($id);
                    if ($master) {
                        $master->update($payload);
                    } else {
                        $master = QuotationItemMaster::create($payload);
                    }
                } else {
                    $master = QuotationItemMaster::create($payload);
                }

                $incomingIds[] = $master->id;

                if (! empty($item['_key'])) {
                    $keyToId[$item['_key']] = $master->id;
                }
            }

            // pass 2 — create / update child items (with parent_key)
            foreach ($data as $d) {
                $item = is_array($d) ? $d : (array) $d;

                if (empty($item['parent_key'])) {
                    continue;
                }

                $parentId = $keyToId[$item['parent_key']] ?? null;
                if (! $parentId) {
                    continue;
                }

                $id = ! empty($item['id']) ? (int) $item['id'] : null;

                $payload = [
                    'parent_id' => $parentId,
                    'description' => $item['description'],
                    'is_header' => $item['is_header'],
                    'is_optional' => $item['is_optional'],
                    'quantity' => $item['quantity'] ?? null,
                    'rate' => $item['rate'] ?? null,
                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                ];

                if ($id) {
                    $master = QuotationItemMaster::find($id);
                    if ($master) {
                        $master->update($payload);
                    } else {
                        $master = QuotationItemMaster::create($payload);
                    }
                } else {
                    $master = QuotationItemMaster::create($payload);
                }

                $incomingIds[] = $master->id;
            }

            // delete removed items
            $allIds = QuotationItemMaster::pluck('id')->toArray();
            $toDelete = array_diff($allIds, $incomingIds);

            QuotationItemMaster::whereIn('id', $toDelete)->delete();
        });
    }

    /**
     * @return array{parent: array<string, mixed>, children: array<int, array<string, mixed>>}
     */
    public function quickCreateItemGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $nextSortOrder = ((int) (QuotationItemMaster::max('sort_order') ?? 0)) + 1;

            $parent = QuotationItemMaster::create([
                'parent_id' => null,
                'description' => $data['name'],
                'is_header' => true,
                'is_optional' => true,
                'quantity' => null,
                'rate' => null,
                'sort_order' => $nextSortOrder,
            ]);

            $child = QuotationItemMaster::create([
                'parent_id' => $parent->id,
                'description' => $data['description'],
                'is_header' => false,
                'is_optional' => true,
                'quantity' => $data['quantity'] ?? 1,
                'rate' => $data['rate'] ?? 0,
                'sort_order' => $nextSortOrder + 1,
            ]);

            return [
                'parent' => [
                    'id' => $parent->id,
                    'parent_id' => $parent->parent_id,
                    'description' => $parent->description,
                    'is_header' => $parent->is_header,
                    'is_optional' => $parent->is_optional,
                    'quantity' => $this->formatService->cleanDecimal($parent->quantity),
                    'rate' => $this->formatService->cleanDecimal($parent->rate),
                    'sort_order' => $parent->sort_order,
                ],
                'children' => [[
                    'id' => $child->id,
                    'parent_id' => $child->parent_id,
                    'description' => $child->description,
                    'is_header' => $child->is_header,
                    'is_optional' => $child->is_optional,
                    'quantity' => $this->formatService->cleanDecimal($child->quantity),
                    'rate' => $this->formatService->cleanDecimal($child->rate),
                    'sort_order' => $child->sort_order,
                ]],
            ];
        });
    }

    public function deleteItem($id)
    {
        $item = QuotationItem::find($id);
        if (! $item) {
            return false;
        }

        return $item->delete();
    }

    public function deleteItemMaster($id)
    {
        $item = QuotationItemMaster::find($id);
        if (! $item) {
            return false;
        }

        return $item->delete();
    }

    private function resolveSortOrder(
        array $item,
        ?int $itemId,
        array $existingSortOrders,
        int &$nextSortOrder
    ): int {
        if (isset($item['workflow_sort_order']) && is_numeric($item['workflow_sort_order'])) {
            return (int) $item['workflow_sort_order'];
        }

        if (isset($item['sort_order']) && is_numeric($item['sort_order'])) {
            return (int) $item['sort_order'];
        }

        if ($itemId && isset($existingSortOrders[$itemId])) {
            return (int) $existingSortOrders[$itemId];
        }

        $sortOrder = $nextSortOrder;
        $nextSortOrder++;

        return $sortOrder;
    }
}

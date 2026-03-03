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

    public function replaceQuotationItems(int $quotationId, array $items = []): array
    {
        return DB::transaction(function () use ($quotationId, $items) {
            $keyToId = [];
            $masterIdToQuotationId = [];
            $incomingIds = [];
            $existingMemberIds = QuotationItem::where('quotation_id', $quotationId)
                ->pluck('customer_confirmation_member_id', 'id');

            // pass 1 — root items
            foreach ($items as $row) {
                $item = $row['item'] ?? $row;

                if (! empty($item['parent_key']) || ! empty($item['parent_id'])) {
                    continue;
                }

                $id = $item['id'] ?? null;
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
                    'sort_order' => $item['sort_order'] ?? 0,
                ];

                $quotationItem = $id
                    ? QuotationItem::updateOrCreate(
                        ['id' => $id, 'quotation_id' => $quotationId],
                        $payload
                    )
                    : QuotationItem::create($payload);

                $incomingIds[] = $quotationItem->id;

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

                $parentId = null;

                if (! empty($item['parent_key'])) {
                    $parentId = $keyToId[$item['parent_key']] ?? null;
                } elseif (! empty($item['parent_id'])) {
                    $parentId = $masterIdToQuotationId[$item['parent_id']] ?? null;
                }

                if (! $parentId) {
                    continue;
                }

                $id = $item['id'] ?? null;
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
                    'sort_order' => $item['sort_order'] ?? 0,
                ];

                $quotationItem = $id
                    ? QuotationItem::updateOrCreate(
                        ['id' => $id, 'quotation_id' => $quotationId],
                        $payload
                    )
                    : QuotationItem::create($payload);

                $incomingIds[] = $quotationItem->id;
            }

            QuotationItem::where('quotation_id', $quotationId)->whereNotIn('id', $incomingIds)->whereDoesntHave('invoices')->delete();

            return $incomingIds;
        });
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
}

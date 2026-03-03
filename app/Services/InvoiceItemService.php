<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class InvoiceItemService
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function get()
    {
        $items = InvoiceItem::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (InvoiceItem $m) => [
                'id' => $m->id,
                'parent_id' => $m->parent_id,
                'invoice_id' => $m->invoice_id,
                'description' => $m->description,
                'is_header' => $m->is_header,
                'amount' => $this->formatService->cleanDecimal($m->amount),
                'sort_order' => $m->sort_order,
            ]);

        $tree = $items
            ->whereNull('parent_id')
            ->values()
            ->map(function ($item) use ($items) {
                return array_merge($item, [
                    'children' => $items
                        ->where('parent_id', $item['id'])
                        ->values()
                        ->all(),
                ]);
            });

        return $tree;
    }

    public function getItemForFilter()
    {
        $data = InvoiceItem::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->description,
            ];
        });

        return $data;
    }

    public function store(int $invoiceId, array $items = []): void
    {
        foreach ($items as $item) {
            InvoiceItem::create([
                'parent_id' => $item['parent_id'],
                'invoice_id' => $invoiceId,
                'quotation_item_id' => $item['quotation_item_id'],
                'description' => $item['description'],
                'is_header' => $item['is_header'] ?? null,
                'amount' => $item['amount'] ?? null,
                'sort_order' => $item['sort_order'],
            ]);
        }
    }

    public function replace(int $invoiceId, array $items = []): void
    {
        DB::transaction(function () use ($invoiceId, $items) {
            InvoiceItem::where('invoice_id', $invoiceId)->delete();
            if (! empty($items)) {
                $this->store($invoiceId, $items);
            }
        });
    }

    public function deleteItem($id)
    {
        $item = InvoiceItem::find($id);
        if (! $item) {
            return false;
        }

        return $item->delete();
    }
}

<?php

namespace App\Services;

use App\Models\InvoiceNotes;
use App\Models\MasterNotes;
use App\Models\QuotationNotes;
use App\Models\ReceiptNotes;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class NoteService
{
    protected function resolve(string $model): array
    {
        return match ($model) {
            'quotation' => ['model' => QuotationNotes::class, 'key' => 'quotation_id'],
            'invoice' => ['model' => InvoiceNotes::class,  'key' => 'invoice_id'],
            'receipt' => ['model' => ReceiptNotes::class,  'key' => 'receipt_id'],
            'master' => ['model' => MasterNotes::class,   'key' => 'model'],
            default => throw new InvalidArgumentException("Unsupported model: {$model}"),
        };
    }

    protected function ownerKey(string $model): ?string
    {
        return match ($model) {
            'quotation' => 'quotation_id',
            'invoice' => 'invoice_id',
            'receipt' => 'receipt_id',
            default => null,
        };
    }

    public function get(string $model, int|string|null $id = null): Collection
    {
        if ($model === 'master') {
            // dd(MasterNotes::where('model', 'quotation')->orderBy('sort_order')->get());
            return MasterNotes::where('model', $id)->orderBy('sort_order')->get();
        }

        ['model' => $noteModel, 'key' => $key] = $this->resolve($model);

        return $noteModel::where($key, $id)->orderBy('sort_order')->get();
    }

    public function sync(string $model, ?int $ownerId, array $notes)
    {
        ['model' => $noteModel, 'key' => $key] = $this->resolve($model);

        $existing = $noteModel::query()
            ->when($ownerId !== null, fn ($q) => $q->where($key, $ownerId))
            ->get()
            ->keyBy('id');

        $incomingIds = collect($notes)->pluck('id')->filter()->all();

        $existing->keys()
            ->diff($incomingIds)
            ->each(fn ($id) => $existing[$id]->delete());

        $result = collect();

        foreach ($notes as $note) {
            if (! empty($note['id']) && $existing->has($note['id'])) {
                $existing[$note['id']]->update([
                    'description' => $note['description'],
                    'sort_order' => $note['sort_order'],
                ]);

                $result->push($existing[$note['id']]);
            } else {
                $payload = [
                    'description' => $note['description'],
                    'sort_order' => $note['sort_order'],
                    $key => $ownerId ?? $note['model'],
                ];

                $result->push($noteModel::create($payload));
            }
        }
    }

    public function delete(string $model, int $noteId): bool
    {
        ['model' => $noteModel] = $this->resolve($model);

        return (bool) $noteModel::findOrFail($noteId)->delete();
    }
}

<?php

namespace App\Services;

use App\Models\Holding;
use Illuminate\Support\Facades\DB;

class HoldingService
{
    public function getForDataTable()
    {
        return Holding::query()->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'address' => $q->address,
            'phone' => $q->phone,
            'email' => $q->email,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return Holding::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $holding = Holding::findOrFail($id);

        return [
            'id' => $holding->id,
            'name' => $holding->name,
            'code' => $holding->code,
            'address' => $holding->address,
            'phone' => $holding->phone,
            'email' => $holding->email,
            'is_active' => (bool) $holding->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $holding = Holding::create($data);

            activity()->performedOn($holding)->log('Holding created successfully #'.($holding->id ?? null));

            return $holding;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $holding = Holding::findOrFail($id);
            $holding->update($data);

            activity()->performedOn($holding)->log('Holding updated successfully #'.($holding->id ?? null));

            return $holding;
        });
    }

    public function delete($id)
    {
        $holding = Holding::find($id);

        if (! $holding) {
            return false;
        }

        $holding->delete();

        activity()->performedOn($holding)->log('Holding deleted successfully #'.($holding->id ?? null));

        return true;
    }
}

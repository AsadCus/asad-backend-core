<?php

namespace App\Services;

use App\Models\BusinessUnit;
use Illuminate\Support\Facades\DB;

class BusinessUnitService
{
    public function getForDataTable()
    {
        return BusinessUnit::query()->with('holding')->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'holding_id' => $q->holding_id,
            'holding_name' => $q->holding?->name,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return BusinessUnit::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $businessUnit = BusinessUnit::findOrFail($id);

        return [
            'id' => $businessUnit->id,
            'name' => $businessUnit->name,
            'code' => $businessUnit->code,
            'holding_id' => $businessUnit->holding_id,
            'is_active' => (bool) $businessUnit->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $businessUnit = BusinessUnit::create($data);

            activity()->performedOn($businessUnit)->log('Business unit created successfully #'.($businessUnit->id ?? null));

            return $businessUnit;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $businessUnit = BusinessUnit::findOrFail($id);
            $businessUnit->update($data);

            activity()->performedOn($businessUnit)->log('Business unit updated successfully #'.($businessUnit->id ?? null));

            return $businessUnit;
        });
    }

    public function delete($id)
    {
        $businessUnit = BusinessUnit::find($id);

        if (! $businessUnit) {
            return false;
        }

        $businessUnit->delete();

        activity()->performedOn($businessUnit)->log('Business unit deleted successfully #'.($businessUnit->id ?? null));

        return true;
    }
}

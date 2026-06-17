<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    public function getForDataTable()
    {
        return Holiday::query()->orderBy('date')->get()->map(fn ($q) => [
            'id' => $q->id,
            'date' => $q->date?->toDateString(),
            'name' => $q->name,
            'type' => $q->type?->value,
            'description' => $q->description,
            'is_recurring' => (bool) $q->is_recurring,
        ]);
    }

    public function getForFilter()
    {
        return Holiday::query()->orderBy('date')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $holiday = Holiday::findOrFail($id);

        return [
            'id' => $holiday->id,
            'date' => $holiday->date?->toDateString(),
            'name' => $holiday->name,
            'type' => $holiday->type?->value,
            'description' => $holiday->description,
            'is_recurring' => (bool) $holiday->is_recurring,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $holiday = Holiday::create($data);

            activity()->performedOn($holiday)->log('Holiday created successfully #'.($holiday->id ?? null));

            return $holiday;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $holiday = Holiday::findOrFail($id);
            $holiday->update($data);

            activity()->performedOn($holiday)->log('Holiday updated successfully #'.($holiday->id ?? null));

            return $holiday;
        });
    }

    public function delete($id)
    {
        $holiday = Holiday::find($id);

        if (! $holiday) {
            return false;
        }

        $holiday->delete();

        activity()->performedOn($holiday)->log('Holiday deleted successfully #'.($holiday->id ?? null));

        return true;
    }
}

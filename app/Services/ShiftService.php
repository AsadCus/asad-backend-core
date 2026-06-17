<?php

namespace App\Services;

use App\Models\Shift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function getForDataTable()
    {
        return Shift::query()->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'start_time' => $q->start_time,
            'end_time' => $q->end_time,
            'break_minutes' => $q->break_minutes,
            'late_tolerance_minutes' => $q->late_tolerance_minutes,
            'is_overnight' => (bool) $q->is_overnight,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return Shift::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $shift = Shift::findOrFail($id);

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'code' => $shift->code,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'break_minutes' => $shift->break_minutes,
            'late_tolerance_minutes' => $shift->late_tolerance_minutes,
            'is_overnight' => (bool) $shift->is_overnight,
            'is_active' => (bool) $shift->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $shift = Shift::create($data);

            activity()->performedOn($shift)->log('Shift created successfully #'.($shift->id ?? null));

            return $shift;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $shift = Shift::findOrFail($id);
            $shift->update($data);

            activity()->performedOn($shift)->log('Shift updated successfully #'.($shift->id ?? null));

            return $shift;
        });
    }

    public function delete($id)
    {
        $shift = Shift::find($id);

        if (! $shift) {
            return false;
        }

        $shift->delete();

        activity()->performedOn($shift)->log('Shift deleted successfully #'.($shift->id ?? null));

        return true;
    }
}

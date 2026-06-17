<?php

namespace App\Services;

use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;

class WorkScheduleService
{
    public function getForDataTable()
    {
        return WorkSchedule::query()->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'description' => $q->description,
            'is_active' => (bool) $q->is_active,
        ]);
    }

    public function getForFilter()
    {
        return WorkSchedule::query()->orderBy('name')->get()->map(fn ($q) => [
            'value' => $q->id,
            'label' => $q->name,
        ]);
    }

    public function getForEditShow($id)
    {
        $workSchedule = WorkSchedule::findOrFail($id);

        return [
            'id' => $workSchedule->id,
            'name' => $workSchedule->name,
            'code' => $workSchedule->code,
            'description' => $workSchedule->description,
            'is_active' => (bool) $workSchedule->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $workSchedule = WorkSchedule::create($data);

            activity()->performedOn($workSchedule)->log('Work schedule created successfully #'.($workSchedule->id ?? null));

            return $workSchedule;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $workSchedule = WorkSchedule::findOrFail($id);
            $workSchedule->update($data);

            activity()->performedOn($workSchedule)->log('Work schedule updated successfully #'.($workSchedule->id ?? null));

            return $workSchedule;
        });
    }

    public function delete($id)
    {
        $workSchedule = WorkSchedule::find($id);

        if (! $workSchedule) {
            return false;
        }

        $workSchedule->delete();

        activity()->performedOn($workSchedule)->log('Work schedule deleted successfully #'.($workSchedule->id ?? null));

        return true;
    }
}

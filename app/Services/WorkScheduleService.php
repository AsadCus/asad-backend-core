<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkScheduleService
{
    public function getForDataTable()
    {
        return WorkSchedule::query()->with('ownerOrgUnit')->orderBy('name')->get()->map(fn ($q) => [
            'id' => $q->id,
            'name' => $q->name,
            'code' => $q->code,
            'owner_org_unit_id' => $q->owner_org_unit_id,
            'owner_org_unit_name' => $q->ownerOrgUnit?->name,
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
            'owner_org_unit_id' => $workSchedule->owner_org_unit_id,
            'description' => $workSchedule->description,
            'is_active' => (bool) $workSchedule->is_active,
        ];
    }

    /**
     * Duplicate an org-owned schedule (header + days) to every descendant org unit as
     * independent copies. Idempotent: re-running updates the existing copies.
     */
    public function generateDown($id): int
    {
        $source = WorkSchedule::with('workScheduleDays')->findOrFail($id);

        if (! $source->owner_org_unit_id) {
            throw ValidationException::withMessages([
                'owner_org_unit_id' => ['Assign this schedule to an org unit before generating down.'],
            ]);
        }

        $targetIds = array_values(array_filter(
            OrgUnit::subtreeIds((int) $source->owner_org_unit_id),
            fn ($x) => $x !== (int) $source->owner_org_unit_id,
        ));

        if ($targetIds === []) {
            return 0;
        }

        $units = OrgUnit::query()->whereIn('id', $targetIds)->get();

        return DB::transaction(function () use ($source, $units) {
            $count = 0;
            foreach ($units as $unit) {
                $copy = WorkSchedule::updateOrCreate(
                    ['code' => $source->code.'-'.$unit->code],
                    [
                        'name' => $source->name,
                        'owner_org_unit_id' => $unit->id,
                        'description' => $source->description,
                        'is_active' => $source->is_active,
                    ],
                );

                foreach ($source->workScheduleDays as $day) {
                    $copy->workScheduleDays()->updateOrCreate(
                        ['day_of_week' => $day->day_of_week],
                        ['shift_id' => $day->shift_id, 'is_workday' => $day->is_workday],
                    );
                }
                $count++;
            }

            activity()->performedOn($source)->log("Work schedule generated down to {$count} org unit(s) #".($source->id ?? null));

            return $count;
        });
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

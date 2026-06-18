<?php

namespace App\Services;

use App\Models\EmployeeSchedule;
use Illuminate\Support\Facades\DB;

class EmployeeScheduleService
{
    public function getForDataTable()
    {
        return EmployeeSchedule::query()
            ->with(['employee.user', 'workSchedule'])
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'employee_id' => $q->employee_id,
                'employee_name' => $q->employee?->user?->name ?? $q->employee?->employee_no,
                'work_schedule_id' => $q->work_schedule_id,
                'work_schedule_name' => $q->workSchedule?->name,
                'effective_from' => $q->effective_from?->format('Y-m-d'),
                'effective_to' => $q->effective_to?->format('Y-m-d'),
                'note' => $q->note,
            ]);
    }

    public function getForEditShow($id)
    {
        $schedule = EmployeeSchedule::findOrFail($id);

        return [
            'id' => $schedule->id,
            'employee_id' => $schedule->employee_id,
            'work_schedule_id' => $schedule->work_schedule_id,
            'effective_from' => $schedule->effective_from?->format('Y-m-d'),
            'effective_to' => $schedule->effective_to?->format('Y-m-d'),
            'note' => $schedule->note,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $schedule = EmployeeSchedule::create($data);

            activity()->performedOn($schedule)->log('Employee schedule created successfully #'.($schedule->id ?? null));

            return $schedule;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $schedule = EmployeeSchedule::findOrFail($id);
            $schedule->update($data);

            activity()->performedOn($schedule)->log('Employee schedule updated successfully #'.($schedule->id ?? null));

            return $schedule;
        });
    }

    public function delete($id)
    {
        $schedule = EmployeeSchedule::find($id);

        if (! $schedule) {
            return false;
        }

        $schedule->delete();

        activity()->performedOn($schedule)->log('Employee schedule deleted successfully #'.($schedule->id ?? null));

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Support\HrisScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeScheduleService
{
    /**
     * The authenticated employee's currently-active work schedule, broken down by weekday
     * (Sun=0..Sat=6) with each workday's shift hours. Used by the employee-facing "My Schedule"
     * screen and the Attendance page's today-shift card.
     */
    public function mySchedule(User $user): array
    {
        $employee = $user->employee;

        if (! $employee) {
            abort(422, 'No employee profile is linked to your account.');
        }

        $today = Carbon::today()->toDateString();

        $schedule = $employee->employeeSchedules()
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->with('workSchedule.workScheduleDays.shift')
            ->first();

        if (! $schedule || ! $schedule->workSchedule) {
            return [
                'work_schedule_name' => null,
                'effective_from' => null,
                'days' => [],
            ];
        }

        $days = $schedule->workSchedule->workScheduleDays
            ->sortBy('day_of_week')
            ->map(fn ($d) => [
                'day_of_week' => $d->day_of_week,
                'is_workday' => $d->is_workday,
                'shift' => $d->is_workday && $d->shift ? [
                    'name' => $d->shift->name,
                    'start_time' => substr($d->shift->start_time, 0, 5),
                    'end_time' => substr($d->shift->end_time, 0, 5),
                ] : null,
            ])
            ->values()
            ->all();

        return [
            'work_schedule_name' => $schedule->workSchedule->name,
            'effective_from' => $schedule->effective_from?->format('Y-m-d'),
            'days' => $days,
        ];
    }

    public function getForDataTable()
    {
        return HrisScope::applyViaEmployee(
            EmployeeSchedule::query()->with(['employee.user', 'workSchedule'])
        )
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
        $from = (string) $data['effective_from'];

        // If a schedule already covers the new start date, this is a *switch* — preserve history
        // by closing that period and opening a new one instead of stacking overlapping rows.
        $coversDate = EmployeeSchedule::query()
            ->where('employee_id', $data['employee_id'])
            ->where('effective_from', '<=', $from)
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            })
            ->exists();

        if ($coversDate) {
            return $this->changeSchedule((int) $data['employee_id'], (int) $data['work_schedule_id'], $from);
        }

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

    /**
     * Move an employee to a new schedule while preserving history: close the period active
     * at $effectiveFrom (effective_to = the day before) and INSERT a new open period.
     * Use this for real schedule changes; {@see update()} is for correcting an existing row.
     */
    public function changeSchedule(int $employeeId, int $workScheduleId, string $effectiveFrom): EmployeeSchedule
    {
        return DB::transaction(function () use ($employeeId, $workScheduleId, $effectiveFrom) {
            $from = Carbon::parse($effectiveFrom)->toDateString();

            EmployeeSchedule::query()
                ->where('employee_id', $employeeId)
                ->where('effective_from', '<=', $from)
                ->where(function ($q) use ($from) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                })
                ->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]);

            $schedule = EmployeeSchedule::create([
                'employee_id' => $employeeId,
                'work_schedule_id' => $workScheduleId,
                'effective_from' => $from,
                'effective_to' => null,
            ]);

            activity()->performedOn($schedule)->log('Employee schedule changed #'.$schedule->id);

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

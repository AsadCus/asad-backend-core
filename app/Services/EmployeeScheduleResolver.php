<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves which work-schedule day (and therefore shift) applies to an employee on a given
 * date, from their currently-active EmployeeSchedule assignment. Single source of truth for
 * "is this employee's date a working day" — shared by attendance lateness, the working-days
 * calculator (leave/WFH duration), and any other feature that needs the same lookup.
 */
class EmployeeScheduleResolver
{
    /**
     * The work_schedule_days row for the employee's active schedule on $date, or null when
     * they have no active schedule. Lets callers tell a rest day (row with is_workday=false)
     * apart from having no schedule at all (null).
     */
    public function resolveDay(Employee $employee, Carbon $date): ?WorkScheduleDay
    {
        $schedule = $employee->employeeSchedules()
            ->where('effective_from', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date->toDateString());
            })
            ->latest('effective_from')
            ->with('workSchedule.workScheduleDays.shift')
            ->first();

        if (! $schedule || ! $schedule->workSchedule) {
            return null;
        }

        // Carbon dayOfWeek: 0=Sunday..6=Saturday — matches the work_schedule_days convention.
        return $schedule->workSchedule->workScheduleDays->firstWhere('day_of_week', $date->dayOfWeek);
    }

    /**
     * Every EmployeeSchedule active at any point in [from, to] for $employeeIds, in one query
     * — pass the result to {@see resolveDayFromBatch()} instead of calling resolveDay() in a
     * loop, so classifying N employees over M days costs one query instead of N×M.
     *
     * @param  array<int>  $employeeIds
     * @return Collection<int, Collection<int, EmployeeSchedule>> keyed by employee_id
     */
    public function preloadForRange(array $employeeIds, Carbon $from, Carbon $to): Collection
    {
        if (empty($employeeIds)) {
            return collect();
        }

        return EmployeeSchedule::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('effective_from', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()))
            ->with('workSchedule.workScheduleDays.shift')
            ->orderBy('effective_from')
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * The work_schedule_days row for $employeeId on $date, from a batch loaded via
     * {@see preloadForRange()} — same result as resolveDay(), zero additional queries.
     *
     * @param  Collection<int, Collection<int, EmployeeSchedule>>  $preloaded
     */
    public function resolveDayFromBatch(Collection $preloaded, int $employeeId, Carbon $date): ?WorkScheduleDay
    {
        $schedules = $preloaded->get($employeeId) ?? collect();

        $active = $schedules
            ->filter(fn (EmployeeSchedule $s) => $s->effective_from->lte($date)
                && ($s->effective_to === null || $s->effective_to->gte($date)))
            ->sortByDesc('effective_from')
            ->first();

        if (! $active || ! $active->workSchedule) {
            return null;
        }

        return $active->workSchedule->workScheduleDays->firstWhere('day_of_week', $date->dayOfWeek);
    }
}

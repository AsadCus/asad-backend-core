<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Counts working days in a date range for an employee — excludes the employee's scheduled
 * rest days (per their work schedule) and company holidays (per the holiday calendar).
 * Shared by every request type whose duration should reflect actual working days, not raw
 * calendar days (Leave, WFH/Visit, ...), so "Friday to Monday" doesn't get billed as 4 days.
 */
class WorkingDaysCalculator
{
    public function __construct(private EmployeeScheduleResolver $scheduleResolver) {}

    /** True when $date is a working day for $employee: not a holiday, not a scheduled rest day. */
    public function isWorkingDay(Employee $employee, Carbon $date): bool
    {
        if (Holiday::isHoliday($date)) {
            return false;
        }

        $day = $this->scheduleResolver->resolveDay($employee, $date);

        // No active schedule → nothing to rule the day out, so treat it as a working day —
        // mirrors AttendanceService's own fallback when an employee has no schedule.
        return ! $day || $day->is_workday;
    }

    /**
     * Working days in [$start, $end] inclusive.
     */
    public function countWorkingDays(Employee $employee, Carbon $start, Carbon $end): int
    {
        $count = 0;

        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $date) {
            if ($this->isWorkingDay($employee, $date)) {
                $count++;
            }
        }

        return $count;
    }
}

<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OrgUnit;
use Carbon\Carbon;

class WorkScheduleAssignmentService
{
    /**
     * Seed every member of a unit's subtree that has no active schedule. Run when a unit's default
     * schedule is set/changed so existing members pick it up (each resolves the default up-tree and
     * skips anyone already scheduled). Returns the number of employees seeded.
     */
    public function seedUnitMembers(OrgUnit $unit): int
    {
        $employees = Employee::query()
            ->whereIn('org_unit_id', OrgUnit::subtreeIds($unit->id))
            ->get();

        $count = 0;
        foreach ($employees as $employee) {
            if ($this->seedForEmployee($employee) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Seed an employee_schedule from their org unit's default (resolved up the tree),
     * but only when they have no active schedule. Append-only: always INSERTs, never
     * edits an existing row, so the schedule timeline stays reconstructable.
     */
    public function seedForEmployee(Employee $employee): ?EmployeeSchedule
    {
        if (! $employee->org_unit_id) {
            return null;
        }

        if ($this->hasActiveSchedule($employee)) {
            return null;
        }

        $workScheduleId = $employee->orgUnit?->resolveDefaultWorkScheduleId();

        if (! $workScheduleId) {
            return null;
        }

        $schedule = EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $workScheduleId,
            'effective_from' => ($employee->hire_date ?? Carbon::now())->toDateString(),
            'effective_to' => null,
            'note' => 'Auto-assigned from org unit default',
        ]);

        activity()->performedOn($schedule)->log('Employee schedule auto-assigned from org unit default #'.$schedule->id);

        return $schedule;
    }

    /**
     * Whether the employee has a schedule whose effective window covers today.
     * Mirrors the active-window predicate in {@see AttendanceService::resolveShift()}.
     */
    private function hasActiveSchedule(Employee $employee): bool
    {
        $today = Carbon::now()->toDateString();

        return $employee->employeeSchedules()
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->exists();
    }
}

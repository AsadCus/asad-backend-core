<?php

namespace App\Services;

use App\Models\Employee;

class AttendanceEligibilityService
{
    /**
     * Roster of employees with their check-in eligibility, for the admin governance screen.
     * Role (jabatan) comes from the linked user (Spatie); org placement + work location from org_units.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForDataTable(): array
    {
        return Employee::query()
            ->with(['user.roles', 'orgUnit', 'workLocation'])
            ->orderBy('employee_no')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->user?->name ?? $e->employee_no,
                'role' => $e->user?->getRoleNames()->first(),
                'org_unit' => $e->orgUnit?->name,
                'work_location' => $e->resolveWorkLocation()?->name,
                'can_check_in' => (bool) $e->can_check_in,
            ])
            ->all();
    }

    /**
     * @return array{id:int, can_check_in:bool}
     */
    public function setEligibility(int $employeeId, bool $canCheckIn): array
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update(['can_check_in' => $canCheckIn]);

        activity()->performedOn($employee)
            ->log('Attendance eligibility '.($canCheckIn ? 'enabled' : 'disabled').' #'.$employee->id);

        return ['id' => $employee->id, 'can_check_in' => (bool) $employee->can_check_in];
    }

    /**
     * Apply one eligibility value to many employees (the "apply to filtered set" action).
     *
     * @param  array<int>  $ids
     */
    public function bulkSetEligibility(array $ids, bool $canCheckIn): int
    {
        return Employee::query()->whereIn('id', $ids)->update(['can_check_in' => $canCheckIn]);
    }
}

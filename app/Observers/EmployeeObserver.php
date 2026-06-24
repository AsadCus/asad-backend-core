<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\WorkScheduleAssignmentService;

/**
 * Seeds an employee_schedule from the org unit default on placement. Lives on the model
 * (not a service) so it fires for every write path — both EmployeeService and
 * HrisUserService::syncEmployee create/update Employee through Eloquent events.
 */
class EmployeeObserver
{
    public function __construct(private WorkScheduleAssignmentService $assignments) {}

    public function created(Employee $employee): void
    {
        $this->assignments->seedForEmployee($employee);
    }

    public function updated(Employee $employee): void
    {
        if ($employee->wasChanged('org_unit_id')) {
            $this->assignments->seedForEmployee($employee);
        }
    }
}

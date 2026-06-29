<?php

namespace Database\Seeders;

use App\Enums\OrgUnitType;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OrgUnit;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $workSchedules = [
            ['name' => 'Standard 5-Day Week', 'code' => 'WS-STD', 'description' => 'Monday to Friday, office hours.'],
            ['name' => 'Shift Rotation', 'code' => 'WS-SHIFT', 'description' => 'Rotating morning/afternoon/night shifts.'],
        ];

        foreach ($workSchedules as $workSchedule) {
            WorkSchedule::updateOrCreate(['code' => $workSchedule['code']], $workSchedule + ['is_active' => true]);
        }

        $this->seedStandardWeekDays();
        $this->assignDefaultScheduleToUnscheduledEmployees();
        $this->assignDefaultScheduleToBusinessUnits();
    }

    /**
     * Mon-Fri on Office Hours, Sat/Sun as rest days. Carbon dayOfWeek convention: 0=Sun..6=Sat
     * (matches AttendanceService::resolveScheduleDay's lookup).
     */
    private function seedStandardWeekDays(): void
    {
        $standard = WorkSchedule::where('code', 'WS-STD')->first();
        $office = Shift::where('code', 'OFFICE')->first();

        if (! $standard || ! $office) {
            return;
        }

        foreach (range(0, 6) as $dayOfWeek) {
            $isWorkday = $dayOfWeek >= 1 && $dayOfWeek <= 5;

            $standard->workScheduleDays()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                ['shift_id' => $isWorkday ? $office->id : null, 'is_workday' => $isWorkday],
            );
        }
    }

    /**
     * Demo/onboarded employees with no active EmployeeSchedule row would otherwise show no
     * shift at all on the Attendance page and "My Schedule" — put them on the Standard 5-Day
     * Week from their hire date so the feature has real data to display out of the box.
     */
    private function assignDefaultScheduleToUnscheduledEmployees(): void
    {
        $standard = WorkSchedule::where('code', 'WS-STD')->first();

        if (! $standard) {
            return;
        }

        Employee::doesntHave('employeeSchedules')->each(function (Employee $employee) use ($standard) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $standard->id,
                'effective_from' => $employee->hire_date ?? now()->toDateString(),
                'effective_to' => null,
            ]);
        });
    }

    /**
     * Business units fall back to the Standard 5-Day Week unless an admin has already
     * picked something else. `OrgUnit::resolveDefaultWorkScheduleId()` walks up the tree,
     * so this single assignment covers every branch/department/division beneath each BU.
     * Runs after the org tree exists (OrgUnitSeeder) and after WorkSchedule rows exist
     * (created above) — only WorkScheduleSeeder satisfies both, so it owns this step.
     */
    private function assignDefaultScheduleToBusinessUnits(): void
    {
        $standard = WorkSchedule::where('code', 'WS-STD')->first();

        if (! $standard) {
            return;
        }

        OrgUnit::where('type', OrgUnitType::BusinessUnit)
            ->whereNull('default_work_schedule_id')
            ->update(['default_work_schedule_id' => $standard->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OrgUnit;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use App\Services\EmployeeScheduleService;
use App\Services\EmployeeService;
use App\Services\OrgUnitService;
use App\Services\WorkScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrisSchedulingChainTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{day_of_week:int, shift_id:int|null, is_workday:bool}> */
    private function mondayToFriday(int $shiftId): array
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $work = $d >= 1 && $d <= 5;
            $days[] = ['day_of_week' => $d, 'shift_id' => $work ? $shiftId : null, 'is_workday' => $work];
        }

        return $days;
    }

    public function test_full_chain_seeds_schedule_and_resolves_shift(): void
    {
        Role::findOrCreate('employee', 'web');

        // 1–2. Shift → Work schedule (Mon–Fri → that shift).
        $shift = Shift::factory()->create(['start_time' => '08:00:00', 'late_tolerance_minutes' => 15]);
        $ws = app(WorkScheduleService::class)->store([
            'name' => 'Office', 'code' => 'OFFICE', 'is_active' => true,
            'days' => $this->mondayToFriday($shift->id),
        ]);

        // 3. Org unit with that default.
        $unit = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'default_work_schedule_id' => $ws->id]);

        // 4. Create the person (login + role + profile) placed in the unit.
        $employee = app(EmployeeService::class)->store([
            'name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'secret123', 'role' => 'employee',
            'hire_date' => '2026-01-01', 'employment_status' => 'permanent',
            'org_unit_id' => $unit->id, 'is_active' => true,
        ]);

        // The merge: one form produced a login account + a profile + an auto employee_no.
        $this->assertNotNull($employee->user_id);
        $this->assertSame('budi@example.com', $employee->user->email);
        $this->assertTrue($employee->user->hasRole('employee'));
        $this->assertStringStartsWith('EMP-', $employee->employee_no);

        // Placement seeded the schedule from the unit default.
        $this->assertSame($ws->id, $employee->employeeSchedules()->value('work_schedule_id'));

        // The whole chain resolves: importing a Monday attendance stamps the shift.
        $monday = '2026-06-15';
        $csv = "employee_no,date,check_in,check_out\n{$employee->employee_no},{$monday},08:05,17:00\n";
        app(AttendanceService::class)->import(
            UploadedFile::fake()->createWithContent('a.csv', $csv),
        );

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $monday)->first();
        $this->assertNotNull($attendance);
        $this->assertSame($shift->id, $attendance->shift_id);
    }

    public function test_setting_unit_default_seeds_existing_members(): void
    {
        $shift = Shift::factory()->create();
        $ws = app(WorkScheduleService::class)->store([
            'name' => 'Office', 'code' => 'OFFICE2', 'is_active' => true,
            'days' => $this->mondayToFriday($shift->id),
        ]);

        // Member is placed BEFORE the unit has a default → no schedule yet.
        $unit = OrgUnit::factory()->create(['type' => OrgUnitType::Holding]);
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id]);
        $this->assertSame(0, $employee->employeeSchedules()->count());

        // Setting the default now seeds existing members.
        app(OrgUnitService::class)->update([
            'type' => 'holding', 'parent_id' => null, 'name' => $unit->name, 'code' => $unit->code,
            'default_work_schedule_id' => $ws->id, 'is_active' => true,
        ], $unit->id);

        $this->assertSame($ws->id, $employee->employeeSchedules()->value('work_schedule_id'));
    }

    public function test_store_switches_schedule_preserving_history(): void
    {
        $ws1 = WorkSchedule::factory()->create();
        $ws2 = WorkSchedule::factory()->create();
        $unit = OrgUnit::factory()->create();
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id, 'work_schedule_id' => $ws1->id,
            'effective_from' => '2026-01-01', 'effective_to' => null,
        ]);

        // Creating a schedule that overlaps the active one switches (close + open), keeping history.
        app(EmployeeScheduleService::class)->store([
            'employee_id' => $employee->id, 'work_schedule_id' => $ws2->id,
            'effective_from' => '2026-06-01', 'effective_to' => null,
        ]);

        $this->assertSame(2, $employee->employeeSchedules()->count());
        $old = EmployeeSchedule::where('employee_id', $employee->id)->where('work_schedule_id', $ws1->id)->first();
        $new = EmployeeSchedule::where('employee_id', $employee->id)->where('work_schedule_id', $ws2->id)->first();
        $this->assertSame('2026-05-31', $old->effective_to->toDateString());
        $this->assertNull($new->effective_to);
    }
}

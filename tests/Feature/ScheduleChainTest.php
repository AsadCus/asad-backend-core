<?php

namespace Tests\Feature;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleChainTest extends TestCase
{
    use RefreshDatabase;

    /** A23 — the work-schedule form can set the weekly day→shift pattern. */
    public function test_work_schedule_store_upserts_weekly_days(): void
    {
        $shift = Shift::factory()->create();

        $ws = app(WorkScheduleService::class)->store([
            'name' => 'Office Mon–Fri',
            'code' => 'OFF',
            'is_active' => true,
            'days' => [
                ['day_of_week' => 1, 'shift_id' => $shift->id, 'is_workday' => true],
                ['day_of_week' => 0, 'shift_id' => null, 'is_workday' => false],
            ],
        ]);

        $this->assertDatabaseHas('work_schedule_days', [
            'work_schedule_id' => $ws->id,
            'day_of_week' => 1,
            'shift_id' => $shift->id,
            'is_workday' => true,
        ]);

        $detail = app(WorkScheduleService::class)->getForEditShow($ws->id);
        $monday = collect($detail['days'])->firstWhere('day_of_week', 1);
        $this->assertSame($shift->id, $monday['shift_id']);
        $this->assertTrue($monday['is_workday']);
    }

    /** A2 — the reported bug: place an employee first, then set the unit default → they get a schedule. */
    public function test_setting_unit_default_after_placement_seeds_existing_members(): void
    {
        $ws = WorkSchedule::factory()->create();
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => null]);
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id, 'hire_date' => '2026-01-01']);

        // Nothing seeded yet (unit had no default at placement time).
        $this->assertSame(0, $employee->employeeSchedules()->count());

        app(OrgUnitService::class)->update([
            'type' => $unit->type->value,
            'parent_id' => $unit->parent_id,
            'name' => $unit->name,
            'code' => $unit->code,
            'default_work_schedule_id' => $ws->id,
            'is_active' => true,
        ], $unit->id);

        $this->assertSame($ws->id, $employee->fresh()->employeeSchedules()->value('work_schedule_id'));
    }

    /** C — creating a schedule for someone already scheduled switches (closes old, opens new). */
    public function test_employee_schedule_store_switches_when_active_exists(): void
    {
        $ws1 = WorkSchedule::factory()->create();
        $ws2 = WorkSchedule::factory()->create();
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => null]);
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $ws1->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        app(EmployeeScheduleService::class)->store([
            'employee_id' => $employee->id,
            'work_schedule_id' => $ws2->id,
            'effective_from' => '2026-06-01',
        ]);

        $this->assertSame(2, $employee->employeeSchedules()->count());
        $old = EmployeeSchedule::where('employee_id', $employee->id)->where('work_schedule_id', $ws1->id)->first();
        $this->assertSame('2026-05-31', $old->effective_to->toDateString());
    }

    /** B1 — one form: EmployeeService creates the login + role + profile with an auto employee_no. */
    public function test_employee_service_creates_account_role_and_auto_employee_no(): void
    {
        Role::create(['name' => 'employee', 'guard_name' => 'web']);
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => null]);

        $employee = app(EmployeeService::class)->store([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'secret123',
            'role' => 'employee',
            'hire_date' => '2026-01-01',
            'employment_status' => 'permanent',
            'org_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'name' => 'Budi']);
        $this->assertTrue($employee->user->hasRole('employee'));
        $this->assertMatchesRegularExpression('/^EMP-\d{4}$/', $employee->employee_no);
    }

    /** End-to-end: Shift → WorkSchedule(days) → OrgUnit default → Employee → resolveShift. */
    public function test_full_chain_resolves_shift_for_a_workday(): void
    {
        $shift = Shift::factory()->create(['start_time' => '08:00:00', 'end_time' => '17:00:00']);

        $ws = app(WorkScheduleService::class)->store([
            'name' => 'Office', 'code' => 'OFFICE', 'is_active' => true,
            'days' => [['day_of_week' => 1, 'shift_id' => $shift->id, 'is_workday' => true]],
        ]);

        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => $ws->id]);
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id, 'hire_date' => '2026-01-01']);

        // The seed produced an active schedule.
        $this->assertSame($ws->id, $employee->fresh()->employeeSchedules()->value('work_schedule_id'));

        // 2026-06-08 is a Monday (day_of_week 1). The attendance layer resolves the shift.
        $resolve = new \ReflectionMethod(AttendanceService::class, 'resolveShift');
        $resolve->setAccessible(true);
        $resolved = $resolve->invoke(app(AttendanceService::class), $employee->fresh(), Carbon::parse('2026-06-08'));

        $this->assertNotNull($resolved, 'expected the chain to resolve a shift');
        $this->assertSame($shift->id, $resolved->id);
    }
}

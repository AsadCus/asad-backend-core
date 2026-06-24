<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OrgUnit;
use App\Models\WorkSchedule;
use App\Services\EmployeeScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_employee_in_unit_with_default_seeds_schedule(): void
    {
        $ws = WorkSchedule::factory()->create();
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => $ws->id]);

        $employee = Employee::factory()->create(['org_unit_id' => $unit->id]);

        $this->assertDatabaseHas('employee_schedules', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $ws->id,
            'effective_to' => null,
        ]);
    }

    public function test_default_is_inherited_from_ancestor(): void
    {
        $ws = WorkSchedule::factory()->create();
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'default_work_schedule_id' => $ws->id]);
        $dept = OrgUnit::factory()->create([
            'type' => OrgUnitType::Department,
            'parent_id' => $holding->id,
            'default_work_schedule_id' => null,
        ]);

        $employee = Employee::factory()->create(['org_unit_id' => $dept->id]);

        $this->assertSame($ws->id, $employee->employeeSchedules()->value('work_schedule_id'));
    }

    public function test_no_default_anywhere_seeds_nothing(): void
    {
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => null]);

        Employee::factory()->create(['org_unit_id' => $unit->id]);

        $this->assertDatabaseCount('employee_schedules', 0);
    }

    public function test_transfer_does_not_overwrite_active_schedule(): void
    {
        $ws1 = WorkSchedule::factory()->create();
        $ws2 = WorkSchedule::factory()->create();
        $unit1 = OrgUnit::factory()->create(['default_work_schedule_id' => $ws1->id]);
        $unit2 = OrgUnit::factory()->create(['default_work_schedule_id' => $ws2->id]);

        $employee = Employee::factory()->create(['org_unit_id' => $unit1->id]);
        $this->assertSame($ws1->id, $employee->employeeSchedules()->value('work_schedule_id'));

        $employee->update(['org_unit_id' => $unit2->id]);

        $this->assertSame(1, $employee->fresh()->employeeSchedules()->count());
        $this->assertSame($ws1->id, $employee->employeeSchedules()->value('work_schedule_id'));
    }

    public function test_resolve_default_work_schedule_id_walks_ancestors(): void
    {
        $ws = WorkSchedule::factory()->create();
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'default_work_schedule_id' => $ws->id]);
        $bu = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $dept = OrgUnit::factory()->create(['type' => OrgUnitType::Department, 'parent_id' => $bu->id]);

        $this->assertSame($ws->id, $dept->resolveDefaultWorkScheduleId());
        $this->assertNull(OrgUnit::factory()->create(['default_work_schedule_id' => null])->resolveDefaultWorkScheduleId());
    }

    public function test_change_schedule_closes_prior_period_and_opens_new(): void
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

        app(EmployeeScheduleService::class)->changeSchedule($employee->id, $ws2->id, '2026-06-01');

        $old = EmployeeSchedule::where('employee_id', $employee->id)->where('work_schedule_id', $ws1->id)->first();
        $new = EmployeeSchedule::where('employee_id', $employee->id)->where('work_schedule_id', $ws2->id)->first();

        $this->assertSame('2026-05-31', $old->effective_to->toDateString());
        $this->assertSame('2026-06-01', $new->effective_from->toDateString());
        $this->assertNull($new->effective_to);
        $this->assertSame(2, $employee->employeeSchedules()->count());
    }
}

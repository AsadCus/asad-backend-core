<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Enums\OrgUnitType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrgUnit;
use App\Models\Shift;
use App\Models\User;
use App\Models\WfhVisitRequest;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overview_exposes_org_breakdown_work_location_and_scheduled_shift(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding]);
        $bu = OrgUnit::factory()->type(OrgUnitType::BusinessUnit, $holding)->create();
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch, $bu)->create([
            'has_location' => true,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'geofence_radius_meters' => 100,
        ]);
        $department = OrgUnit::factory()->type(OrgUnitType::Department, $branch)->create();
        $division = OrgUnit::factory()->type(OrgUnitType::Division, $department)->create();

        $supUser = User::factory()->create();
        $supUser->assignRole('supervisor');
        $supervisor = Employee::query()->create([
            'employee_no' => 'EMP-SUP1',
            'hire_date' => '2024-01-01',
            'user_id' => $supUser->id,
            'org_unit_id' => $division->id,
        ]);

        $empUser = User::factory()->create();
        $empUser->assignRole('employee');
        $employee = Employee::query()->create([
            'employee_no' => 'EMP-0001',
            'nik' => '3201019001010001',
            'hire_date' => '2024-01-01',
            'user_id' => $empUser->id,
            'org_unit_id' => $division->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // WorkScheduleFactory auto-creates all 7 WorkScheduleDay rows (Mon-Fri workdays) using
        // this shift — force today's row to be a workday with this shift regardless of which
        // weekday the test happens to run on.
        $shift = Shift::factory()->create(['name' => 'Morning Shift']);
        $workSchedule = WorkSchedule::factory()->create();
        $today = Carbon::today();
        WorkScheduleDay::query()
            ->where('work_schedule_id', $workSchedule->id)
            ->where('day_of_week', $today->dayOfWeek)
            ->update(['is_workday' => true, 'shift_id' => $shift->id]);
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $workSchedule->id,
            'effective_from' => $today->copy()->subDays(10),
            'effective_to' => null,
        ]);

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview')->assertOk();

        $member = $response->json('members.0');
        $this->assertSame('3201019001010001', $member['nik']);
        $this->assertSame($bu->name, $member['business_unit']);
        $this->assertSame($branch->name, $member['branch']);
        $this->assertSame($department->name, $member['department']);
        $this->assertSame($division->name, $member['division']);
        $this->assertSame($branch->name, $member['work_location']);
        // No attendance recorded today — shift still surfaces from the assigned schedule.
        $this->assertSame('Morning Shift', $member['shift']);
    }

    public function test_week_period_counts_instances_per_day_and_groups_leave_by_request(): void
    {
        // Freeze "today" to a known Thursday so Mon–Thu (the week-to-date range) is deterministic.
        Carbon::setTestNow(Carbon::create(2026, 6, 25));

        $supUser = User::factory()->create();
        $supUser->assignRole('supervisor');
        $supervisor = Employee::query()->create([
            'employee_no' => 'EMP-SUP2',
            'hire_date' => '2024-01-01',
            'user_id' => $supUser->id,
        ]);

        $empUser = User::factory()->create();
        $empUser->assignRole('employee');
        $employee = Employee::query()->create([
            'employee_no' => 'EMP-0002',
            'hire_date' => '2024-01-01',
            'user_id' => $empUser->id,
            'supervisor_id' => $supervisor->id,
        ]);

        // WorkScheduleFactory auto-creates Mon–Fri workdays sharing whichever Shift exists first.
        $shift = Shift::factory()->create(['name' => 'Morning Shift']);
        $workSchedule = WorkSchedule::factory()->create();
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $workSchedule->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        // Mon 22nd: present. Tue 23rd: late. Wed 24th: no record at all -> absent.
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-06-22',
            'shift_id' => $shift->id,
            'check_in_at' => '2026-06-22 08:00:00',
            'status' => AttendanceStatus::Present->value,
        ]);
        Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-06-23',
            'shift_id' => $shift->id,
            'check_in_at' => '2026-06-23 08:25:00',
            'status' => AttendanceStatus::Late->value,
        ]);

        // Thu 25th (today): on approved leave — spans into Friday, outside the capped range.
        $leaveType = LeaveType::factory()->create();
        LeaveRequest::create([
            'request_no' => 'LR-TEST-0001',
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-25',
            'end_date' => '2026-06-26',
            'days' => 2,
            'reason' => 'Acara keluarga',
            'status' => ApprovalStatus::Approved->value,
        ]);

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview?period=week')->assertOk();

        $response->assertJsonPath('period', 'week');
        $response->assertJsonPath('range.start', '2026-06-22');
        $response->assertJsonPath('range.end', '2026-06-25');

        $summary = $response->json('summary');
        $this->assertSame(2, $summary['present']); // Present (Mon) + Late (Tue) both count as "present-like"
        $this->assertSame(1, $summary['late']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(1, $summary['on_leave']); // one day-instance (Thursday) within the capped range

        $onLeave = $response->json('details.on_leave');
        $this->assertCount(1, $onLeave); // grouped by request, not exploded per day
        $this->assertSame('Acara keluarga', $onLeave[0]['reason']);
        $this->assertSame('2026-06-25', $onLeave[0]['start_date']);
        $this->assertSame('2026-06-26', $onLeave[0]['end_date']);

        $this->assertCount(2, $response->json('details.present'));
        $this->assertCount(1, $response->json('details.late'));
        $this->assertCount(1, $response->json('details.absent'));
        $this->assertSame('2026-06-24', $response->json('details.absent.0.date'));

        $member = $response->json('members.0');
        $this->assertSame(2, $member['present_count']);
        $this->assertSame(1, $member['late_count']);
        $this->assertSame(1, $member['absent_count']);
        $this->assertSame(1, $member['on_leave_count']);
    }

    /** A supervisor + one direct report on a Mon–Fri schedule. */
    private function makeSupervisorWithSubordinate(): array
    {
        $supUser = User::factory()->create();
        $supUser->assignRole('supervisor');
        $supervisor = Employee::query()->create([
            'employee_no' => 'EMP-SUP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $supUser->id,
        ]);

        $empUser = User::factory()->create();
        $empUser->assignRole('employee');
        $employee = Employee::query()->create([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $empUser->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $shift = Shift::factory()->create();
        $workSchedule = WorkSchedule::factory()->create();
        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $workSchedule->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        return [$supUser, $supervisor, $employee, $shift];
    }

    public function test_today_status_shows_wfh_instead_of_present_for_an_approved_wfh_subordinate(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 25, 9, 0, 0)); // a Thursday — a workday
        [$supUser, , $employee] = $this->makeSupervisorWithSubordinate();

        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-OVERVIEW-1',
            'employee_id' => $employee->id,
            'type' => 'wfh',
            'start_date' => '2026-06-25',
            'end_date' => '2026-06-25',
            'total_days' => 1,
            'reason' => 'Test WFH.',
            'status' => ApprovalStatus::Approved->value,
        ]);

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview')->assertOk();

        $this->assertSame('WFH', $response->json('members.0.status'));
        $this->assertSame(1, $response->json('summary.wfh'));
        $this->assertSame(0, $response->json('summary.present'));
    }

    public function test_today_status_is_holiday_not_absent_when_no_record_on_a_holiday(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 25, 9, 0, 0));
        [$supUser] = $this->makeSupervisorWithSubordinate();
        Holiday::query()->create(['name' => 'Test Holiday', 'date' => '2026-06-25', 'is_recurring' => false]);

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview')->assertOk();

        $this->assertSame('Holiday', $response->json('members.0.status'));
    }

    public function test_today_status_is_weekend_not_absent_on_a_scheduled_rest_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 27, 9, 0, 0)); // a Saturday
        [$supUser] = $this->makeSupervisorWithSubordinate();

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview')->assertOk();

        $this->assertSame('Weekend', $response->json('members.0.status'));
    }

    public function test_week_period_counts_wfh_and_visit_separately_from_present_and_absent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 25)); // Thursday — Mon-Thu range
        [$supUser, , $employee] = $this->makeSupervisorWithSubordinate();

        // Mon 22nd: WFH. Tue 23rd: Visit. Wed 24th: nothing -> absent. Thu 25th: nothing -> absent.
        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-OVERVIEW-2', 'employee_id' => $employee->id, 'type' => 'wfh',
            'start_date' => '2026-06-22', 'end_date' => '2026-06-22', 'total_days' => 1,
            'reason' => 'Test.', 'status' => ApprovalStatus::Approved->value,
        ]);
        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-OVERVIEW-3', 'employee_id' => $employee->id, 'type' => 'visit',
            'start_date' => '2026-06-23', 'end_date' => '2026-06-23', 'total_days' => 1,
            'reason' => 'Test.', 'status' => ApprovalStatus::Approved->value, 'geotag_mode' => 'open',
        ]);

        $this->actingAs($supUser, 'sanctum');
        $response = $this->getJson('/api/team/overview?period=week')->assertOk();

        $summary = $response->json('summary');
        $this->assertSame(1, $summary['wfh']);
        $this->assertSame(1, $summary['visit']);
        $this->assertSame(2, $summary['absent']);

        $member = $response->json('members.0');
        $this->assertSame(1, $member['wfh_count']);
        $this->assertSame(1, $member['visit_count']);
    }
}

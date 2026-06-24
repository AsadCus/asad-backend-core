<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OrgUnit;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AttendanceDayStatusTest extends TestCase
{
    use RefreshDatabase;

    // 2026-06-15 is a Monday (workday); 2026-06-14 is a Sunday (rest day in the Mon–Fri factory schedule).
    private const WORKDAY = '2026-06-15';

    private const REST_DAY = '2026-06-14';

    private function employeeWithSchedule(): Employee
    {
        $ws = WorkSchedule::factory()->create(); // Mon–Fri workdays, Sat/Sun off
        $unit = OrgUnit::factory()->create(['default_work_schedule_id' => null]);
        $employee = Employee::factory()->create(['org_unit_id' => $unit->id]);

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $ws->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        return $employee;
    }

    private function importNoCheckIn(Employee $employee, string $date): AttendanceStatus
    {
        $csv = "employee_no,date,check_in,check_out\n{$employee->employee_no},{$date},,\n";
        $file = UploadedFile::fake()->createWithContent('att.csv', $csv);

        app(AttendanceService::class)->import($file);

        return $employee->attendances()->whereDate('date', $date)->first()->status;
    }

    public function test_workday_no_check_in_is_absent(): void
    {
        $employee = $this->employeeWithSchedule();
        $this->assertSame(AttendanceStatus::Absent, $this->importNoCheckIn($employee, self::WORKDAY));
    }

    public function test_rest_day_no_check_in_is_weekend(): void
    {
        $employee = $this->employeeWithSchedule();
        $this->assertSame(AttendanceStatus::Weekend, $this->importNoCheckIn($employee, self::REST_DAY));
    }

    public function test_holiday_takes_precedence_over_workday(): void
    {
        $employee = $this->employeeWithSchedule();
        Holiday::factory()->create(['date' => self::WORKDAY, 'is_recurring' => false]);

        $this->assertSame(AttendanceStatus::Holiday, $this->importNoCheckIn($employee, self::WORKDAY));
    }

    public function test_recurring_holiday_matches_month_and_day(): void
    {
        $employee = $this->employeeWithSchedule();
        Holiday::factory()->create(['date' => '2000-06-15', 'is_recurring' => true]);

        $this->assertSame(AttendanceStatus::Holiday, $this->importNoCheckIn($employee, self::WORKDAY));
    }

    public function test_approved_leave_is_on_leave(): void
    {
        $employee = $this->employeeWithSchedule();
        LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => ApprovalStatus::Approved->value,
            'start_date' => self::WORKDAY,
            'end_date' => '2026-06-16',
        ]);

        $this->assertSame(AttendanceStatus::OnLeave, $this->importNoCheckIn($employee, self::WORKDAY));
    }

    public function test_pending_leave_is_not_on_leave(): void
    {
        $employee = $this->employeeWithSchedule();
        LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => ApprovalStatus::PendingSupervisor->value,
            'start_date' => self::WORKDAY,
            'end_date' => self::WORKDAY,
        ]);

        $this->assertSame(AttendanceStatus::Absent, $this->importNoCheckIn($employee, self::WORKDAY));
    }
}

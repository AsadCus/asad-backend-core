<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceCorrectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function makeEmployeeUser(string $role, array $attrs = []): array
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $employee = Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ], $attrs));

        return [$user, $employee];
    }

    public function test_full_correction_workflow_updates_attendance(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser, $employee] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-06-10',
            'check_out_at' => null,
        ]);

        // 1. Employee submits a missed check-out correction.
        $this->actingAs($empUser, 'sanctum');
        $create = $this->postJson('/api/attendance-corrections', [
            'attendance_id' => $attendance->id,
            'date' => '2026-06-10',
            'correction_type' => 'missed_check_out',
            'requested_check_out' => '2026-06-10 17:05:00',
            'reason' => 'Forgot to clock out.',
        ])->assertCreated()->assertJsonFragment(['status' => 'Pending Supervisor']);
        $id = $create->json('id');

        // 2. Supervisor approves → pending HR.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/approve", ['note' => 'ok'])
            ->assertOk()->assertJsonFragment(['status' => 'Pending HR']);

        // 3. HR verifies → approved, and the attendance row gets the corrected check-out.
        $this->actingAs($hrUser, 'sanctum');
        $verify = $this->postJson("/api/attendance-corrections/{$id}/verify", ['note' => 'verified'])
            ->assertOk()->assertJsonFragment(['status' => 'Approved']);

        $attendance->refresh();
        $this->assertNotNull($attendance->check_out_at);
        $this->assertSame('2026-06-10 17:05:00', $attendance->check_out_at->format('Y-m-d H:i:s'));

        // The response exposes which attendance row it touched, so the UI can link to it.
        $this->assertSame($attendance->id, $verify->json('attendance_id'));
    }

    /** Employee on a real shift (08:00-17:00, 15min tolerance) every weekday. */
    private function makeShiftedEmployee(string $role, array $attrs = []): array
    {
        $shift = Shift::query()->create([
            'name' => 'Office', 'code' => 'OFF', 'start_time' => '08:00', 'end_time' => '17:00',
            'late_tolerance_minutes' => 15, 'is_active' => true,
        ]);
        [$user, $employee] = $this->makeEmployeeUser($role, $attrs);

        $schedule = WorkSchedule::query()->create(['name' => 'Std', 'code' => 'STD', 'is_active' => true]);
        foreach (range(0, 6) as $dow) {
            WorkScheduleDay::query()->create([
                'work_schedule_id' => $schedule->id,
                'day_of_week' => $dow,
                'shift_id' => $shift->id,
                'is_workday' => true,
            ]);
        }
        $employee->employeeSchedules()->create([
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return [$user, $employee, $shift];
    }

    public function test_approved_missed_check_in_correction_still_flags_lateness_against_the_real_shift(): void
    {
        // The corrected punch (09:00) is genuinely late against an 08:00 shift with 15min
        // tolerance — approving the correction must not wave that away as a clean Present,
        // or the export would show a late employee as on-time.
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0));
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser, $employee] = $this->makeShiftedEmployee('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10',
            'correction_type' => 'missed_check_in',
            'requested_check_in' => '2026-06-10 09:00:00',
            'reason' => 'Forgot to clock in.',
        ])->assertCreated()->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/approve", ['note' => 'ok'])->assertOk();

        $this->actingAs($hrUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/verify", ['note' => 'verified'])->assertOk();

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-06-10')->firstOrFail();
        $this->assertSame('Late', $attendance->status->label());
        $this->assertSame(60, $attendance->late_minutes);

        Carbon::setTestNow();
    }

    public function test_approved_missed_check_out_correction_flags_an_early_leave_against_the_real_shift(): void
    {
        // Leaving at 15:00 against a 17:00 shift end is a genuine early leave — approving the
        // correction must recompute early_leave_minutes/status, not just patch check_out_at.
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 18, 0, 0));
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser, $employee, $shift] = $this->makeShiftedEmployee('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-06-10',
            'shift_id' => $shift->id,
            'check_in_at' => '2026-06-10 08:00:00',
            'check_out_at' => null,
            'status' => 'present',
        ]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'attendance_id' => $attendance->id,
            'date' => '2026-06-10',
            'correction_type' => 'missed_check_out',
            'requested_check_out' => '2026-06-10 15:00:00',
            'reason' => 'Forgot to clock out before leaving early (approved).',
        ])->assertCreated()->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/approve", ['note' => 'ok'])->assertOk();

        $this->actingAs($hrUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/verify", ['note' => 'verified'])->assertOk();

        $attendance->refresh();
        $this->assertSame(420, $attendance->work_minutes); // 08:00 -> 15:00
        $this->assertSame(120, $attendance->early_leave_minutes); // 15:00 -> 17:00
        $this->assertSame('Early Leave', $attendance->status->label());

        Carbon::setTestNow();
    }

    public function test_supervisor_can_reject(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10', 'correction_type' => 'sick', 'reason' => 'Sick.',
        ])->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/reject", ['note' => 'no proof'])
            ->assertOk()->assertJsonFragment(['status' => 'Rejected']);
    }

    public function test_owner_can_cancel_while_pending(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10', 'correction_type' => 'other', 'reason' => 'Mistake.',
        ])->json('id');

        $this->postJson("/api/attendance-corrections/{$id}/cancel")
            ->assertOk()->assertJsonFragment(['status' => 'Cancelled']);
    }

    public function test_employee_only_sees_own_corrections(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        [$otherUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($otherUser, 'sanctum');
        $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-09', 'correction_type' => 'other', 'reason' => 'Other guy.',
        ])->assertCreated();

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10', 'correction_type' => 'other', 'reason' => 'Mine.',
        ])->assertCreated();

        $this->getJson('/api/attendance-corrections')->assertOk()->assertJsonCount(1);
    }

    public function test_wfh_correction_stores_attachment_and_exposes_its_url(): void
    {
        Storage::fake('public');
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $response = $this->post('/api/attendance-corrections', [
            'date' => '2026-06-10',
            'correction_type' => 'wfh',
            'reason' => 'Worked from home, see attached log.',
            'attachment' => UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf'),
        ])->assertCreated();

        $this->assertNotNull($response->json('attachment_url'));

        $correction = AttendanceCorrection::query()->findOrFail($response->json('id'));
        $this->assertNotNull($correction->attachment_path);
        Storage::disk('public')->assertExists($correction->attachment_path);
    }

    public function test_employee_cannot_approve(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10', 'correction_type' => 'other', 'reason' => 'Mine.',
        ])->json('id');

        $this->postJson("/api/attendance-corrections/{$id}/approve")->assertStatus(403);
    }

    public function test_submit_notifies_supervisor_and_approval_notifies_requester(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10', 'correction_type' => 'other', 'reason' => 'Mine.',
        ])->json('id');

        // Submitting notifies the supervisor.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $supUser->id]);

        // Supervisor approval notifies the requester.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/attendance-corrections/{$id}/approve", ['note' => 'ok'])->assertOk();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $empUser->id]);
    }

    public function test_employee_without_a_supervisor_can_submit_and_see_it_in_my(): void
    {
        // The notifier no-ops when there's no supervisor to notify; the submit must still persist.
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $this->assertNull($employee->supervisor_id);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/attendance-corrections', [
            'date' => '2026-06-10',
            'correction_type' => 'missed_check_out',
            'reason' => 'Forgot to clock out.',
        ])->assertCreated();

        $this->assertDatabaseHas('attendance_corrections', [
            'employee_id' => $employee->id,
            'status' => 'pending_supervisor',
        ]);

        $this->getJson('/api/attendance-corrections/my')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_my_returns_only_own_corrections(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        [, $otherEmployee] = $this->makeEmployeeUser('employee');

        AttendanceCorrection::factory()->count(2)->create(['employee_id' => $employee->id]);
        AttendanceCorrection::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->actingAs($empUser, 'sanctum');
        $this->getJson('/api/attendance-corrections/my')
            ->assertOk()
            ->assertJsonCount(2);
    }
}

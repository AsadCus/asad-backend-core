<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestApiTest extends TestCase
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

    /** Employee on a Mon–Fri schedule (Sat/Sun are scheduled rest days). */
    private function makeEmployeeWithWeekdaySchedule(string $role = 'employee', array $attrs = []): array
    {
        [$user, $employee] = $this->makeEmployeeUser($role, $attrs);

        $shift = Shift::query()->create([
            'name' => 'Office', 'code' => 'OFF-'.fake()->unique()->numerify('####'), 'is_active' => true,
            'start_time' => '08:00', 'end_time' => '17:00',
        ]);
        $schedule = WorkSchedule::query()->create(['name' => 'S', 'code' => 'S-'.fake()->unique()->numerify('####'), 'is_active' => true]);
        foreach (range(0, 6) as $dow) {
            $isWorkday = $dow >= 1 && $dow <= 5; // Mon(1)..Fri(5)
            WorkScheduleDay::query()->create([
                'work_schedule_id' => $schedule->id,
                'day_of_week' => $dow,
                'shift_id' => $isWorkday ? $shift->id : null,
                'is_workday' => $isWorkday,
            ]);
        }
        $employee->employeeSchedules()->create([
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2020-01-01',
        ]);

        return [$user, $employee];
    }

    public function test_leave_spanning_a_weekend_only_counts_working_days(): void
    {
        // Friday 12 Jun 2026 → Monday 15 Jun 2026 is 4 calendar days, but only Fri + Mon
        // (2 days) are actually scheduled working days for this employee.
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-12',
            'end_date' => '2026-06-15',
            'reason' => 'Long weekend trip.',
        ])->assertCreated()->assertJsonFragment(['days' => 2]);
    }

    public function test_leave_entirely_on_rest_days_is_rejected(): void
    {
        // Saturday 13 Jun – Sunday 14 Jun 2026: zero working days for this employee.
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-13',
            'end_date' => '2026-06-14',
            'reason' => 'Makes no sense.',
        ])->assertStatus(422);
    }

    public function test_leave_on_a_company_holiday_is_rejected(): void
    {
        // A single-day request that lands exactly on a company holiday has no working days
        // to spend leave on, regardless of the employee's own schedule.
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        Holiday::query()->create(['name' => 'Independence Day', 'date' => '2026-06-10', 'is_recurring' => false]);
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'reason' => 'Already a holiday.',
        ])->assertStatus(422);
    }

    public function test_full_leave_workflow_consumes_balance(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser, $employee] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $leaveType = LeaveType::factory()->create(['requires_balance' => true]);
        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated' => 12,
            'used' => 0,
        ]);

        // 1. Employee submits a 3-day leave request.
        $this->actingAs($empUser, 'sanctum');
        $create = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'reason' => 'Family trip.',
        ])->assertCreated()->assertJsonFragment(['status' => 'Pending Supervisor', 'days' => 3]);
        $id = $create->json('id');

        // 2. Supervisor approves → pending HR.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/leave-requests/{$id}/approve", ['note' => 'ok'])
            ->assertOk()->assertJsonFragment(['status' => 'Pending HR']);

        // 3. HR verifies → approved, and the balance is consumed.
        $this->actingAs($hrUser, 'sanctum');
        $this->postJson("/api/leave-requests/{$id}/verify", ['note' => 'verified'])
            ->assertOk()->assertJsonFragment(['status' => 'Approved']);

        $balance->refresh();
        $this->assertSame('3.00', $balance->used);
    }

    public function test_submission_blocked_when_balance_is_insufficient(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');

        $leaveType = LeaveType::factory()->create(['requires_balance' => true]);
        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated' => 2,
            'used' => 0,
        ]);

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12', // 3 days requested, only 2 remaining
            'reason' => 'Too long.',
        ])->assertStatus(422);
    }

    public function test_supervisor_can_reject(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'reason' => 'Personal.',
        ])->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/leave-requests/{$id}/reject", ['note' => 'denied'])
            ->assertOk()->assertJsonFragment(['status' => 'Rejected']);
    }

    public function test_owner_can_cancel_while_pending(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'reason' => 'Mistake.',
        ])->json('id');

        $this->postJson("/api/leave-requests/{$id}/cancel")
            ->assertOk()->assertJsonFragment(['status' => 'Cancelled']);
    }

    public function test_employee_only_sees_own_leave_requests(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        [$otherUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($otherUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-09', 'end_date' => '2026-06-09', 'reason' => 'Other guy.',
        ])->assertCreated();

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'Mine.',
        ])->assertCreated();

        $this->getJson('/api/leave-requests')->assertOk()->assertJsonCount(1);
    }

    public function test_employee_cannot_approve(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'Mine.',
        ])->json('id');

        $this->postJson("/api/leave-requests/{$id}/approve")->assertStatus(403);
    }

    public function test_submit_notifies_supervisor_and_approval_notifies_requester(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'Mine.',
        ])->json('id');

        // Submitting notifies the supervisor.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $supUser->id]);

        // Supervisor approval notifies the requester.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/leave-requests/{$id}/approve", ['note' => 'ok'])->assertOk();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $empUser->id]);
    }

    public function test_my_returns_only_own_leave_requests(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        [, $otherEmployee] = $this->makeEmployeeUser('employee');

        LeaveRequest::factory()->count(2)->create(['employee_id' => $employee->id]);
        LeaveRequest::factory()->create(['employee_id' => $otherEmployee->id]);

        $this->actingAs($empUser, 'sanctum');
        $this->getJson('/api/leave-requests/my')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_only_one_in_flight_request_at_a_time(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => false]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'First.',
        ])->assertCreated()->json('id');

        // A second submission while the first is still pending is rejected.
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-20', 'end_date' => '2026-06-20', 'reason' => 'Second.',
        ])->assertStatus(422)->assertJsonValidationErrors('leave_type_id');

        // After the first reaches a terminal state, the employee may submit again.
        $this->postJson("/api/leave-requests/{$id}/cancel")->assertOk();
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-20', 'end_date' => '2026-06-20', 'reason' => 'Third.',
        ])->assertCreated();
    }

    public function test_attachment_required_when_leave_type_demands_it(): void
    {
        Storage::fake('public');
        [$empUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => false, 'requires_attachment' => true]);

        $this->actingAs($empUser, 'sanctum');

        // Missing attachment → rejected with a field error.
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'Need doc.',
        ])->assertStatus(422)->assertJsonValidationErrors('attachment');

        // With an attachment → accepted.
        $this->post('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'With doc.',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_balance_reducing_type_blocked_without_allocation(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $leaveType = LeaveType::factory()->create(['requires_balance' => true]); // no balance row allocated

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/leave-requests', [
            'leave_type_id' => $leaveType->id, 'start_date' => '2026-06-10', 'end_date' => '2026-06-10', 'reason' => 'No balance.',
        ])->assertStatus(422)->assertJsonValidationErrors('leave_type_id');
    }

    public function test_requester_info_returns_identity_and_remaining_balances(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser, $employee] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $leaveType = LeaveType::factory()->create(['requires_balance' => true]);
        LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'year' => (int) date('Y'), 'allocated' => 12, 'used' => 2,
        ]);

        $this->actingAs($empUser, 'sanctum');
        $this->getJson('/api/leave-requests/requester-info')
            ->assertOk()
            ->assertJsonPath('employee_no', $employee->employee_no)
            ->assertJsonPath('supervisor', $supUser->name)
            ->assertJsonPath("balances.{$leaveType->id}", 10);
    }
}

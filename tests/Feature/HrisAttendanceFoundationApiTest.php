<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrisAttendanceFoundationApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        Role::findOrCreate('administrator', 'web')->update(['is_full_access' => true]);
        Role::findOrCreate('employee', 'web');

        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_employee_crud_with_soft_delete(): void
    {
        $this->actingAdmin();

        $create = $this->postJson('/api/master/employees', [
            'employee_no' => 'EMP-001',
            'hire_date' => '2024-01-15',
            'employment_status' => 'permanent',
            'is_active' => true,
        ]);
        $create->assertCreated();
        $id = $create->json('id');

        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP-001']);
        $this->getJson('/api/master/employees')->assertOk()->assertJsonFragment(['employee_no' => 'EMP-001']);

        $this->putJson("/api/master/employees/{$id}", [
            'employee_no' => 'EMP-001',
            'hire_date' => '2024-01-15',
            'employment_status' => 'contract',
        ])->assertOk();
        $this->assertDatabaseHas('employees', ['id' => $id, 'employment_status' => 'contract']);

        $this->deleteJson("/api/master/employees/{$id}")->assertOk();
        $this->assertSoftDeleted('employees', ['id' => $id]);
    }

    public function test_duplicate_employee_no_is_rejected(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/employees', [
            'employee_no' => 'DUP', 'hire_date' => '2024-01-01', 'employment_status' => 'permanent',
        ])->assertCreated();

        $this->postJson('/api/master/employees', [
            'employee_no' => 'DUP', 'hire_date' => '2024-01-01', 'employment_status' => 'permanent',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_no');
    }

    public function test_invalid_employment_status_is_rejected(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/employees', [
            'employee_no' => 'BAD', 'hire_date' => '2024-01-01', 'employment_status' => 'not-a-status',
        ])->assertStatus(422)->assertJsonValidationErrors('employment_status');
    }

    public function test_employees_options_endpoint_returns_value_label(): void
    {
        $this->actingAdmin();
        Employee::query()->create(['employee_no' => 'EMP-OPT', 'hire_date' => '2024-01-01']);

        $this->getJson('/api/master/employees/options')
            ->assertOk()
            ->assertJsonStructure([['value', 'label']]);
    }

    public function test_employee_schedule_crud(): void
    {
        $this->actingAdmin();
        $employee = Employee::query()->create(['employee_no' => 'EMP-SCH', 'hire_date' => '2024-01-01']);
        $schedule = WorkSchedule::query()->create(['name' => 'Standard', 'code' => 'STD', 'is_active' => true]);

        $this->postJson('/api/master/employee-schedules', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2024-02-01',
        ])->assertCreated();

        $this->assertDatabaseHas('employee_schedules', ['employee_id' => $employee->id, 'work_schedule_id' => $schedule->id]);

        // effective_to before effective_from is rejected.
        $this->postJson('/api/master/employee-schedules', [
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_from' => '2024-02-01',
            'effective_to' => '2024-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('effective_to');
    }

    public function test_approval_matrix_crud_with_unique_submitter_level(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/approval-matrices', [
            'submitter_level' => 'staff',
            'approver_1_level' => 'supervisor',
            'final_verifier_role' => 'hr',
        ])->assertCreated();

        $this->postJson('/api/master/approval-matrices', [
            'submitter_level' => 'staff',
            'approver_1_level' => 'manager',
            'final_verifier_role' => 'hr',
        ])->assertStatus(422)->assertJsonValidationErrors('submitter_level');
    }

    public function test_leave_balance_crud_with_compound_unique(): void
    {
        $this->actingAdmin();
        $employee = Employee::query()->create(['employee_no' => 'EMP-BAL', 'hire_date' => '2024-01-01']);
        $leaveType = LeaveType::query()->create(['name' => 'Annual', 'code' => 'ANL', 'is_active' => true]);

        $payload = [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated' => 12,
        ];

        $this->postJson('/api/master/leave-balances', $payload)->assertCreated();
        $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'year' => 2026]);

        // Same employee + leave type + year is rejected.
        $this->postJson('/api/master/leave-balances', $payload)
            ->assertStatus(422)->assertJsonValidationErrors('leave_type_id');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

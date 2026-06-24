<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
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
        $this->postJson("/api/attendance-corrections/{$id}/verify", ['note' => 'verified'])
            ->assertOk()->assertJsonFragment(['status' => 'Approved']);

        $attendance->refresh();
        $this->assertNotNull($attendance->check_out_at);
        $this->assertSame('2026-06-10 17:05:00', $attendance->check_out_at->format('Y-m-d H:i:s'));
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
}

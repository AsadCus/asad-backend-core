<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\User;
use App\Models\WfhVisitRequest;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Carbon\Carbon;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WfhVisitRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 6, 26, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

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

    /** Create an approved WFH/Visit request that covers today in the DB directly. */
    private function makeApprovedRequest(Employee $employee, array $attrs = []): WfhVisitRequest
    {
        $today = Carbon::today()->toDateString();

        return WfhVisitRequest::create(array_merge([
            'request_no' => 'WFH-TEST-'.fake()->unique()->numerify('####'),
            'employee_id' => $employee->id,
            'type' => 'wfh',
            'start_date' => $today,
            'end_date' => $today,
            'total_days' => 1,
            'reason' => 'Test request.',
            'status' => ApprovalStatus::Approved,
        ], $attrs));
    }

    private function photo(): string
    {
        return 'data:image/png;base64,'.base64_encode('fake-image-bytes');
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

    // ===========================================================================
    // WFH/Visit Request — submission & approval workflow
    // ===========================================================================

    public function test_full_wfh_approval_workflow(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        // 1. Employee submits a WFH request.
        $this->actingAs($empUser, 'sanctum');
        $create = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh',
            'start_date' => '2026-06-29',
            'end_date' => '2026-06-30',
            'reason' => 'Working from home this week.',
        ])->assertCreated()
            ->assertJsonFragment(['status_value' => 'pending_supervisor']);

        $id = $create->json('id');

        // 2. Supervisor approves → pending HR.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/approve", ['note' => 'Approved.'])
            ->assertOk()
            ->assertJsonFragment(['status_value' => 'pending_hr']);

        // 3. HR verifies → approved.
        $this->actingAs($hrUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/verify", ['note' => 'Verified.'])
            ->assertOk()
            ->assertJsonFragment(['status_value' => 'approved']);

        $this->assertDatabaseHas('wfh_visit_requests', [
            'id' => $id,
            'status' => 'approved',
        ]);
    }

    public function test_full_visit_approval_workflow_with_locked_location(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $this->actingAs($empUser, 'sanctum');
        $create = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'visit',
            'start_date' => '2026-06-27',
            'end_date' => '2026-06-27',
            'reason' => 'Client visit.',
            'location_address' => 'Jl. Sudirman No.1, Jakarta',
            'location_lat' => -6.2088,
            'location_lng' => 106.8456,
            'location_radius' => 200,
        ])->assertCreated()
            ->assertJsonPath('geotag_mode', 'locked')
            ->assertJsonFragment(['status_value' => 'pending_supervisor']);

        $id = $create->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/approve")->assertOk();

        $this->actingAs($hrUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/verify")->assertOk()
            ->assertJsonFragment(['status_value' => 'approved']);
    }

    public function test_visit_without_location_has_open_geotag_mode(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'visit',
            'start_date' => '2026-06-27',
            'end_date' => '2026-06-27',
            'reason' => 'Open field visit.',
        ])->assertCreated()
            ->assertJsonPath('geotag_mode', 'open');
    }

    public function test_supervisor_can_reject_at_first_stage(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => 'WFH needed.',
        ])->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/reject", ['note' => 'Not justified.'])
            ->assertOk()
            ->assertJsonFragment(['status_value' => 'rejected']);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $empUser->id]);
    }

    public function test_employee_can_cancel_while_pending_supervisor(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => 'Need to cancel.',
        ])->json('id');

        $this->postJson("/api/wfh-visit-requests/{$id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status_value' => 'cancelled']);
    }

    public function test_cannot_submit_overlapping_request(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-06', 'end_date' => '2026-07-10',
            'reason' => 'First request this week.',
        ])->assertCreated();

        // Fully inside the first range.
        $response = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'visit', 'start_date' => '2026-07-08', 'end_date' => '2026-07-08',
            'reason' => 'Client visit, same week.',
        ])->assertStatus(422);

        $this->assertStringContainsString(
            'You already have a Pending Supervisor WFH request',
            $response->json('message'),
        );
        $this->assertDatabaseCount('wfh_visit_requests', 1);
    }

    public function test_cannot_submit_request_that_partially_overlaps_existing_one(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-06', 'end_date' => '2026-07-10',
            'reason' => 'First request.',
        ])->assertCreated();

        // Starts before, ends inside — still overlaps.
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-04', 'end_date' => '2026-07-07',
            'reason' => 'Overlapping request.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('wfh_visit_requests', 1);
    }

    public function test_can_submit_adjacent_non_overlapping_request(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-06', 'end_date' => '2026-07-10',
            'reason' => 'First request.',
        ])->assertCreated();

        // Starts the very next day — back-to-back, no overlap.
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-11', 'end_date' => '2026-07-12',
            'reason' => 'Back-to-back request.',
        ])->assertCreated();

        $this->assertDatabaseCount('wfh_visit_requests', 2);
    }

    public function test_can_resubmit_same_dates_after_rejection_or_cancellation(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);
        $this->actingAs($empUser, 'sanctum');

        $rejectedId = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-06', 'end_date' => '2026-07-06',
            'reason' => 'First attempt.',
        ])->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$rejectedId}/reject", ['note' => 'Denied.'])
            ->assertOk();

        // Same date, after rejection — should be allowed again.
        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-06', 'end_date' => '2026-07-06',
            'reason' => 'Second attempt after rejection.',
        ])->assertCreated();

        $this->assertDatabaseCount('wfh_visit_requests', 2);
    }

    public function test_wfh_spanning_a_weekend_only_counts_working_days(): void
    {
        // Friday 12 Jun 2026 → Monday 15 Jun 2026 is 4 calendar days, but only Fri + Mon
        // (2 days) are actually scheduled working days for this employee.
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-12', 'end_date' => '2026-06-15',
            'reason' => 'WFH around the weekend.',
        ])->assertCreated()->assertJsonFragment(['total_days' => 2]);
    }

    public function test_wfh_entirely_on_rest_days_is_rejected(): void
    {
        // Saturday 13 Jun – Sunday 14 Jun 2026: zero working days for this employee.
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-13', 'end_date' => '2026-06-14',
            'reason' => 'Makes no sense.',
        ])->assertStatus(422);
    }

    public function test_visit_on_a_company_holiday_is_rejected(): void
    {
        [$empUser] = $this->makeEmployeeWithWeekdaySchedule();
        Holiday::query()->create(['name' => 'Independence Day', 'date' => '2026-06-10', 'is_recurring' => false]);
        $this->actingAs($empUser, 'sanctum');

        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'visit', 'start_date' => '2026-06-10', 'end_date' => '2026-06-10',
            'reason' => 'Already a holiday.',
        ])->assertStatus(422);
    }

    public function test_employee_only_sees_own_requests_via_my(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        [$otherUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($otherUser, 'sanctum');
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => "Other user's request.",
        ])->assertCreated();

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-07-01', 'end_date' => '2026-07-01',
            'reason' => 'My request.',
        ])->assertCreated();

        $this->getJson('/api/wfh-visit-requests/my')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_submission_notifies_supervisor_and_approval_notifies_requester(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => 'WFH from home today.',
        ])->json('id');

        // Submission must notify supervisor.
        $this->assertDatabaseHas('user_notifications', ['user_id' => $supUser->id]);

        // Approval must notify requester.
        $this->actingAs($supUser, 'sanctum');
        $this->postJson("/api/wfh-visit-requests/{$id}/approve", ['note' => 'ok'])->assertOk();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $empUser->id]);
    }

    public function test_employee_cannot_approve_their_own_request(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => 'Mine.',
        ])->json('id');

        $this->postJson("/api/wfh-visit-requests/{$id}/approve")->assertStatus(403);
    }

    public function test_attachment_stored_on_submission(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $response = $this->post('/api/wfh-visit-requests', [
            'type' => 'wfh',
            'start_date' => '2026-06-29',
            'end_date' => '2026-06-29',
            'reason' => 'Attached proof.',
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
        ])->assertCreated();

        $id = $response->json('id');
        $this->assertDatabaseHas('wfh_visit_request_attachments', [
            'wfh_visit_request_id' => $id,
            'stage' => 'submission',
        ]);
    }

    public function test_decision_attachment_stored_with_correct_stage(): void
    {
        [$supUser, $supervisor] = $this->makeEmployeeUser('supervisor');
        [$empUser] = $this->makeEmployeeUser('employee', ['supervisor_id' => $supervisor->id]);

        $this->actingAs($empUser, 'sanctum');
        $id = $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh', 'start_date' => '2026-06-29', 'end_date' => '2026-06-29',
            'reason' => 'Proof attached on approval.',
        ])->json('id');

        $this->actingAs($supUser, 'sanctum');
        $this->post("/api/wfh-visit-requests/{$id}/approve", [
            'note' => 'Approved with attachment.',
            'attachments' => [UploadedFile::fake()->image('sign.png')],
        ])->assertOk();

        $this->assertDatabaseHas('wfh_visit_request_attachments', [
            'wfh_visit_request_id' => $id,
            'stage' => 'supervisor',
            'uploader_id' => $supUser->id,
        ]);
    }

    public function test_multi_day_request_calculates_total_days(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/wfh-visit-requests', [
            'type' => 'wfh',
            'start_date' => '2026-06-29',
            'end_date' => '2026-07-03',
            'reason' => 'Five-day WFH.',
        ])->assertCreated()
            ->assertJsonPath('total_days', 5);
    }

    // ===========================================================================
    // Attendance today endpoint — WFH/Visit status surfacing
    // ===========================================================================

    public function test_today_shows_active_wfh_when_approved_request_covers_today(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $this->makeApprovedRequest($employee, ['type' => 'wfh', 'geotag_mode' => null]);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        $this->assertNotNull($response->json('active_wfh_visit'));
        $this->assertSame('wfh', $response->json('active_wfh_visit.type'));
        $this->assertNull($response->json('active_wfh_visit.geotag_mode'));
        $this->assertNull($response->json('pending_wfh_visit'));
    }

    public function test_today_shows_active_visit_with_locked_geotag_when_location_is_set(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $this->makeApprovedRequest($employee, [
            'type' => 'visit',
            'geotag_mode' => 'locked',
            'location_lat' => -6.2088,
            'location_lng' => 106.8456,
            'location_radius' => 200,
            'location_address' => 'Jl. Sudirman, Jakarta',
        ]);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        $this->assertSame('visit', $response->json('active_wfh_visit.type'));
        $this->assertSame('locked', $response->json('active_wfh_visit.geotag_mode'));
        $this->assertSame(-6.2088, $response->json('active_wfh_visit.location_lat'));
    }

    public function test_today_shows_active_visit_with_open_geotag_when_no_location(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $this->makeApprovedRequest($employee, [
            'type' => 'visit',
            'geotag_mode' => 'open',
        ]);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        $this->assertSame('visit', $response->json('active_wfh_visit.type'));
        $this->assertSame('open', $response->json('active_wfh_visit.geotag_mode'));
    }

    public function test_today_shows_pending_wfh_visit_when_not_yet_approved(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $today = Carbon::today()->toDateString();

        WfhVisitRequest::create([
            'request_no' => 'WFH-PENDING-001',
            'employee_id' => $employee->id,
            'type' => 'wfh',
            'start_date' => $today,
            'end_date' => $today,
            'total_days' => 1,
            'reason' => 'Awaiting approval.',
            'status' => ApprovalStatus::PendingSupervisor,
        ]);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        $this->assertNull($response->json('active_wfh_visit'));
        $this->assertNotNull($response->json('pending_wfh_visit'));
        $this->assertSame('wfh', $response->json('pending_wfh_visit.type'));
    }

    public function test_today_shows_no_wfh_visit_when_request_is_for_different_date(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');

        $this->makeApprovedRequest($employee, [
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-20',
        ]);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        $this->assertNull($response->json('active_wfh_visit'));
        $this->assertNull($response->json('pending_wfh_visit'));
    }

    public function test_today_approved_request_takes_precedence_over_pending(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $today = Carbon::today()->toDateString();

        // Pending request
        WfhVisitRequest::create([
            'request_no' => 'WFH-PEND-001',
            'employee_id' => $employee->id,
            'type' => 'wfh',
            'start_date' => $today,
            'end_date' => $today,
            'total_days' => 1,
            'reason' => 'Pending.',
            'status' => ApprovalStatus::PendingSupervisor,
        ]);

        // Approved request for the same day
        $this->makeApprovedRequest($employee, ['type' => 'visit', 'geotag_mode' => 'open']);

        $this->actingAs($empUser, 'sanctum');
        $response = $this->getJson('/api/attendances/today')->assertOk();

        // The approved request surfaces as active, pending is still visible
        $this->assertSame('visit', $response->json('active_wfh_visit.type'));
        $this->assertSame('wfh', $response->json('pending_wfh_visit.type'));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\OrgUnitType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\GhostUser;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
        Storage::fake('public');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeEmployeeUser(string $role = 'employee', array $attrs = []): array
    {
        $user = $this->makeUser($role);
        $employee = Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ], $attrs));

        return [$user, $employee];
    }

    private function photo(): string
    {
        return 'data:image/png;base64,'.base64_encode('fake-image-bytes');
    }

    public function test_employee_can_check_in_and_check_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 0, 0));
        [$user, $employee] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(), 'location' => 'Jakarta',
        ])->assertCreated()->assertJsonFragment(['status' => 'Present']);

        $row = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertNotNull($row->check_in_at);
        Storage::disk('public')->assertExists($row->check_in_photo_path);

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 17, 0, 0));
        $this->postJson('/api/attendances/check-out', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertOk();

        $row->refresh();
        $this->assertNotNull($row->check_out_at);
        $this->assertSame(540, $row->work_minutes); // 08:00 → 17:00
        Carbon::setTestNow();
    }

    public function test_late_status_is_derived_from_assigned_shift(): void
    {
        $now = Carbon::create(2026, 6, 15, 8, 20, 0);
        Carbon::setTestNow($now);
        [$user, $employee] = $this->makeEmployeeUser();

        $shift = Shift::query()->create([
            'name' => 'Office', 'code' => 'OFF', 'start_time' => '08:00', 'end_time' => '17:00',
            'late_tolerance_minutes' => 5, 'is_active' => true,
        ]);
        $schedule = WorkSchedule::query()->create(['name' => 'Std', 'code' => 'STD', 'is_active' => true]);
        WorkScheduleDay::query()->create([
            'work_schedule_id' => $schedule->id, 'day_of_week' => $now->dayOfWeek,
            'shift_id' => $shift->id, 'is_workday' => true,
        ]);
        $employee->employeeSchedules()->create([
            'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Late', 'late_minutes' => 20]);

        Carbon::setTestNow();
    }

    public function test_check_in_blocked_while_session_open(): void
    {
        [$user] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');

        $payload = ['lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo()];
        $this->postJson('/api/attendances/check-in', $payload)->assertCreated();
        // A second check-in is rejected while the first session is still open.
        $this->postJson('/api/attendances/check-in', $payload)->assertStatus(422);
    }

    public function test_can_check_in_again_after_check_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 0, 0));
        [$user, $employee] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');
        $payload = ['lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo()];

        $this->postJson('/api/attendances/check-in', $payload)->assertCreated();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
        $this->postJson('/api/attendances/check-out', $payload)->assertOk();

        // After checking out, a fresh check-in opens a second session the same day.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 13, 0, 0));
        $this->postJson('/api/attendances/check-in', $payload)->assertCreated();

        $row = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(1, Attendance::where('employee_id', $employee->id)->count());
        $this->assertSame(2, $row->sessions()->count());
        Carbon::setTestNow();
    }

    public function test_work_minutes_sums_sessions(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 0, 0));
        [$user, $employee] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');
        $payload = ['lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo()];

        // Session 1: 08:00 → 12:00 (240 min)
        $this->postJson('/api/attendances/check-in', $payload)->assertCreated();
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
        $this->postJson('/api/attendances/check-out', $payload)->assertOk();

        // Session 2: 13:00 → 17:00 (240 min)
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 13, 0, 0));
        $this->postJson('/api/attendances/check-in', $payload)->assertCreated();
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 17, 0, 0));
        $this->postJson('/api/attendances/check-out', $payload)->assertOk();

        $row = Attendance::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(480, $row->work_minutes); // 240 + 240
        $this->assertSame('08:00', $row->check_in_at->format('H:i')); // first in
        $this->assertSame('17:00', $row->check_out_at->format('H:i')); // last out
        Carbon::setTestNow();
    }

    public function test_check_in_requires_selfie_and_geolocation(): void
    {
        [$user] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', ['location' => 'Jakarta'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng', 'photo']);
    }

    public function test_employee_only_sees_own_rows_admin_sees_all(): void
    {
        [$me, $employee] = $this->makeEmployeeUser();
        [, $other] = $this->makeEmployeeUser();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-06-10']);
        Attendance::factory()->create(['employee_id' => $other->id, 'date' => '2026-06-10']);

        $this->actingAs($me, 'sanctum');
        $this->getJson('/api/attendances')->assertOk()->assertJsonCount(1);

        // A ghost is unbounded, so it sees every employee's rows. (A plain administrator is now
        // scoped like anyone else — it sees everything only when anchored at the org root.)
        $admin = $this->makeUser('administrator');
        GhostUser::create(['user_id' => (int) $admin->id]);
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/attendances')->assertOk()->assertJsonCount(2);
    }

    public function test_role_without_check_in_permission_is_forbidden(): void
    {
        // A user whose role lacks the HRIS check-in permission is stopped at the gate.
        $user = User::factory()->create();
        Employee::query()->create([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertStatus(403);
    }

    public function test_non_employee_role_can_check_in(): void
    {
        // All HRIS roles may punch now — supervisor/manager/hr were granted check-in.
        [$user] = $this->makeEmployeeUser('supervisor');
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_ineligible_employee_cannot_check_in(): void
    {
        // Admin-disabled eligibility blocks the punch even when the role permits it.
        [$user] = $this->makeEmployeeUser('employee', ['can_check_in' => false]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertStatus(403);
    }

    public function test_locked_employee_cannot_check_in(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser();
        $admin = $this->makeUser('administrator');

        $this->actingAs($admin, 'sanctum');
        $this->postJson("/api/attendance-locks/{$employee->id}", ['reason' => 'terlambat'])->assertOk();

        $this->actingAs($empUser, 'sanctum');
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertStatus(423);

        // HR unlock restores check-in. Re-resolve the user so the cached employee relation
        // (loaded during the locked attempt above) doesn't mask the unlock in this test process.
        $this->actingAs($admin, 'sanctum');
        $this->deleteJson("/api/attendance-locks/{$employee->id}")->assertOk();
        $this->actingAs(User::findOrFail($empUser->id), 'sanctum');
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_check_in_blocked_outside_branch_geofence(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true,
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'geofence_radius_meters' => 100,
        ]);
        [$user] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        // Roughly 11km from the branch — well outside the 100m radius.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.300000, 'lng' => 106.900000, 'photo' => $this->photo(),
        ])->assertStatus(422);
    }

    public function test_check_in_allowed_inside_branch_geofence(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true,
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'geofence_radius_meters' => 100,
        ]);
        [$user] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        // A few meters from the branch centroid — inside the 100m radius.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.200050, 'lng' => 106.800050, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_branch_without_location_skips_geofence_check(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create(['has_location' => false]);
        [$user] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.300000, 'lng' => 106.900000, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_branch_without_own_location_falls_back_to_business_unit_geofence(): void
    {
        // The branch has no geofence of its own — it inherits the business unit's default location.
        $businessUnit = OrgUnit::factory()->type(OrgUnitType::BusinessUnit)->create([
            'has_location' => true,
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'geofence_radius_meters' => 100,
        ]);
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch, $businessUnit)->create(['has_location' => false]);
        [$user] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        // Outside the business unit's radius.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.300000, 'lng' => 106.900000, 'photo' => $this->photo(),
        ])->assertStatus(422);

        // Inside the business unit's radius.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.200050, 'lng' => 106.800050, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    /** Approve a WFH/Visit request covering today for $employee, with the given attrs. */
    private function makeApprovedWfhVisit(Employee $employee, array $attrs = []): WfhVisitRequest
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

    public function test_check_in_outside_office_geofence_is_allowed_during_approved_wfh(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true, 'latitude' => -6.200000, 'longitude' => 106.800000, 'geofence_radius_meters' => 100,
        ]);
        [$user, $employee] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->makeApprovedWfhVisit($employee, ['type' => 'wfh']);
        $this->actingAs($user, 'sanctum');

        // Roughly 11km from the office, but today is an approved WFH day — no office lock.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.300000, 'lng' => 106.900000, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_check_in_outside_office_geofence_is_allowed_during_open_visit(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true, 'latitude' => -6.200000, 'longitude' => 106.800000, 'geofence_radius_meters' => 100,
        ]);
        [$user, $employee] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->makeApprovedWfhVisit($employee, ['type' => 'visit', 'geotag_mode' => 'open']);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.300000, 'lng' => 106.900000, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_check_in_for_locked_visit_is_checked_against_the_visit_pin_not_the_office(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true, 'latitude' => -6.200000, 'longitude' => 106.800000, 'geofence_radius_meters' => 100,
        ]);
        [$user, $employee] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->makeApprovedWfhVisit($employee, [
            'type' => 'visit',
            'geotag_mode' => 'locked',
            'location_lat' => -6.914744,
            'location_lng' => 107.609810,
            'location_radius' => 100,
        ]);
        $this->actingAs($user, 'sanctum');

        // Inside the office's own radius, but nowhere near the visit's pin — rejected.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.200050, 'lng' => 106.800050, 'photo' => $this->photo(),
        ])->assertStatus(422);
    }

    public function test_check_in_for_locked_visit_succeeds_within_the_visit_radius(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true, 'latitude' => -6.200000, 'longitude' => 106.800000, 'geofence_radius_meters' => 100,
        ]);
        [$user, $employee] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->makeApprovedWfhVisit($employee, [
            'type' => 'visit',
            'geotag_mode' => 'locked',
            'location_lat' => -6.914744,
            'location_lng' => 107.609810,
            'location_radius' => 100,
        ]);
        $this->actingAs($user, 'sanctum');

        // A few meters from the visit's own pin — far from the office, but allowed.
        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.914700, 'lng' => 107.609850, 'photo' => $this->photo(),
        ])->assertCreated();
    }

    public function test_today_exposes_resolved_work_location_for_the_map(): void
    {
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true,
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'geofence_radius_meters' => 150,
        ]);
        [$user] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/attendances/today')->assertOk()->assertJsonFragment([
            'work_location' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius_meters' => 150,
            ],
        ]);
    }

    public function test_show_returns_punch_photos_coordinates_and_resolved_work_location(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 0, 0));
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch)->create([
            'has_location' => true,
            'latitude' => -6.200000,
            'longitude' => 106.800000,
            'geofence_radius_meters' => 150,
        ]);
        [$user, $employee] = $this->makeEmployeeUser('employee', ['org_unit_id' => $branch->id]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(), 'location' => 'Jakarta HQ',
        ])->assertCreated();
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 17, 0, 0));
        $this->postJson('/api/attendances/check-out', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(), 'location' => 'Jakarta HQ',
        ])->assertOk();
        Carbon::setTestNow();

        $attendance = Attendance::where('employee_id', $employee->id)->firstOrFail();

        $response = $this->getJson("/api/attendances/{$attendance->id}")->assertOk();

        $response->assertJsonPath('check_in.location', 'Jakarta HQ');
        $response->assertJsonPath('check_in.lat', '-6.20000000');
        $response->assertJsonPath('check_out.location', 'Jakarta HQ');
        $this->assertNotNull($response->json('check_in.photo_url'));
        $this->assertNotNull($response->json('check_out.photo_url'));
        $response->assertJsonFragment([
            'work_location' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius_meters' => 150,
            ],
        ]);
    }

    public function test_show_is_forbidden_for_another_employees_record_without_view_scope(): void
    {
        [, $owner] = $this->makeEmployeeUser('employee');
        $attendance = Attendance::query()->create([
            'employee_id' => $owner->id,
            'date' => '2026-06-15',
            'status' => 'present',
        ]);

        [$viewer] = $this->makeEmployeeUser('employee');
        $this->actingAs($viewer, 'sanctum');

        $this->getJson("/api/attendances/{$attendance->id}")->assertForbidden();
    }

    /** Helper: set up employee + schedule with a given shift. */
    private function makeShiftedEmployee(array $shiftAttrs, string $role = 'employee'): array
    {
        $shift = Shift::query()->create(array_merge([
            'name' => 'Test', 'code' => 'TST', 'is_active' => true,
            'late_tolerance_minutes' => 15,
        ], $shiftAttrs));

        [$user, $employee] = $this->makeEmployeeUser($role);

        $schedule = WorkSchedule::query()->create(['name' => 'S', 'code' => 'S', 'is_active' => true]);
        // Cover all weekdays so the test date always resolves a shift.
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

    public function test_check_in_before_shift_start_is_early_check_in(): void
    {
        // Shift starts 08:00 → check-in at 07:45 must be Early Check In, not Present.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 7, 45, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Early Check In', 'late_minutes' => 0]);

        Carbon::setTestNow();
    }

    public function test_check_in_exactly_on_time_is_present(): void
    {
        // Check-in at the exact shift start (0 minutes from start) must be Present.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 0, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Present', 'late_minutes' => 0]);

        Carbon::setTestNow();
    }

    public function test_early_check_in_status_survives_a_normal_checkout(): void
    {
        // Checked in early (07:45) then checked out exactly on time (17:00) — the day should
        // still read "Early Check In", not be flattened to "Present" by the checkout.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 7, 45, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Early Check In']);

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 17, 0, 0));
        $this->postJson('/api/attendances/check-out', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertOk()->assertJsonFragment(['status' => 'Early Check In']);

        Carbon::setTestNow();
    }

    public function test_early_check_in_followed_by_early_leave_becomes_early_leave(): void
    {
        // Arriving early doesn't excuse leaving early — checking out at 16:30 (shift ends
        // 17:00) must override the Early Check In status with Early Leave.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 7, 45, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Early Check In']);

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 16, 30, 0));
        $this->postJson('/api/attendances/check-out', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertOk()->assertJsonFragment(['status' => 'Early Leave', 'early_leave_minutes' => 30]);

        Carbon::setTestNow();
    }

    public function test_late_check_in_status_survives_checkout(): void
    {
        // Late at check-in must stay Late at checkout even if the employee leaves on time —
        // it shouldn't be overwritten back to Present.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 16, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Late']);

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 17, 0, 0));
        $this->postJson('/api/attendances/check-out', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertOk()->assertJsonFragment(['status' => 'Late']);

        Carbon::setTestNow();
    }

    public function test_check_in_within_tolerance_is_present(): void
    {
        // Shift starts 08:00, tolerance 15 min → check-in at 08:14 must be Present.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 14, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Present', 'late_minutes' => 14]);

        Carbon::setTestNow();
    }

    public function test_check_in_one_minute_over_tolerance_is_late(): void
    {
        // Shift starts 08:00, tolerance 15 min → check-in at 08:16 must be Late.
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 8, 16, 0));
        [$user] = $this->makeShiftedEmployee(['start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/attendances/check-in', [
            'lat' => -6.2, 'lng' => 106.8, 'photo' => $this->photo(),
        ])->assertCreated()->assertJsonFragment(['status' => 'Late', 'late_minutes' => 16]);

        Carbon::setTestNow();
    }

    public function test_overnight_shift_csv_import_correct_work_minutes(): void
    {
        // Night shift 22:00 → 06:00 next day (8 hours = 480 minutes).
        $admin = $this->makeUser('administrator');
        Employee::query()->create(['employee_no' => 'EMP-NIGHT', 'hire_date' => '2024-01-01']);

        $csv = "employee_no,date,check_in,check_out\nEMP-NIGHT,2026-06-15,22:00,06:00\n";
        $file = UploadedFile::fake()->createWithContent('night.csv', $csv);

        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/attendances/import', ['file' => $file])
            ->assertOk()
            ->assertJsonFragment(['imported' => 1]);

        $row = Attendance::whereDate('date', '2026-06-15')->firstOrFail();
        $this->assertSame(480, $row->work_minutes);
        $this->assertSame('22:00', $row->check_in_at->format('H:i'));
        $this->assertSame('06:00', $row->check_out_at->format('H:i'));
        // check_out must be on the NEXT day.
        $this->assertSame('2026-06-16', $row->check_out_at->toDateString());
    }

    public function test_csv_import_upserts_attendance(): void
    {
        $admin = $this->makeUser('administrator');
        Employee::query()->create(['employee_no' => 'EMP-IMP', 'hire_date' => '2024-01-01']);

        $csv = "employee_no,date,check_in,check_out\nEMP-IMP,2026-06-01,08:05,17:02\n";
        $file = UploadedFile::fake()->createWithContent('att.csv', $csv);

        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/attendances/import', ['file' => $file])
            ->assertOk()
            ->assertJsonFragment(['imported' => 1]);

        $this->assertSame(1, Attendance::whereDate('date', '2026-06-01')->count());
    }

    // ===========================================================================
    // Daily report — Hadir/Telat/Alpha/Pulang Cepat/WFH/Visit/Cuti per day
    // ===========================================================================

    /** Employee on a Mon–Fri schedule (Sat/Sun are scheduled rest days), 08:00–17:00 shift. */
    private function makeWeekdayScheduledEmployee(string $role = 'employee', array $attrs = []): array
    {
        [$user, $employee] = $this->makeEmployeeUser($role, $attrs);

        $shift = Shift::query()->create([
            'name' => 'Office', 'code' => 'OFF-'.fake()->unique()->numerify('####'),
            'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true,
        ]);
        $schedule = WorkSchedule::query()->create(['name' => 'Std', 'code' => 'STD-'.fake()->unique()->numerify('####'), 'is_active' => true]);
        foreach (range(0, 6) as $dow) {
            $isWorkday = $dow >= 1 && $dow <= 5;
            WorkScheduleDay::query()->create([
                'work_schedule_id' => $schedule->id, 'day_of_week' => $dow,
                'shift_id' => $isWorkday ? $shift->id : null, 'is_workday' => $isWorkday,
            ]);
        }
        $employee->employeeSchedules()->create(['work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01']);

        return [$user, $employee];
    }

    public function test_report_classifies_each_day_of_the_week_correctly(): void
    {
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();

        // Mon 8 Jun: on-time check-in -> Present.
        Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'present',
        ]);
        // Tue 9 Jun: late check-in -> Late.
        Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => '2026-06-09', 'status' => 'late', 'late_minutes' => 20,
        ]);
        // Wed 10 Jun: a working day with no record at all -> Alpha (Absent).
        // Thu 11 Jun: approved leave -> Cuti.
        $leaveType = \App\Models\LeaveType::factory()->create(['requires_balance' => false]);
        \App\Models\LeaveRequest::create([
            'request_no' => 'LR-TEST-1', 'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-11', 'end_date' => '2026-06-11', 'days' => 1,
            'reason' => 'Test leave.', 'status' => 'approved',
        ]);
        // Fri 12 Jun: approved WFH -> WFH.
        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-1', 'employee_id' => $employee->id, 'type' => 'wfh',
            'start_date' => '2026-06-12', 'end_date' => '2026-06-12', 'total_days' => 1,
            'reason' => 'Test WFH.', 'status' => 'approved',
        ]);
        // Sat 13 / Sun 14: scheduled rest days, no record -> Weekend.

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-14')->assertOk();
        $byDate = collect($response->json())->keyBy('date');

        $this->assertSame('Present', $byDate['2026-06-08']['status']);
        $this->assertSame('Late', $byDate['2026-06-09']['status']);
        $this->assertSame(20, $byDate['2026-06-09']['late_minutes']);
        $this->assertSame('Absent', $byDate['2026-06-10']['status']);
        $this->assertSame('On Leave', $byDate['2026-06-11']['status']);
        $this->assertSame('WFH', $byDate['2026-06-12']['status']);
        $this->assertSame('Weekend', $byDate['2026-06-13']['status']);
        $this->assertSame('Weekend', $byDate['2026-06-14']['status']);
    }

    public function test_report_shows_visit_status_for_an_approved_visit(): void
    {
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();
        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-2', 'employee_id' => $employee->id, 'type' => 'visit',
            'start_date' => '2026-06-08', 'end_date' => '2026-06-08', 'total_days' => 1,
            'reason' => 'Client visit.', 'status' => 'approved', 'geotag_mode' => 'open',
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-08')->assertOk();
        $this->assertSame('Visit', $response->json('0.status'));
    }

    public function test_report_wfh_status_overrides_late_label_but_keeps_late_minutes(): void
    {
        // Checked in late while on an approved WFH day: the headline status reads WFH (the
        // day's defining fact), but the actual lateness stays visible in late_minutes.
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();
        Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'late', 'late_minutes' => 15,
        ]);
        WfhVisitRequest::create([
            'request_no' => 'WFH-TEST-3', 'employee_id' => $employee->id, 'type' => 'wfh',
            'start_date' => '2026-06-08', 'end_date' => '2026-06-08', 'total_days' => 1,
            'reason' => 'Test WFH.', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'sanctum');

        $row = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-08')->assertOk()->json('0');
        $this->assertSame('WFH', $row['status']);
        $this->assertSame(15, $row['late_minutes']);
    }

    public function test_report_defaults_to_the_current_month_when_no_range_given(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15));
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-06-10', 'status' => 'present']);
        // Outside the current month — must not appear.
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-05-10', 'status' => 'present']);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report')->assertOk();
        $dates = collect($response->json())->pluck('date');

        $this->assertTrue($dates->contains('2026-06-10'));
        $this->assertFalse($dates->contains('2026-05-10'));
        $this->assertTrue($dates->every(fn ($d) => str_starts_with($d, '2026-06')));
        Carbon::setTestNow();
    }

    public function test_report_filters_by_status(): void
    {
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'present']);
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-06-09', 'status' => 'late']);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-09&status=late')->assertOk();
        $rows = collect($response->json());

        $this->assertCount(1, $rows);
        $this->assertSame('Late', $rows->first()['status']);
    }

    public function test_report_scopes_to_own_employee_for_view_own_users(): void
    {
        [$me, $employee] = $this->makeWeekdayScheduledEmployee();
        [, $other] = $this->makeWeekdayScheduledEmployee();
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'present']);
        Attendance::factory()->create(['employee_id' => $other->id, 'date' => '2026-06-08', 'status' => 'present']);

        $this->actingAs($me, 'sanctum');
        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-08')->assertOk();

        $this->assertTrue(collect($response->json())->every(fn ($r) => $r['employee_id'] === $employee->id));
    }

    public function test_report_row_id_is_the_attendance_id_when_present_and_null_otherwise(): void
    {
        [$user, $employee] = $this->makeWeekdayScheduledEmployee();
        $attendance = Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'present',
        ]);
        // Wed 10 Jun: no record at all -> Alpha, with no underlying Attendance row.
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-10')->assertOk();
        $byDate = collect($response->json())->keyBy('date');

        $this->assertSame($attendance->id, $byDate['2026-06-08']['id']);
        $this->assertNull($byDate['2026-06-10']['id']);
    }

    public function test_report_row_exposes_the_employee_org_unit_breakdown(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding]);
        $bu = OrgUnit::factory()->type(OrgUnitType::BusinessUnit, $holding)->create();
        $branch = OrgUnit::factory()->type(OrgUnitType::Branch, $bu)->create();
        $department = OrgUnit::factory()->type(OrgUnitType::Department, $branch)->create();
        $division = OrgUnit::factory()->type(OrgUnitType::Division, $department)->create();

        [$user, $employee] = $this->makeWeekdayScheduledEmployee('employee', ['org_unit_id' => $division->id]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-08')->assertOk();
        $row = $response->json('0');

        $this->assertSame($bu->name, $row['business_unit']);
        $this->assertSame($branch->name, $row['branch']);
        $this->assertSame($department->name, $row['department']);
        $this->assertSame($division->name, $row['division']);
    }

    public function test_report_row_exposes_nik_shift_hours_and_punch_locations(): void
    {
        [$user, $employee] = $this->makeWeekdayScheduledEmployee('employee', ['nik' => '3201019001010099']);
        Attendance::factory()->create([
            'employee_id' => $employee->id, 'date' => '2026-06-08', 'status' => 'present',
            'check_in_location' => 'Margo City, Depok', 'check_out_location' => 'Margo City, Depok',
        ]);
        // Wed 10 Jun: scheduled workday, no check-in at all.
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/attendances/report?from=2026-06-08&to=2026-06-10')->assertOk();
        $byDate = collect($response->json())->keyBy('date');

        $checkedIn = $byDate['2026-06-08'];
        $this->assertSame('3201019001010099', $checkedIn['nik']);
        $this->assertSame('08:00', substr((string) $checkedIn['shift_start_time'], 0, 5));
        $this->assertSame('17:00', substr((string) $checkedIn['shift_end_time'], 0, 5));
        $this->assertSame('Margo City, Depok', $checkedIn['check_in_location']);
        $this->assertSame('Margo City, Depok', $checkedIn['check_out_location']);

        // No punch at all, but still a scheduled workday — shift hours still surface.
        $noPunch = $byDate['2026-06-10'];
        $this->assertSame('08:00', substr((string) $noPunch['shift_start_time'], 0, 5));
        $this->assertNull($noPunch['check_in_location']);
    }
}

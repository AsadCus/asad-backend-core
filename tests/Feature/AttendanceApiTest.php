<?php

namespace Tests\Feature;

use App\Models\Attendance;
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

        $admin = $this->makeUser('administrator');
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
}

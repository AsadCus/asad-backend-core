<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeScheduleImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        return $user;
    }

    private function makeEmployee(string $no): Employee
    {
        return Employee::query()->create(['employee_no' => $no, 'hire_date' => '2024-01-01']);
    }

    private function makeSchedule(string $code, string $name): WorkSchedule
    {
        return WorkSchedule::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('schedules.csv', $content);
    }

    public function test_imports_schedule_by_work_schedule_code(): void
    {
        $emp = $this->makeEmployee('EMP-001');
        $ws = $this->makeSchedule('STD', 'Standard Week');

        $csv = "employee_no,work_schedule,effective_from\nEMP-001,STD,2026-01-01\n";

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 1, 'skipped' => 0]);

        $row = EmployeeSchedule::where('employee_id', $emp->id)->firstOrFail();
        $this->assertSame($ws->id, $row->work_schedule_id);
        $this->assertSame('2026-01-01', $row->effective_from->toDateString());
    }

    public function test_imports_schedule_by_work_schedule_name(): void
    {
        $emp = $this->makeEmployee('EMP-002');
        $ws = $this->makeSchedule('ROT', 'Shift Rotation');

        $csv = "employee_no,work_schedule,effective_from\nEMP-002,Shift Rotation,2026-02-01\n";

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 1]);

        $this->assertSame(
            $ws->id,
            EmployeeSchedule::where('employee_id', $emp->id)->value('work_schedule_id'),
        );
    }

    public function test_skips_rows_with_missing_required_columns(): void
    {
        $this->makeSchedule('STD', 'Standard');
        $csv = "employee_no,work_schedule,effective_from\n,STD,2026-01-01\nEMP-X,,2026-01-01\nEMP-Y,STD,\n";

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 0, 'skipped' => 3]);
    }

    public function test_records_error_for_unknown_employee_no(): void
    {
        $this->makeSchedule('STD', 'Standard');
        $csv = "employee_no,work_schedule,effective_from\nGHOST-99,STD,2026-01-01\n";

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 0]);

        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_records_error_for_unknown_work_schedule(): void
    {
        $this->makeEmployee('EMP-003');
        $csv = "employee_no,work_schedule,effective_from\nEMP-003,NO_SUCH_SCHEDULE,2026-01-01\n";

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 0]);

        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_changing_schedule_via_import_closes_previous_period(): void
    {
        $emp = $this->makeEmployee('EMP-CHG');
        $ws1 = $this->makeSchedule('S1', 'Schedule One');
        $ws2 = $this->makeSchedule('S2', 'Schedule Two');

        $emp->employeeSchedules()->create([
            'work_schedule_id' => $ws1->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $csv = "employee_no,work_schedule,effective_from\nEMP-CHG,S2,2026-07-01\n";
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master/employee-schedules/import', ['file' => $this->csvFile($csv)])
            ->assertOk()
            ->assertJsonFragment(['imported' => 1]);

        $rows = EmployeeSchedule::where('employee_id', $emp->id)->orderBy('effective_from')->get();
        $this->assertSame(2, $rows->count());
        // Old row closed at 2026-06-30.
        $this->assertSame($ws1->id, $rows[0]->work_schedule_id);
        $this->assertSame('2026-06-30', $rows[0]->effective_to->toDateString());
        // New row open from 2026-07-01.
        $this->assertSame($ws2->id, $rows[1]->work_schedule_id);
        $this->assertSame('2026-07-01', $rows[1]->effective_from->toDateString());
        $this->assertNull($rows[1]->effective_to);
    }
}

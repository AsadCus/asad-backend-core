<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LeaveBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function hrUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('hr');

        return $user;
    }

    /**
     * Build a real .xlsx upload from the given rows (first row is the header).
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'lb').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'balances.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_valid_rows_upsert_and_bad_rows_are_reported(): void
    {
        $emp = Employee::factory()->create(['employee_no' => 'EMP-1001']);
        $type = LeaveType::factory()->create(['code' => 'ANNUAL']);

        $file = $this->xlsx([
            ['employee_no', 'leave_type_code', 'year', 'allocated', 'note'],
            ['EMP-1001', 'ANNUAL', 2026, 12, 'Initial'],
            ['EMP-9999', 'ANNUAL', 2026, 5, 'Unknown employee'],
            ['EMP-1001', 'NOPE', 2026, 5, 'Unknown type'],
        ]);

        $this->actingAs($this->hrUser(), 'sanctum');
        $this->post('/api/master/leave-balances/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['imported' => 1, 'skipped' => 0])
            ->assertJsonCount(2, 'errors');

        $balance = LeaveBalance::query()
            ->where(['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'year' => 2026])
            ->first();
        $this->assertNotNull($balance);
        $this->assertSame('12.00', $balance->allocated);
    }

    public function test_import_upserts_existing_balance_and_keeps_used(): void
    {
        $emp = Employee::factory()->create(['employee_no' => 'EMP-2002']);
        $type = LeaveType::factory()->create(['code' => 'SICK']);
        LeaveBalance::create([
            'employee_id' => $emp->id, 'leave_type_id' => $type->id, 'year' => 2026, 'allocated' => 5, 'used' => 1,
        ]);

        $file = $this->xlsx([
            ['employee_no', 'leave_type_code', 'year', 'allocated', 'note'],
            ['EMP-2002', 'SICK', 2026, 20, 'Adjusted'],
        ]);

        $this->actingAs($this->hrUser(), 'sanctum');
        $this->post('/api/master/leave-balances/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['imported' => 1]);

        $balance = LeaveBalance::query()->where('employee_id', $emp->id)->first();
        $this->assertSame('20.00', $balance->allocated);
        $this->assertSame('1.00', $balance->used); // upsert leaves used untouched
        $this->assertSame(1, LeaveBalance::query()->where('employee_id', $emp->id)->count());
    }

    public function test_non_hr_cannot_import(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $file = $this->xlsx([['employee_no', 'leave_type_code', 'year', 'allocated', 'note']]);

        $this->actingAs($user, 'sanctum');
        $this->post('/api/master/leave-balances/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_template_download(): void
    {
        $this->actingAs($this->hrUser(), 'sanctum');
        $res = $this->get('/api/master/leave-balances/template');
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }
}

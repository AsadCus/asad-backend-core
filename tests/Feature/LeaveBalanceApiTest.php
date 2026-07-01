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

class LeaveBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    /**
     * @return array{0: User, 1: Employee}
     */
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

    public function test_my_balances_lists_only_assigned_types_with_remaining(): void
    {
        [$empUser, $employee] = $this->makeEmployeeUser('employee');
        $annual = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]);
        LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]); // unassigned → excluded
        LeaveType::factory()->create(['requires_balance' => false, 'is_active' => true]); // no quota → excluded

        LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $annual->id,
            'year' => (int) date('Y'), 'allocated' => 12, 'used' => 5,
        ]);

        $this->actingAs($empUser, 'sanctum');
        $rows = collect($this->getJson('/api/master/leave-balances/my')->assertOk()->json());

        $this->assertCount(1, $rows); // only the assigned type
        $annualRow = $rows->firstWhere('leave_type_id', $annual->id);
        $this->assertEquals(12, $annualRow['allocated']);
        $this->assertEquals(5, $annualRow['used']);
        $this->assertEquals(7, $annualRow['remaining']);
    }

    public function test_hr_can_view_a_specific_employees_balances(): void
    {
        [, $employee] = $this->makeEmployeeUser('employee');
        $type = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]);
        LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]); // unassigned → excluded
        LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'year' => (int) date('Y'), 'allocated' => 10,
        ]);
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr, 'sanctum');
        $this->getJson("/api/master/employees/{$employee->id}/leave-balances")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_employee_cannot_view_another_employees_balances(): void
    {
        [$empUser] = $this->makeEmployeeUser('employee');
        [, $other] = $this->makeEmployeeUser('employee');

        $this->actingAs($empUser, 'sanctum');
        $this->getJson("/api/master/employees/{$other->id}/leave-balances")->assertStatus(403);
    }

    public function test_assignable_types_filters_balance_gender_and_allocated(): void
    {
        [, $employee] = $this->makeEmployeeUser('employee', ['gender' => 'male']);
        $balanceType = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]);
        $femaleOnly = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true, 'gender_restriction' => 'female']);
        $nonBalance = LeaveType::factory()->create(['requires_balance' => false, 'is_active' => true]);
        $allocated = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true]);
        LeaveBalance::create([
            'employee_id' => $employee->id, 'leave_type_id' => $allocated->id, 'year' => 2026, 'allocated' => 5,
        ]);

        $hr = User::factory()->create();
        $hr->assignRole('hr');
        $this->actingAs($hr, 'sanctum');

        $ids = collect(
            $this->getJson("/api/master/leave-balances/assignable-types?employee_id={$employee->id}&year=2026")
                ->assertOk()->json(),
        )->pluck('value');

        $this->assertTrue($ids->contains($balanceType->id));
        $this->assertFalse($ids->contains($femaleOnly->id));
        $this->assertFalse($ids->contains($nonBalance->id));
        $this->assertFalse($ids->contains($allocated->id));
    }

    public function test_assignable_types_by_gender_returns_max_days_for_a_not_yet_created_employee(): void
    {
        $balanceType = LeaveType::factory()->create([
            'requires_balance' => true, 'is_active' => true, 'max_days_per_year' => 12,
        ]);
        $femaleOnly = LeaveType::factory()->create(['requires_balance' => true, 'is_active' => true, 'gender_restriction' => 'female']);

        $hr = User::factory()->create();
        $hr->assignRole('hr');
        $this->actingAs($hr, 'sanctum');

        $rows = collect(
            $this->getJson('/api/master/leave-balances/assignable-types?gender=male')
                ->assertOk()->json(),
        );

        $row = $rows->firstWhere('value', $balanceType->id);
        $this->assertEquals(12, $row['max_days_per_year']);
        $this->assertFalse($rows->pluck('value')->contains($femaleOnly->id));
    }

    public function test_cannot_allocate_a_non_balance_type(): void
    {
        [, $employee] = $this->makeEmployeeUser('employee', ['gender' => 'male']);
        $nonBalance = LeaveType::factory()->create(['requires_balance' => false]);
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $this->actingAs($hr, 'sanctum');
        $this->postJson('/api/master/leave-balances', [
            'employee_id' => $employee->id, 'leave_type_id' => $nonBalance->id, 'year' => 2026, 'allocated' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('leave_type_id');
    }
}

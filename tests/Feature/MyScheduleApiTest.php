<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function makeEmployeeUser(): array
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $employee = Employee::query()->create([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ]);

        return [$user, $employee];
    }

    public function test_employee_can_view_their_own_schedule(): void
    {
        [$user, $employee] = $this->makeEmployeeUser();
        $ws = WorkSchedule::factory()->create(['name' => 'Office Mon-Fri']); // Mon-Fri workdays, Sat/Sun off

        EmployeeSchedule::create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $ws->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/my-schedule')->assertOk();
        $response->assertJsonPath('work_schedule_name', 'Office Mon-Fri');
        $response->assertJsonCount(7, 'days');

        $days = collect($response->json('days'))->keyBy('day_of_week');
        $this->assertTrue($days[1]['is_workday']); // Monday
        $this->assertNotNull($days[1]['shift']);
        $this->assertFalse($days[0]['is_workday']); // Sunday
        $this->assertNull($days[0]['shift']);
    }

    public function test_employee_with_no_assigned_schedule_gets_an_empty_response(): void
    {
        [$user] = $this->makeEmployeeUser();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/my-schedule')
            ->assertOk()
            ->assertJson(['work_schedule_name' => null, 'effective_from' => null, 'days' => []]);
    }

    public function test_user_without_an_employee_profile_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/my-schedule')->assertStatus(422);
    }
}

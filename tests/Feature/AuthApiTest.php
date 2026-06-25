<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeUser(array $employeeAttrs = []): User
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
            'is_active' => true,
        ], $employeeAttrs));

        return $user;
    }

    public function test_user_without_employee_profile_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_active_employee_can_login(): void
    {
        $user = $this->makeEmployeeUser(['is_active' => true]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_employee_cannot_login(): void
    {
        $user = $this->makeEmployeeUser(['is_active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_employee_past_termination_date_cannot_login(): void
    {
        $user = $this->makeEmployeeUser([
            'is_active' => true,
            'termination_date' => Carbon::yesterday()->toDateString(),
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_employee_with_future_termination_date_can_still_login(): void
    {
        // Termination is scheduled but hasn't taken effect yet.
        $user = $this->makeEmployeeUser([
            'is_active' => true,
            'termination_date' => Carbon::tomorrow()->toDateString(),
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}

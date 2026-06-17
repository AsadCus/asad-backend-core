<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Employee;
use App\Models\FinancialYear;
use App\Models\GhostUser;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_hris_roles_users_and_masters(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Master data exists.
        $this->assertGreaterThan(0, Country::count());
        $this->assertGreaterThan(0, FinancialYear::count());
        $this->assertGreaterThan(0, Position::count());

        // Exactly the five HRIS roles, one user each (+ asad as a second administrator).
        foreach (['administrator', 'hr', 'supervisor', 'manager', 'employee'] as $role) {
            $this->assertGreaterThanOrEqual(1, User::role($role)->count(), "missing user for role {$role}");
        }

        // asad@example.com is the ghost administrator.
        $asad = User::query()->where('email', 'asad@example.com')->firstOrFail();
        $this->assertTrue($asad->hasRole('administrator'));
        $this->assertSame(1, GhostUser::count());
        $this->assertDatabaseHas('ghost_users', ['user_id' => (int) $asad->id]);

        // Every seeded user has a linked Employee carrying a position.
        foreach (['employee@example.com', 'supervisor@example.com', 'hr@example.com', 'manager@example.com', 'administrator@example.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $employee = Employee::query()->where('user_id', $user->id)->first();
            $this->assertNotNull($employee, "missing employee for {$email}");
            $this->assertNotNull($employee->position_id, "missing position for {$email}");
        }
    }
}

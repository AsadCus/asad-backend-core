<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrisMasterAndUserApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        Role::findOrCreate('administrator', 'web');
        Role::findOrCreate('employee', 'web');

        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_holding_master_crud_with_soft_delete(): void
    {
        $this->actingAdmin();

        $create = $this->postJson('/api/master/holdings', [
            'name' => 'Acme Group',
            'code' => 'ACME',
            'email' => 'group@acme.test',
            'is_active' => true,
        ]);
        $create->assertCreated();
        $id = $create->json('id');

        $this->assertDatabaseHas('holdings', ['code' => 'ACME', 'name' => 'Acme Group']);

        $this->getJson('/api/master/holdings')->assertOk()->assertJsonFragment(['code' => 'ACME']);

        $this->putJson("/api/master/holdings/{$id}", [
            'name' => 'Acme Holdings',
            'code' => 'ACME',
            'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('holdings', ['id' => $id, 'name' => 'Acme Holdings']);

        $this->deleteJson("/api/master/holdings/{$id}")->assertOk();
        $this->assertSoftDeleted('holdings', ['id' => $id]);
    }

    public function test_duplicate_holding_code_is_rejected(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/holdings', ['name' => 'One', 'code' => 'DUP'])->assertCreated();
        $this->postJson('/api/master/holdings', ['name' => 'Two', 'code' => 'DUP'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_position_options_endpoint_returns_value_label(): void
    {
        $this->actingAdmin();
        Position::query()->create(['name' => 'Staff', 'code' => 'STAFF', 'level' => 'staff', 'is_active' => true]);

        $this->getJson('/api/master/positions/options')
            ->assertOk()
            ->assertJsonStructure([['value', 'label']]);
    }

    public function test_create_hris_user_links_employee_with_position_then_soft_deletes(): void
    {
        $this->actingAdmin();
        $position = Position::query()->create(['name' => 'Staff', 'code' => 'STAFF', 'level' => 'staff', 'is_active' => true]);

        $create = $this->postJson('/api/master/users', [
            'name' => 'New Employee',
            'email' => 'new.employee@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employee',
            'position_id' => $position->id,
        ]);
        $create->assertCreated();
        $userId = $create->json('id');

        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'new.employee@test.com']);
        $this->assertDatabaseHas('employees', ['user_id' => $userId, 'position_id' => $position->id]);
        $this->assertTrue(User::find($userId)->hasRole('employee'));

        // Listing by role returns the new user with its position.
        $this->getJson('/api/master/users?role=employee')
            ->assertOk()
            ->assertJsonFragment(['email' => 'new.employee@test.com', 'position_name' => 'Staff']);

        $this->deleteJson("/api/master/users/{$userId}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_user_create_requires_role_and_position(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/users', [
            'name' => 'No Role',
            'email' => 'norole@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors(['role', 'position_id']);
    }

    public function test_all_master_endpoints_respond_against_full_seed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'asad@example.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        foreach ([
            'holdings', 'business-units', 'departments', 'positions',
            'shifts', 'work-schedules', 'holidays', 'leave-types',
        ] as $master) {
            $this->getJson("/api/master/{$master}")->assertOk();
        }

        $this->getJson('/api/master/stats')
            ->assertOk()
            ->assertJsonStructure(['users', 'holdings', 'positions', 'leave_types']);

        $this->getJson('/api/master/business-units/options')->assertOk()->assertJsonStructure([['value', 'label']]);

        // Parent-FK + enum + time creates.
        $holdingId = Holding::query()->value('id');
        $this->postJson('/api/master/business-units', [
            'name' => 'New BU', 'code' => 'NEW-BU', 'holding_id' => $holdingId, 'is_active' => true,
        ])->assertCreated();

        $this->postJson('/api/master/positions', [
            'name' => 'Analyst', 'code' => 'ANALYST', 'level' => 'staff', 'is_active' => true,
        ])->assertCreated();
        $this->postJson('/api/master/positions', [
            'name' => 'Bad', 'code' => 'BAD', 'level' => 'not-a-level',
        ])->assertStatus(422)->assertJsonValidationErrors('level');

        $this->postJson('/api/master/holidays', [
            'name' => 'Company Day', 'date' => '2026-12-31', 'type' => 'company', 'is_recurring' => false,
        ])->assertCreated();

        $this->postJson('/api/master/leave-types', [
            'name' => 'Special', 'code' => 'SPECIAL', 'gender_restriction' => 'female', 'is_paid' => true,
        ])->assertCreated();

        $this->postJson('/api/master/shifts', [
            'name' => 'Evening', 'code' => 'EVE', 'start_time' => '18:00', 'end_time' => '22:00',
        ])->assertCreated();
    }
}

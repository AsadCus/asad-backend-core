<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\HrisRoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceEligibilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, HrisRoleSeeder::class]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeEmployee(string $role = 'employee', array $attrs = []): Employee
    {
        $user = $this->makeUser($role);

        return Employee::query()->create(array_merge([
            'employee_no' => 'EMP-'.fake()->unique()->numerify('####'),
            'hire_date' => '2024-01-01',
            'user_id' => $user->id,
        ], $attrs));
    }

    private function makeLegacyAdminAlias(string $role): User
    {
        $user = User::factory()->create();
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_list_eligibility_roster(): void
    {
        $employee = $this->makeEmployee();
        $admin = $this->makeUser('administrator');

        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/master/attendance-eligibility')
            ->assertOk()
            ->assertJsonFragment(['id' => $employee->id, 'can_check_in' => true]);
    }

    public function test_admin_can_toggle_eligibility(): void
    {
        $employee = $this->makeEmployee();
        $admin = $this->makeUser('administrator');

        $this->actingAs($admin, 'sanctum');
        $this->putJson("/api/master/attendance-eligibility/{$employee->id}", ['can_check_in' => false])
            ->assertOk()
            ->assertJsonFragment(['id' => $employee->id, 'can_check_in' => false]);

        $this->assertFalse($employee->fresh()->can_check_in);
    }

    public function test_admin_can_bulk_toggle_eligibility(): void
    {
        $a = $this->makeEmployee();
        $b = $this->makeEmployee();
        $admin = $this->makeUser('administrator');

        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/master/attendance-eligibility/bulk', [
            'ids' => [$a->id, $b->id],
            'can_check_in' => false,
        ])->assertOk()->assertJsonFragment(['updated' => 2]);

        $this->assertFalse($a->fresh()->can_check_in);
        $this->assertFalse($b->fresh()->can_check_in);
    }

    public function test_non_admin_cannot_manage_eligibility(): void
    {
        $employee = $this->makeEmployee();
        $viewer = $this->makeUser('hr');

        $this->actingAs($viewer, 'sanctum');
        $this->getJson('/api/master/attendance-eligibility')->assertStatus(403);
        $this->putJson("/api/master/attendance-eligibility/{$employee->id}", ['can_check_in' => false])
            ->assertStatus(403);
    }

    public function test_legacy_admin_aliases_without_permission_are_forbidden(): void
    {
        // Role-name fallbacks are gone — access requires the explicit permission, not a role name.
        foreach (['admin', 'superadmin'] as $role) {
            $this->actingAs($this->makeLegacyAdminAlias($role), 'sanctum');
            $this->getJson('/api/master/attendance-eligibility')->assertStatus(403);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_org_unit_master_crud_with_soft_delete(): void
    {
        $this->actingAdmin();

        $create = $this->postJson('/api/master/org-units', [
            'type' => 'holding',
            'name' => 'Acme Group',
            'code' => 'ACME',
            'email' => 'group@acme.test',
            'is_active' => true,
        ]);
        $create->assertCreated();
        $id = $create->json('id');

        $this->assertDatabaseHas('org_units', ['code' => 'ACME', 'name' => 'Acme Group', 'type' => 'holding']);

        $this->getJson('/api/master/org-units')->assertOk()->assertJsonFragment(['code' => 'ACME']);

        $this->putJson("/api/master/org-units/{$id}", [
            'type' => 'holding',
            'name' => 'Acme Holdings',
            'code' => 'ACME',
            'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('org_units', ['id' => $id, 'name' => 'Acme Holdings']);

        $this->deleteJson("/api/master/org-units/{$id}")->assertOk();
        $this->assertSoftDeleted('org_units', ['id' => $id]);
    }

    public function test_duplicate_org_unit_code_is_rejected(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/org-units', ['type' => 'holding', 'name' => 'One', 'code' => 'DUP'])->assertCreated();
        $this->postJson('/api/master/org-units', ['type' => 'holding', 'name' => 'Two', 'code' => 'DUP'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_org_unit_invalid_nesting_is_rejected(): void
    {
        $this->actingAdmin();

        $holding = $this->postJson('/api/master/org-units', ['type' => 'holding', 'name' => 'H', 'code' => 'H1'])
            ->assertCreated()->json('id');

        // A division cannot sit directly under a holding.
        $this->postJson('/api/master/org-units', [
            'type' => 'division', 'name' => 'Bad', 'code' => 'BAD', 'parent_id' => $holding,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_roles_options_endpoint_returns_value_label(): void
    {
        $this->actingAdmin();

        $this->getJson('/api/master/roles/options')
            ->assertOk()
            ->assertJsonStructure([['value', 'label']]);
    }

    public function test_roles_permission_sets_endpoint_returns_roles_with_permissions(): void
    {
        $this->actingAdmin();

        $role = Role::findOrCreate('finance', 'web');
        $role->givePermissionTo(Permission::findOrCreate('dashboard view', 'web'));

        $this->getJson('/api/master/roles/permission-sets')
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'label', 'permissions']])
            ->assertJsonFragment(['name' => 'finance', 'permissions' => ['dashboard view']]);
    }

    public function test_create_hris_user_links_employee_with_role_then_soft_deletes(): void
    {
        $this->actingAdmin();

        $create = $this->postJson('/api/master/users', [
            'name' => 'New Employee',
            'email' => 'new.employee@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employee',
        ]);
        $create->assertCreated();
        $userId = $create->json('id');

        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'new.employee@test.com']);
        $this->assertDatabaseHas('employees', ['user_id' => $userId]);
        $this->assertTrue(User::find($userId)->hasRole('employee'));

        $this->getJson('/api/master/users?role=employee')
            ->assertOk()
            ->assertJsonFragment(['email' => 'new.employee@test.com', 'role' => 'employee']);

        $this->deleteJson("/api/master/users/{$userId}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_user_create_requires_role(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/master/users', [
            'name' => 'No Role',
            'email' => 'norole@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_all_master_endpoints_respond_against_full_seed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'asad@example.com')->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        foreach ([
            'org-units', 'roles', 'role-groups', 'management-levels',
            'shifts', 'work-schedules', 'holidays', 'leave-types',
        ] as $master) {
            $this->getJson("/api/master/{$master}")->assertOk();
        }

        $this->getJson('/api/master/stats')
            ->assertOk()
            ->assertJsonStructure(['users', 'org_units', 'roles', 'leave_types']);

        $this->getJson('/api/master/org-units/options')->assertOk()->assertJsonStructure([['value', 'label']]);
        $this->getJson('/api/master/org-units/tree')->assertOk()->assertJsonStructure(['tree', 'types']);
        $this->getJson('/api/master/roles/permissions')->assertOk();

        // Parent-FK + nesting create.
        $holdingUnitId = OrgUnit::query()->where('code', 'SMGI')->value('id');
        $this->postJson('/api/master/org-units', [
            'type' => 'business_unit', 'name' => 'New BU', 'code' => 'NEW-BU', 'parent_id' => $holdingUnitId, 'is_active' => true,
        ])->assertCreated();

        // Create a role with permissions.
        $this->postJson('/api/master/roles', [
            'label' => 'Auditor', 'permissions' => ['dashboard view', 'master view'],
        ])->assertCreated();

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

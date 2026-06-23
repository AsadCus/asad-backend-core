<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\Employee;
use App\Models\GhostUser;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrisScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{holding: OrgUnit, bu1: OrgUnit, bu2: OrgUnit, e1: Employee, e2: Employee} */
    private function seedTree(): array
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $e1 = Employee::factory()->create(['org_unit_id' => $bu1->id]);
        $e2 = Employee::factory()->create(['org_unit_id' => $bu2->id]);

        return compact('holding', 'bu1', 'bu2', 'e1', 'e2');
    }

    public function test_scoped_user_sees_only_their_subtree(): void
    {
        ['bu1' => $bu1, 'e1' => $e1, 'e2' => $e2] = $this->seedTree();

        $role = Role::create(['name' => 'bu-hr', 'guard_name' => 'web']); // not full access
        $hr = User::factory()->create();
        $hr->assignRole($role);
        Employee::factory()->create(['user_id' => $hr->id, 'org_unit_id' => $bu1->id, 'scope_org_unit_id' => $bu1->id]);

        $this->actingAs($hr);
        $ids = app(EmployeeService::class)->getForDataTable()->pluck('id')->all();

        $this->assertContains($e1->id, $ids);
        $this->assertNotContains($e2->id, $ids);
    }

    public function test_root_anchored_user_sees_the_whole_tree(): void
    {
        ['holding' => $holding, 'e1' => $e1, 'e2' => $e2] = $this->seedTree();

        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'root-hr', 'guard_name' => 'web']));
        Employee::factory()->create(['user_id' => $admin->id, 'org_unit_id' => $holding->id, 'scope_org_unit_id' => $holding->id]);

        $this->actingAs($admin);
        $ids = app(EmployeeService::class)->getForDataTable()->pluck('id')->all();

        $this->assertContains($e1->id, $ids);
        $this->assertContains($e2->id, $ids);
    }

    public function test_ghost_sees_everything(): void
    {
        ['e1' => $e1, 'e2' => $e2] = $this->seedTree();

        $ghost = User::factory()->create();
        $ghost->assignRole(Role::create(['name' => 'plain', 'guard_name' => 'web']));
        GhostUser::create(['user_id' => (int) $ghost->id]);

        $this->actingAs($ghost);
        $ids = app(EmployeeService::class)->getForDataTable()->pluck('id')->all();

        $this->assertContains($e1->id, $ids);
        $this->assertContains($e2->id, $ids);
    }
}

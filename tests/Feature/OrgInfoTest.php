<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\Employee;
use App\Models\GhostUser;
use App\Models\OrgInfo;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrgInfoTest extends TestCase
{
    use RefreshDatabase;

    /** A ghost is unbounded + bypasses every gate — the simplest "can do anything" actor. */
    private function actingGhost(): User
    {
        $user = User::factory()->create();
        GhostUser::create(['user_id' => (int) $user->id]);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_hierarchical_returns_ancestor_sections_skipping_empty(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $branch = OrgUnit::factory()->create(['type' => OrgUnitType::Branch, 'parent_id' => $bu->id]);

        OrgInfo::factory()->create(['org_unit_id' => $holding->id]);
        OrgInfo::factory()->create(['org_unit_id' => $bu->id]);
        // branch has none → skipped

        $this->actingGhost();

        $sections = $this->getJson("/api/company/org-infos?org_unit_id={$branch->id}")
            ->assertOk()
            ->json('sections');

        $this->assertCount(2, $sections); // holding + bu, branch skipped
        $this->assertSame($holding->id, $sections[0]['org_unit']['id']); // root first
        $this->assertSame($bu->id, $sections[1]['org_unit']['id']);
    }

    public function test_store_requires_manage_permission(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);

        $plain = User::factory()->create();
        $plain->assignRole(Role::create(['name' => 'plain-'.uniqid(), 'guard_name' => 'web']));
        $this->actingAs($plain, 'sanctum');

        $this->postJson('/api/company/org-infos', [
            'org_unit_id' => $holding->id, 'title' => 'Visi',
        ])->assertForbidden();

        $this->actingGhost();
        $this->postJson('/api/company/org-infos', [
            'org_unit_id' => $holding->id, 'title' => 'Visi', 'body' => 'x',
        ])->assertCreated();
    }

    public function test_store_is_rejected_for_unit_outside_allowed_scope(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);

        $editor = User::factory()->create();
        $role = Role::create(['name' => 'bu-editor-'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('hris.company-info manage', 'web'));
        $editor->assignRole($role);
        Employee::factory()->create(['user_id' => $editor->id, 'org_unit_id' => $bu1->id, 'scope_org_unit_id' => $bu1->id]);
        $this->actingAs($editor, 'sanctum');

        // Inside their subtree → ok.
        $this->postJson('/api/company/org-infos', [
            'org_unit_id' => $bu1->id, 'title' => 'Visi',
        ])->assertCreated();

        // Sibling BU, outside the subtree → 403 from scope enforcement.
        $this->postJson('/api/company/org-infos', [
            'org_unit_id' => $bu2->id, 'title' => 'Visi',
        ])->assertForbidden();
    }

    public function test_cannot_read_unit_outside_allowed_scope(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);

        $hr = User::factory()->create();
        $hr->assignRole(Role::create(['name' => 'bu-hr-'.uniqid(), 'guard_name' => 'web']));
        Employee::factory()->create(['user_id' => $hr->id, 'org_unit_id' => $bu1->id, 'scope_org_unit_id' => $bu1->id]);
        $this->actingAs($hr, 'sanctum');

        $this->getJson("/api/company/org-infos?org_unit_id={$bu1->id}")->assertOk();
        $this->getJson("/api/company/org-infos?org_unit_id={$bu2->id}")->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\Employee;
use App\Models\GhostUser;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\HrisScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgScopeSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{holding: OrgUnit, bu1: OrgUnit, branch: OrgUnit, bu2: OrgUnit} */
    private function tree(): array
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $branch = OrgUnit::factory()->create(['type' => OrgUnitType::Branch, 'parent_id' => $bu1->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);

        return compact('holding', 'bu1', 'branch', 'bu2');
    }

    private function scopedHr(OrgUnit $anchor): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'bu-hr-'.$anchor->id, 'guard_name' => 'web']));
        Employee::factory()->create(['user_id' => $user->id, 'org_unit_id' => $anchor->id, 'scope_org_unit_id' => $anchor->id]);

        return $user;
    }

    private function unboundedGhost(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'plain-'.uniqid(), 'guard_name' => 'web']));
        GhostUser::create(['user_id' => (int) $user->id]);

        return $user;
    }

    public function test_org_units_endpoint_lists_only_the_allowed_subtree(): void
    {
        ['bu1' => $bu1, 'branch' => $branch, 'bu2' => $bu2, 'holding' => $holding] = $this->tree();

        $this->actingAs($this->scopedHr($bu1), 'sanctum');
        $ids = collect($this->getJson('/api/scope/org-units')->assertOk()->json())->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$bu1->id, $branch->id], $ids);
        $this->assertNotContains($bu2->id, $ids);
        $this->assertNotContains($holding->id, $ids);
    }

    public function test_unbounded_user_can_list_and_switch_to_any_unit(): void
    {
        ['holding' => $holding, 'bu1' => $bu1, 'branch' => $branch, 'bu2' => $bu2] = $this->tree();

        $admin = $this->unboundedGhost();
        $this->actingAs($admin, 'sanctum');

        $ids = collect($this->getJson('/api/scope/org-units')->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$holding->id, $bu1->id, $branch->id, $bu2->id], $ids);

        $this->putJson('/api/scope/org-unit', ['org_unit_id' => $bu1->id])->assertOk();

        // Active selection narrows the visible set to that subtree.
        $this->assertEqualsCanonicalizing([$bu1->id, $branch->id], HrisScope::visibleOrgUnitIds($admin->fresh()));
    }

    public function test_switching_outside_allowed_scope_is_rejected(): void
    {
        ['bu1' => $bu1, 'bu2' => $bu2] = $this->tree();

        $this->actingAs($this->scopedHr($bu1), 'sanctum');

        $this->putJson('/api/scope/org-unit', ['org_unit_id' => $bu2->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('org_unit_id');
    }

    public function test_clearing_scope_returns_to_anchor(): void
    {
        ['bu1' => $bu1, 'branch' => $branch] = $this->tree();
        $hr = $this->scopedHr($bu1);
        $this->actingAs($hr, 'sanctum');

        $this->putJson('/api/scope/org-unit', ['org_unit_id' => $branch->id])->assertOk();
        $this->assertSame([$branch->id], HrisScope::visibleOrgUnitIds($hr->fresh()));

        $this->putJson('/api/scope/org-unit', ['org_unit_id' => null])->assertOk();
        $this->assertEqualsCanonicalizing([$bu1->id, $branch->id], HrisScope::visibleOrgUnitIds($hr->fresh()));
    }
}

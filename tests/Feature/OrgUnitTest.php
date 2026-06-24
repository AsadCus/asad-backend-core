<?php

namespace Tests\Feature;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use App\Rules\OrgUnitRule;
use App\Services\OrgUnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrgUnitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, OrgUnit>
     */
    private function buildTree(): array
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);
        $bu1 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);
        $branch = OrgUnit::factory()->create(['type' => OrgUnitType::Branch, 'parent_id' => $bu1->id]);
        $dept = OrgUnit::factory()->create(['type' => OrgUnitType::Department, 'parent_id' => $branch->id]);
        $division = OrgUnit::factory()->create(['type' => OrgUnitType::Division, 'parent_id' => $dept->id]);
        $bu2 = OrgUnit::factory()->create(['type' => OrgUnitType::BusinessUnit, 'parent_id' => $holding->id]);

        return compact('holding', 'bu1', 'branch', 'dept', 'division', 'bu2');
    }

    public function test_subtree_ids_returns_self_and_descendants_excluding_siblings(): void
    {
        ['bu1' => $bu1, 'branch' => $branch, 'dept' => $dept, 'division' => $division, 'bu2' => $bu2, 'holding' => $holding] = $this->buildTree();

        $ids = OrgUnit::subtreeIds($bu1->id);

        $this->assertEqualsCanonicalizing(
            [$bu1->id, $branch->id, $dept->id, $division->id],
            $ids,
        );
        $this->assertNotContains($bu2->id, $ids);
        $this->assertNotContains($holding->id, $ids);
    }

    public function test_nearest_of_type_walks_up_to_ancestor(): void
    {
        ['holding' => $holding, 'branch' => $branch, 'division' => $division] = $this->buildTree();

        $this->assertSame($branch->id, $division->nearestOfType(OrgUnitType::Branch)?->id);
        $this->assertSame($holding->id, $division->nearestOfType(OrgUnitType::Holding)?->id);
        $this->assertNull($holding->nearestOfType(OrgUnitType::Division));
    }

    public function test_nesting_rules_are_enforced(): void
    {
        // Holding is root-only.
        $this->assertTrue(OrgUnitType::Holding->canHaveParent(null));
        $this->assertFalse(OrgUnitType::Holding->canHaveParent(OrgUnitType::Holding));

        // BU must sit under a holding.
        $this->assertTrue(OrgUnitType::BusinessUnit->canHaveParent(OrgUnitType::Holding));
        $this->assertFalse(OrgUnitType::BusinessUnit->canHaveParent(null));

        // Branch under BU.
        $this->assertTrue(OrgUnitType::Branch->canHaveParent(OrgUnitType::BusinessUnit));
        $this->assertFalse(OrgUnitType::Branch->canHaveParent(OrgUnitType::Holding));

        // Department may sit under holding / BU / branch.
        $this->assertTrue(OrgUnitType::Department->canHaveParent(OrgUnitType::Holding));
        $this->assertTrue(OrgUnitType::Department->canHaveParent(OrgUnitType::BusinessUnit));
        $this->assertTrue(OrgUnitType::Department->canHaveParent(OrgUnitType::Branch));
        $this->assertFalse(OrgUnitType::Department->canHaveParent(OrgUnitType::Division));

        // Division is a leaf under a department.
        $this->assertTrue(OrgUnitType::Division->canHaveParent(OrgUnitType::Department));
        $this->assertFalse(OrgUnitType::Division->canHaveParent(OrgUnitType::Holding));
    }

    public function test_service_store_rejects_invalid_nesting(): void
    {
        $holding = OrgUnit::factory()->create(['type' => OrgUnitType::Holding, 'parent_id' => null]);

        $this->expectException(ValidationException::class);

        app(OrgUnitService::class)->store([
            'type' => OrgUnitType::Division->value, // division cannot sit under a holding
            'name' => 'Bad Division',
            'code' => 'BAD-DIV',
            'parent_id' => $holding->id,
        ]);
    }

    public function test_service_reparent_into_descendant_is_rejected(): void
    {
        ['bu1' => $bu1, 'branch' => $branch] = $this->buildTree();

        $this->expectException(ValidationException::class);

        // Move the BU under its own descendant branch → cycle.
        app(OrgUnitService::class)->update([
            'type' => OrgUnitType::BusinessUnit->value,
            'name' => $bu1->name,
            'code' => $bu1->code,
            'parent_id' => $branch->id,
        ], $bu1->id);
    }

    public function test_service_delete_blocked_when_unit_has_children(): void
    {
        ['bu1' => $bu1] = $this->buildTree();

        $this->expectException(ValidationException::class);

        app(OrgUnitService::class)->delete($bu1->id);
    }

    public function test_service_builds_nested_tree(): void
    {
        $this->buildTree();

        $tree = app(OrgUnitService::class)->getTree();

        $this->assertCount(1, $tree);                 // one holding root
        $this->assertSame('holding', $tree[0]['type']);
        $this->assertCount(2, $tree[0]['children']);  // two business units
    }

    public function test_any_unit_type_can_be_a_location(): void
    {
        // A holding (not a branch) can carry its own coordinates.
        $unit = app(OrgUnitService::class)->store([
            'type' => OrgUnitType::Holding->value,
            'parent_id' => null,
            'name' => 'HQ Tower',
            'code' => 'HQ',
            'has_location' => true,
            'latitude' => -6.2,
            'longitude' => 106.816666,
            'geofence_radius_meters' => 100,
            'is_active' => true,
        ]);

        $this->assertTrue((bool) $unit->fresh()->has_location);
        $this->assertSame('-6.20000000', (string) $unit->fresh()->latitude);
    }

    public function test_location_requires_coordinates_when_enabled(): void
    {
        $rules = (new OrgUnitRule)->rules();

        $missing = Validator::make(
            ['type' => 'branch', 'name' => 'X', 'code' => 'XLOC1', 'has_location' => true],
            $rules,
        );
        $this->assertTrue($missing->fails());
        $this->assertArrayHasKey('latitude', $missing->errors()->toArray());

        $ok = Validator::make(
            ['type' => 'branch', 'name' => 'X', 'code' => 'XLOC2', 'has_location' => false],
            $rules,
        );
        $this->assertFalse($ok->fails());
    }
}

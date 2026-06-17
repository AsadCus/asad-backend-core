<?php

namespace Tests\Feature\Tms;

use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestRoomMember;
use App\Models\Package;
use App\Services\ManifestService;
use Tests\TmsTestCase as TestCase;

class ManifestMemberMealTest extends TestCase
{
    private function buildRoomWithMeals(): Manifest
    {
        $package = Package::create([
            'package_number' => 'PKG-MEAL-001',
            'name' => 'Meal Package',
            'status' => 'open',
            'total_seats' => 10,
            'seats_left' => 8,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MNF-MEAL-001',
        ]);

        $official = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'name' => 'Ustaz Official',
            'sort_order' => 1,
        ]);
        $member = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'name' => 'Pilgrim One',
            'sort_order' => 2,
        ]);

        $room = ManifestRoom::create([
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'sort_order' => 1,
            'status' => 'pending',
        ]);

        ManifestRoomMember::create([
            'manifest_room_id' => $room->id,
            'manifest_member_id' => $official->id,
            'sort_order' => 1,
            'meal' => 'Exclude Meal',
        ]);
        ManifestRoomMember::create([
            'manifest_room_id' => $room->id,
            'manifest_member_id' => $member->id,
            'sort_order' => 2,
            'meal' => 'Full Board',
        ]);

        return $manifest;
    }

    public function test_meal_is_stored_per_room_member(): void
    {
        $this->buildRoomWithMeals();

        $this->assertDatabaseHas('manifest_room_members', [
            'sort_order' => 1,
            'meal' => 'Exclude Meal',
        ]);
        $this->assertDatabaseHas('manifest_room_members', [
            'sort_order' => 2,
            'meal' => 'Full Board',
        ]);
    }

    public function test_room_list_read_returns_per_member_meal(): void
    {
        $manifest = $this->buildRoomWithMeals();

        $data = app(ManifestService::class)->getForEditShow($manifest->id);
        $rows = $data['roomLists']['makkah'] ?? [];

        $meals = collect($rows)->pluck('meal', 'manifest_member_id');

        // Each member keeps its own meal — official excluded, pilgrim full board.
        $this->assertContains('Exclude Meal', $meals->values());
        $this->assertContains('Full Board', $meals->values());
        $this->assertCount(2, $rows);
    }
}

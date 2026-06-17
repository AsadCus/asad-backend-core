<?php

namespace Tests\Feature\Tms;

use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestRoomMember;
use App\Models\Package;
use App\Models\PackageOfficial;
use App\Models\User;
use App\Services\ManifestService;
use Tests\TmsTestCase as TestCase;

class ManifestStableIdsSyncTest extends TestCase
{
    public function test_manifest_update_preserves_group_member_room_and_room_member_ids(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-STABLE-ID-001',
            'name' => 'Stable ID Package',
            'status' => 'open',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'official',
            'name' => 'Official One',
            'contact_number' => '0191000001',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-STABLE-ID-001',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'open',
            'members' => [
                [
                    'name_as_per_passport' => 'Member One',
                    'sharing_group_key' => 'group-a',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                    'relationship' => 'leader',
                    'sharing_plan' => 'double',
                ],
                [
                    'package_official_id' => $official->id,
                    'name_as_per_passport' => 'Official One',
                    'sharing_group_key' => 'group-official',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                    'relationship' => 'official',
                    'sharing_plan' => 'single',
                ],
            ],
            'rooms' => [
                [
                    'location' => 'makkah',
                    'group_relationship' => 'family',
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'capacity' => 2,
                    'status' => 'pending',
                    'members' => [
                        [
                            'package_official_id' => $official->id,
                            'name_as_per_passport' => 'Official One',
                            'sharing_group_key' => 'group-official',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();

        $memberOne = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->whereNull('package_official_id')
            ->firstOrFail();
        $officialMember = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('package_official_id', $official->id)
            ->firstOrFail();

        $room = ManifestRoom::query()->where('manifest_id', $manifest->id)->firstOrFail();
        $roomMembers = ManifestRoomMember::query()
            ->where('manifest_room_id', $room->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(1, $roomMembers);

        $groupOneId = (int) $memberOne->manifest_sharing_group_id;
        $groupOfficialId = (int) $officialMember->manifest_sharing_group_id;
        $memberOneId = (int) $memberOne->id;
        $officialMemberId = (int) $officialMember->id;
        $roomId = (int) $room->id;
        $roomMemberOneId = (int) $roomMembers
            ->firstWhere('manifest_member_id', $officialMemberId)
            ?->id;

        $this->assertGreaterThan(0, $roomMemberOneId);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'open',
            'members' => [
                [
                    'id' => $memberOneId,
                    'manifest_sharing_group_id' => $groupOneId,
                    'sharing_group_id' => $groupOneId,
                    'name_as_per_passport' => 'Member One Updated',
                    'sharing_group_key' => 'group-a',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                    'relationship' => 'leader',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $officialMemberId,
                    'package_official_id' => $official->id,
                    'manifest_sharing_group_id' => $groupOfficialId,
                    'sharing_group_id' => $groupOfficialId,
                    'name_as_per_passport' => 'Official One Updated',
                    'sharing_group_key' => 'group-official',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                    'relationship' => 'official',
                    'sharing_plan' => 'single',
                ],
            ],
            'rooms' => [
                [
                    'id' => $roomId,
                    'location' => 'makkah',
                    'group_relationship' => 'family-updated',
                    'room_label' => 'Room A Updated',
                    'room_type' => 'double',
                    'bed_type' => 'king',
                    'capacity' => 2,
                    'status' => 'assigned',
                    'members' => [
                        [
                            'room_member_id' => $roomMemberOneId,
                            'manifest_member_id' => $officialMemberId,
                            'package_official_id' => $official->id,
                            'name_as_per_passport' => 'Official One Updated',
                            'sharing_group_key' => 'group-official',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $this->assertDatabaseHas('manifest_members', [
            'id' => $memberOneId,
            'manifest_id' => $manifest->id,
            'name' => 'Member One Updated',
            'manifest_sharing_group_id' => $groupOneId,
        ]);

        $this->assertDatabaseHas('manifest_members', [
            'id' => $officialMemberId,
            'manifest_id' => $manifest->id,
            'name' => 'Official One Updated',
            'manifest_sharing_group_id' => $groupOfficialId,
            'package_official_id' => $official->id,
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'id' => $roomId,
            'manifest_id' => $manifest->id,
            'group_relationship' => 'family-updated',
            'room_label' => 'Room A Updated',
            'status' => 'assigned',
        ]);

        $this->assertDatabaseHas('manifest_room_members', [
            'id' => $roomMemberOneId,
            'manifest_room_id' => $roomId,
            'manifest_member_id' => $officialMemberId,
        ]);

        $this->assertDatabaseCount('manifest_sharing_groups', 2);
        $this->assertDatabaseCount('manifest_members', 2);
        $this->assertDatabaseCount('manifest_rooms', 1);
        $this->assertDatabaseCount('manifest_room_members', 1);

        $this->assertDatabaseHas('manifest_sharing_groups', ['id' => $groupOneId]);
        $this->assertDatabaseHas('manifest_sharing_groups', ['id' => $groupOfficialId]);
    }

    public function test_manifest_edit_payload_keeps_room_order_by_sort_order(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-ORDER-001',
            'name' => 'Room Order Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-ORDER-001',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'open',
            'members' => [
                [
                    'name_as_per_passport' => 'Member A',
                    'sharing_group_key' => 'group-a',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                    'relationship' => 'leader',
                    'sharing_plan' => 'single',
                ],
                [
                    'name_as_per_passport' => 'Member B',
                    'sharing_group_key' => 'group-b',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                    'relationship' => 'member',
                    'sharing_plan' => 'single',
                ],
            ],
            'rooms' => [
                [
                    'location' => 'makkah',
                    'room_label' => 'Room Priority 1',
                    'room_type' => 'single',
                    'bed_type' => 'single',
                    'capacity' => 1,
                    'status' => 'assigned',
                    'members' => [
                        [
                            'name_as_per_passport' => 'Member A',
                            'sharing_group_key' => 'group-a',
                            'sort_order' => 1,
                        ],
                    ],
                ],
                [
                    'location' => 'makkah',
                    'room_label' => 'Room Priority 2',
                    'room_type' => 'single',
                    'bed_type' => 'single',
                    'capacity' => 1,
                    'status' => 'assigned',
                    'members' => [
                        [
                            'name_as_per_passport' => 'Member B',
                            'sharing_group_key' => 'group-b',
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $manifestRooms = array_values($payload['manifest_rooms'] ?? []);

        $this->assertCount(2, $manifestRooms);
        $this->assertSame('Room Priority 1', $manifestRooms[0]['room_label']);
        $this->assertSame('Room Priority 2', $manifestRooms[1]['room_label']);
    }
}

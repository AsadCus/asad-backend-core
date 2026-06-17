<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestRoomMember;
use App\Models\Package;
use App\Models\User;
use App\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManifestRoomSplitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: splitting a member out of a shared room must persist as two
     * separate rooms. The frontend can submit the split room still carrying the
     * original room id; syncRooms must not merge two incoming groups onto the
     * same physical room.
     */
    public function test_splitting_shared_room_member_creates_two_rooms(): void
    {
        $this->actingAs(User::factory()->create());

        $package = Package::create([
            'package_number' => 'PKG-SPLIT-001',
            'name' => 'Room Split Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-SPLIT-001',
        ]);

        // Create the two members first (room members resolve by id, not name).
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
                    'name_as_per_passport' => 'Member Two',
                    'sharing_group_key' => 'group-a',
                    'group_sort_order' => 1,
                    'sort_order' => 2,
                    'relationship' => 'member',
                    'sharing_plan' => 'double',
                ],
            ],
            'rooms' => [],
        ], (int) $manifest->id);

        $memberOne = ManifestMember::query()->where('manifest_id', $manifest->id)->where('name', 'Member One')->firstOrFail();
        $memberTwo = ManifestMember::query()->where('manifest_id', $manifest->id)->where('name', 'Member Two')->firstOrFail();

        // Assign both to one shared double room.
        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'open',
            'members' => [
                ['id' => $memberOne->id, 'manifest_sharing_group_id' => $memberOne->manifest_sharing_group_id, 'name_as_per_passport' => 'Member One', 'sharing_group_key' => 'group-a', 'group_sort_order' => 1, 'sort_order' => 1, 'relationship' => 'leader', 'sharing_plan' => 'double'],
                ['id' => $memberTwo->id, 'manifest_sharing_group_id' => $memberTwo->manifest_sharing_group_id, 'name_as_per_passport' => 'Member Two', 'sharing_group_key' => 'group-a', 'group_sort_order' => 1, 'sort_order' => 2, 'relationship' => 'member', 'sharing_plan' => 'double'],
            ],
            'rooms' => [
                [
                    'location' => 'makkah',
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'capacity' => 2,
                    'status' => 'pending',
                    'members' => [
                        ['manifest_member_id' => $memberOne->id, 'sharing_group_key' => 'group-a', 'sort_order' => 1],
                        ['manifest_member_id' => $memberTwo->id, 'sharing_group_key' => 'group-a', 'sort_order' => 2],
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();
        $this->assertDatabaseCount('manifest_rooms', 1);
        $this->assertDatabaseCount('manifest_room_members', 2);

        $room = ManifestRoom::query()->where('manifest_id', $manifest->id)->firstOrFail();
        $roomMemberTwoId = (int) ManifestRoomMember::query()
            ->where('manifest_room_id', $room->id)
            ->where('manifest_member_id', $memberTwo->id)
            ->value('id');

        // Split: Member Two goes to its own room. The second room intentionally
        // arrives with the SAME room id as the first (the unfixed frontend) to
        // prove the backend guard splits rather than merges.
        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'open',
            'members' => [
                [
                    'id' => $memberOne->id,
                    'manifest_sharing_group_id' => $memberOne->manifest_sharing_group_id,
                    'name_as_per_passport' => 'Member One',
                    'sharing_group_key' => 'group-a',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                    'relationship' => 'leader',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $memberTwo->id,
                    'manifest_sharing_group_id' => $memberTwo->manifest_sharing_group_id,
                    'name_as_per_passport' => 'Member Two',
                    'sharing_group_key' => 'split-group-b',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                    'relationship' => 'member',
                    'sharing_plan' => 'double',
                ],
            ],
            'rooms' => [
                [
                    'id' => $room->id,
                    'location' => 'makkah',
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'capacity' => 1,
                    'status' => 'pending',
                    'members' => [
                        ['id' => $memberOne->id, 'manifest_member_id' => $memberOne->id, 'sharing_group_key' => 'group-a', 'sort_order' => 1],
                    ],
                ],
                [
                    'id' => $room->id,
                    'location' => 'makkah',
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'capacity' => 1,
                    'status' => 'pending',
                    'members' => [
                        ['room_member_id' => $roomMemberTwoId, 'id' => $memberTwo->id, 'manifest_member_id' => $memberTwo->id, 'sharing_group_key' => 'split-group-b', 'sort_order' => 1],
                    ],
                ],
            ],
        ], (int) $manifest->id);

        // Two distinct rooms, one member each — the split persisted.
        $this->assertDatabaseCount('manifest_rooms', 2);
        $this->assertDatabaseCount('manifest_room_members', 2);

        $rooms = ManifestRoom::query()->where('manifest_id', $manifest->id)->withCount('roomMembers')->get();
        $this->assertCount(2, $rooms);
        foreach ($rooms as $r) {
            $this->assertSame(1, (int) $r->room_members_count);
        }

        // Each member sits in a different room.
        $memberOneRoom = ManifestRoomMember::query()->where('manifest_member_id', $memberOne->id)->value('manifest_room_id');
        $memberTwoRoom = ManifestRoomMember::query()->where('manifest_member_id', $memberTwo->id)->value('manifest_room_id');
        $this->assertNotSame((int) $memberOneRoom, (int) $memberTwoRoom);
    }

    /**
     * Regression through the controller normalization layer: the canonical
     * payload can carry two split rooms that still share the original
     * manifest_room_id. applyCanonicalRoomLists must not collapse them into one
     * room before syncRooms runs.
     */
    public function test_split_persists_through_controller_when_rooms_share_room_id(): void
    {
        Permission::firstOrCreate(['name' => 'manifest view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manifest edit', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['manifest view', 'manifest edit']);
        $this->actingAs($user);

        $package = Package::create([
            'package_number' => 'PKG-SPLIT-HTTP-001',
            'name' => 'Room Split HTTP Package',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'created_by' => $user->id,
        ]);

        $memberA = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => Customer::create(['user_id' => User::factory()->create()->id, 'is_active' => true])->id,
            'is_leader' => true,
            'status' => 'draft',
            'sharing_plan' => 'double',
        ]);
        $memberB = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => Customer::create(['user_id' => User::factory()->create()->id, 'is_active' => true])->id,
            'is_leader' => false,
            'status' => 'draft',
            'sharing_plan' => 'double',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-SPLIT-HTTP-001',
            'status' => 'draft',
        ]);

        $sharingGroup = static fn (array $members): array => [
            'customer_confirmation_id' => $confirmation->id,
            'sort_order' => 1,
            'members' => $members,
        ];

        // Create: both members in one shared double room.
        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'manifest' => ['package_id' => $package->id, 'status' => 'draft'],
            'manifest_sharing_groups' => [
                $sharingGroup([
                    ['customer_confirmation_member_id' => $memberA->id, 'sharing_plan' => 'double', 'sort_order' => 1],
                    ['customer_confirmation_member_id' => $memberB->id, 'sharing_plan' => 'double', 'sort_order' => 2],
                ]),
            ],
            'manifest_rooms' => [
                [
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'members' => [
                        ['customer_confirmation_member_id' => $memberA->id, 'sort_order' => 1],
                        ['customer_confirmation_member_id' => $memberB->id, 'sort_order' => 2],
                    ],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('manifest_rooms', 1);
        $roomId = (int) ManifestRoom::query()->where('manifest_id', $manifest->id)->value('id');

        // Split: two rooms, BOTH carrying the original room id (same room_label
        // too) — exactly what the UI submits after a split-from-group action.
        $this->post(route('manifests.store'), [
            'id' => $manifest->id,
            'manifest' => ['package_id' => $package->id, 'status' => 'draft'],
            'manifest_sharing_groups' => [
                $sharingGroup([
                    ['customer_confirmation_member_id' => $memberA->id, 'sharing_plan' => 'double', 'sort_order' => 1],
                ]),
                [
                    'customer_confirmation_id' => $confirmation->id,
                    'sort_order' => 2,
                    'members' => [
                        ['customer_confirmation_member_id' => $memberB->id, 'sharing_plan' => 'double', 'sort_order' => 1],
                    ],
                ],
            ],
            'manifest_rooms' => [
                [
                    'id' => $roomId,
                    'location' => 'makkah',
                    'sort_order' => 1,
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'members' => [
                        ['customer_confirmation_member_id' => $memberA->id, 'sort_order' => 1],
                    ],
                ],
                [
                    'id' => $roomId,
                    'location' => 'makkah',
                    'sort_order' => 2,
                    'room_label' => 'Room A',
                    'room_type' => 'double',
                    'bed_type' => 'twin',
                    'members' => [
                        ['customer_confirmation_member_id' => $memberB->id, 'sort_order' => 1],
                    ],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('manifest_rooms', 2);
        $this->assertDatabaseCount('manifest_room_members', 2);

        $rooms = ManifestRoom::query()->where('manifest_id', $manifest->id)->withCount('roomMembers')->get();
        $this->assertCount(2, $rooms);
        foreach ($rooms as $room) {
            $this->assertSame(1, (int) $room->room_members_count);
        }
    }
}

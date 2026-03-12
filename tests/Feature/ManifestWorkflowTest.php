<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Manifest;
use App\Models\ManifestTraveler;
use App\Models\Package;
use App\Models\User;
use App\Services\ManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_grouped_manifest_payload_and_normalizes_values(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create(['name' => 'Ahmad Example']);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-001',
            'name' => 'Umrah Basic',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'passport_number' => 'A1234567',
            'date_of_birth' => '1995-01-10',
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
            'role' => 'spouse',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Ahmad Example',
                    'passport_no' => 'A9999999',
                    'date_of_birth' => '1996-02-20',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'name_as_per_passport' => 'Ahmad Example',
                        'customer_confirmation_member_id' => $member->id,
                        'room_no' => 'M-101',
                        'room_type' => 'Quad',
                        'bed_type' => 'Single',
                        'meal' => 'Breakfast Only',
                        'room_remarks' => 'Room-level note',
                        'remarks' => 'Member-level note',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::first();

        $this->assertNotNull($manifest);
        $this->assertDatabaseHas('manifest_travelers', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $customer->refresh();
        $this->assertSame('A9999999', $customer->passport_number);
        $this->assertSame('1996-02-20', optional($customer->date_of_birth)->format('Y-m-d'));

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => 'M-101',
            'room_type' => 'quad',
            'bed_type' => 'single',
            'meal' => 'Breakfast Only',
            'remarks' => 'Room-level note',
        ]);

        $createdTravelerId = (int) ManifestTraveler::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $createdRoomId = (int) $manifest->rooms()->value('id');

        $this->assertDatabaseHas('manifest_room_members', [
            'manifest_room_id' => $createdRoomId,
            'manifest_traveler_id' => $createdTravelerId,
            'sort_order' => 1,
            'remarks' => 'Member-level note',
        ]);
    }

    public function test_get_for_edit_show_returns_grouped_travelers_shape(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-002',
            'name' => 'Umrah Premium',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-2002',
            'status' => 'draft',
        ]);

        $customerUser = User::factory()->create(['name' => 'Siti Example']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'date_of_birth' => '1990-01-01',
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => User::factory()->create()->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $service = app(ManifestService::class);
        $result = $service->getForEditShow($manifest->id);

        $this->assertIsArray($result['travelers']);
        $this->assertNotEmpty($result['travelers']);

        $this->assertSame('Siti Example', $result['travelers'][0]['name_as_per_passport']);
        $this->assertArrayHasKey('roomLists', $result);
        $this->assertArrayHasKey('airlineList', $result);
    }

    public function test_room_grouping_falls_back_to_each_customer_when_no_sharing_group_exists(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-003',
            'name' => 'Umrah Dynamic',
            'status' => 'open',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Traveler One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Traveler Two', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Traveler One',
                ],
                [
                    'sn' => 2,
                    'name_as_per_passport' => 'Traveler Two',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Traveler One',
                        'room_no' => 'M-201',
                        'room_type' => 'Double',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'name_as_per_passport' => 'Traveler Two',
                        'room_no' => 'M-202',
                        'room_type' => 'Double',
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::firstOrFail();

        $this->assertSame(2, $manifest->rooms()->count());
    }

    public function test_room_list_order_can_be_different_between_hotels_and_is_persisted(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-004',
            'name' => 'Umrah Multi Hotel',
            'status' => 'open',
        ]);

        $memberA = $this->createMemberForPackage($package->id, 'Traveler A', $actingUser->id);
        $memberB = $this->createMemberForPackage($package->id, 'Traveler B', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Traveler A',
                ],
                [
                    'sn' => 2,
                    'name_as_per_passport' => 'Traveler B',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberA->id,
                        'name_as_per_passport' => 'Traveler A',
                        'room_no' => 'MK-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => $memberB->id,
                        'name_as_per_passport' => 'Traveler B',
                        'room_no' => 'MK-02',
                        'sort_order' => 2,
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => $memberB->id,
                        'name_as_per_passport' => 'Traveler B',
                        'room_no' => 'MD-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => $memberA->id,
                        'name_as_per_passport' => 'Traveler A',
                        'room_no' => 'MD-02',
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::firstOrFail();

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => 'MK-01',
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'madinah',
            'room_number' => 'MD-01',
        ]);
    }

    public function test_store_rejects_customer_confirmation_member_from_different_package(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create();

        $this->actingAs($actingUser);

        $manifestPackage = Package::create([
            'package_number' => 'PKG-050',
            'name' => 'Umrah Package A',
            'status' => 'open',
        ]);

        $differentPackage = Package::create([
            'package_number' => 'PKG-051',
            'name' => 'Umrah Package B',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $differentPackage->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'draft',
        ]);

        $payload = [
            'package_id' => $manifestPackage->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Mismatch Traveler',
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertSessionHasErrors('travelers');

        $this->assertSame(0, Manifest::count());
    }

    public function test_update_room_members_uses_current_manifest_traveler_ids_after_reorder(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-UPDATE',
            'name' => 'Room Update Package',
            'status' => 'open',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Traveler One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Traveler Two', $actingUser->id);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-UPDATE',
            'status' => 'draft',
        ]);

        $travelerOne = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);

        $travelerTwo = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'id' => $travelerOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Traveler One',
                ],
                [
                    'id' => $travelerTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Traveler Two',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'id' => $travelerOne->id,
                        'manifest_traveler_id' => $travelerOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'sharing_group_key' => 'group-1',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'RM-1',
                    ],
                    [
                        'id' => $travelerTwo->id,
                        'manifest_traveler_id' => $travelerTwo->id,
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'sharing_group_key' => 'group-1',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'RM-1',
                    ],
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $currentTravelerIds = $manifest->travelers()->pluck('id')->all();

        $this->assertCount(2, $currentTravelerIds);
        $this->assertDatabaseCount('manifest_room_members', 2);

        foreach ($currentTravelerIds as $travelerId) {
            $this->assertDatabaseHas('manifest_room_members', [
                'manifest_traveler_id' => $travelerId,
            ]);
        }
    }

    public function test_manifest_traveler_can_be_moved_to_holding_confirmation(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create();

        $this->actingAs($actingUser);

        $sourcePackage = Package::create([
            'package_number' => 'PKG-HOLD-001',
            'name' => 'Umrah Holding Source',
            'status' => 'open',
        ]);

        $targetPackage = Package::create([
            'package_number' => 'PKG-HOLD-002',
            'name' => 'Umrah Holding Target',
            'status' => 'open',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $sourcePackage->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $member = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
        ]);

        $manifest = Manifest::create([
            'package_id' => $sourcePackage->id,
            'manifest_number' => 'MNF-HOLD-001',
            'status' => 'confirmed',
        ]);

        $traveler = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $this->postJson(route('manifests.travelers.move-holding', [
            'manifestId' => $manifest->id,
            'travelerId' => $traveler->id,
        ]), [
            'target_package_id' => $targetPackage->id,
        ])->assertOk();

        $this->assertDatabaseMissing('manifest_travelers', [
            'id' => $traveler->id,
        ]);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'id' => $member->id,
            'status' => 'cancelled',
        ]);

        $newConfirmation = CustomerConfirmation::query()
            ->where('id', '!=', $confirmation->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newConfirmation);
        $this->assertSame($targetPackage->id, $newConfirmation->package_id);

        $this->assertDatabaseHas('customer_confirmation_members', [
            'customer_confirmation_id' => $newConfirmation->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);
    }

    public function test_update_allows_room_number_to_be_empty_from_room_lists(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-NULL-001',
            'name' => 'Umrah Nullable Room Number',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-NULL-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Nullable Room Traveler', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Nullable Room Traveler',
                    'customer_confirmation_member_id' => $member->id,
                ],
            ],
            'roomLists' => [
                'mekkah' => [
                    [
                        'sn' => 1,
                        'name_as_per_passport' => 'Nullable Room Traveler',
                        'customer_confirmation_member_id' => $member->id,
                        'room_no' => null,
                        'room_type' => 'double',
                        'bed_type' => null,
                        'capacity' => 3,
                        'sharing_group_key' => 'group-1',
                    ],
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'mekkah',
            'room_number' => null,
            'room_type' => 'double',
        ]);
    }

    public function test_update_persists_regrouped_room_lists_and_rehydrates_group_keys(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-REGROUP-001',
            'name' => 'Umrah Regroup Persist',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-REGROUP-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Regroup One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Regroup Two', $actingUser->id);
        $memberThree = $this->createMemberForPackage($package->id, 'Regroup Three', $actingUser->id);

        $travelerOne = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);
        $travelerTwo = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
        ]);
        $travelerThree = ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberThree->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'id' => $travelerOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Regroup One',
                ],
                [
                    'id' => $travelerTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Regroup Two',
                ],
                [
                    'id' => $travelerThree->id,
                    'customer_confirmation_member_id' => $memberThree->id,
                    'name_as_per_passport' => 'Regroup Three',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_traveler_id' => $travelerOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Regroup One',
                        'sharing_group_key' => 'room-double-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room 1',
                        'room_no' => 'MK-501',
                        'sort_order' => 1,
                    ],
                    [
                        'manifest_traveler_id' => $travelerTwo->id,
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'name_as_per_passport' => 'Regroup Two',
                        'sharing_group_key' => 'room-double-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room 1',
                        'room_no' => 'MK-501',
                        'sort_order' => 2,
                    ],
                    [
                        'manifest_traveler_id' => $travelerThree->id,
                        'customer_confirmation_member_id' => $memberThree->id,
                        'name_as_per_passport' => 'Regroup Three',
                        'sharing_group_key' => 'room-single-b',
                        'sharing_plan' => 'single',
                        'room_type' => 'single',
                        'bed_type' => 'single',
                        'room_label' => 'Room 2',
                        'room_no' => 'MK-502',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $manifest->load(['rooms.roomMembers']);

        $this->assertCount(2, $manifest->rooms);
        $this->assertSame([1, 2], $manifest->rooms->pluck('roomMembers')->map->count()->sort()->values()->all());

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $roomRows = $rehydrated['roomLists']['makkah'] ?? [];

        $this->assertCount(3, $roomRows);
        $this->assertSame(2, collect($roomRows)->pluck('sharing_group_key')->filter()->unique()->count());
    }

    private function createMemberForPackage(int $packageId, string $customerName, int $createdBy): CustomerConfirmationMember
    {
        $user = User::factory()->create(['name' => $customerName]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $packageId,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $createdBy,
        ]);

        return CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'role' => 'member',
        ]);
    }
}

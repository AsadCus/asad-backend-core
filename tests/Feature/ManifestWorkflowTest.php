<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageAccommodation;
use App\Models\PackageOfficial;
use App\Models\Quotation;
use App\Models\QuotationExtension;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ManifestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_accepts_grouped_manifest_payload_and_normalizes_values(): void
    {
        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Ahmad Example',
            'contact' => '0199988877',
        ]);

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
            'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
            'first_time_umrah' => true,
            'has_chronic_disease' => false,
            'passport_path' => 'passports/customer-001.pdf',
            'photo_path' => 'photos/customer-001.jpg',
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
                    'passport_number' => 'A9999999',
                    'date_of_birth' => '1996-02-20',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'name_as_per_passport' => 'Ahmad Example',
                        'customer_confirmation_member_id' => $member->id,
                        'room_relationship' => 'Family',
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
        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'name' => 'Ahmad Example',
            'contact_number' => '0199988877',
            'passport_number' => 'A9999999',
            'address' => 'Jalan Bukit Bintang, Kuala Lumpur',
            'first_time_umrah' => 1,
            'has_chronic_disease' => 0,
            'passport_path' => 'passports/customer-001.pdf',
            'photo_path' => 'photos/customer-001.jpg',
        ]);

        $manifestMemberDob = ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('date_of_birth');

        $this->assertNotNull($manifestMemberDob);

        $customer->refresh();
        $this->assertSame('A9999999', $customer->passport_number);
        $this->assertSame('1996-02-20', optional($customer->date_of_birth)->format('Y-m-d'));

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'relationship' => 'Family',
            'room_number' => 'M-101',
            'room_type' => 'quad',
            'bed_type' => 'single',
            'meal' => 'Breakfast Only',
            'remarks' => 'Room-level note',
        ]);

        $createdTravelerId = (int) ManifestMember::query()
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

    public function test_update_persists_collection_items_checklist_fields(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-COLLECTION-001',
            'name' => 'Umrah Collection Checklist',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-COLLECTION-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Checklist Member', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Checklist Member',
                    'course_1' => true,
                    'course_2' => false,
                    'lanyard' => true,
                    'luggage_tag' => true,
                    'cabin_tag' => false,
                    'passport_cover' => true,
                    'umrah_guidebook' => true,
                    'sling_bag' => false,
                    'cabin_size_luggage' => true,
                    'umrah_essentials' => true,
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $travelerId = (int) ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->where('customer_confirmation_member_id', $member->id)
            ->value('id');

        $this->assertDatabaseHas('manifest_member_collection_items', [
            'manifest_member_id' => $travelerId,
            'course_1' => 1,
            'course_2' => 0,
            'lanyard' => 1,
            'luggage_tag' => 1,
            'cabin_tag' => 0,
            'passport_cover' => 1,
            'umrah_guidebook' => 1,
            'sling_bag' => 0,
            'cabin_size_luggage' => 1,
            'umrah_essentials' => 1,
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $this->assertTrue((bool) ($rehydrated['travelers'][0]['course_1'] ?? false));
        $this->assertTrue((bool) ($rehydrated['travelers'][0]['luggage_tag'] ?? false));
        $this->assertFalse((bool) ($rehydrated['travelers'][0]['cabin_tag'] ?? true));
    }

    public function test_manifest_status_updates_package_status_and_rehydrates_from_package(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-STATUS-LINK-001',
            'name' => 'Umrah Status Link',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-STATUS-LINK-001',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'closed',
            'travelers' => [],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $package->refresh();
        $this->assertSame('closed', $package->status);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $this->assertSame('closed', $rehydrated['status']);
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

        ManifestMember::create([
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

    public function test_update_persists_and_rehydrates_manifest_sharing_group_remarks(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-GROUP-REMARK-001',
            'name' => 'Umrah Group Remark',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-GROUP-REMARK-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Remark One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Remark Two', $actingUser->id);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Remark One',
                    'sharing_group_key' => 'group-remarks-a',
                    'sharing_plan' => 'double',
                    'group_remarks' => 'Shared group note from main tab',
                ],
                [
                    'sn' => 2,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Remark Two',
                    'sharing_group_key' => 'group-remarks-a',
                    'sharing_plan' => 'double',
                    'group_remarks' => 'Shared group note from main tab',
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $this->assertDatabaseHas('manifest_sharing_groups', [
            'manifest_id' => $manifest->id,
            'remarks' => 'Shared group note from main tab',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);
        $this->assertSame(
            'Shared group note from main tab',
            $rehydrated['travelers'][0]['group_remarks'] ?? null,
        );
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

        $travelerOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);

        $travelerTwo = ManifestMember::create([
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

        $traveler = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $this->postJson(route('manifests.travelers.move-holding', [
            'manifestId' => $manifest->id,
            'travelerId' => $traveler->id,
        ]), [
            'target_package_id' => $targetPackage->id,
        ])->assertOk();

        $this->assertDatabaseMissing('manifest_members', [
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
                'makkah' => [
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
            'location' => 'makkah',
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

        $travelerOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
        ]);
        $travelerTwo = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
        ]);
        $travelerThree = ManifestMember::create([
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

    public function test_room_member_sharing_plan_and_room_relationship_are_synced_on_update(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-SHARING-001',
            'name' => 'Umrah Room Sharing Sync',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-SHARING-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Sharing Member', $actingUser->id);
        $member->update([
            'sharing_plan' => 'single',
            'role' => 'Spouse',
        ]);

        $traveler = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'id' => $traveler->id,
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Sharing Member',
                    'sharing_plan' => 'double',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_traveler_id' => $traveler->id,
                        'customer_confirmation_member_id' => $member->id,
                        'name_as_per_passport' => 'Sharing Member',
                        'sharing_group_key' => 'room-relationship-a',
                        'room_relationship' => 'Family',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room A',
                        'room_no' => 'MK-700',
                        'sort_order' => 1,
                    ],
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $member->refresh();

        $this->assertSame('double', $member->sharing_plan);
        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'relationship' => 'Family',
            'sharing_plan' => 'double',
            'room_label' => 'Room A',
        ]);
    }

    public function test_relationship_update_does_not_override_member_role(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROLE-REL-001',
            'name' => 'Role Relation Package',
            'status' => 'open',
        ]);

        $customerUser = User::factory()->create([
            'name' => 'Traveler Role Relation',
            'contact' => '0198877665',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
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
            'status' => 'confirmed',
            'role' => 'wife',
            'sharing_plan' => 'double',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROLE-REL-001',
            'status' => 'draft',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Traveler Role Relation',
                    'relationship' => 'family',
                    'sharing_group_key' => 'group-role-rel-1',
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $member->refresh();
        $manifest->refresh();

        $this->assertSame('wife', $member->role);
        $this->assertSame('family', $manifest->manifestSharingGroups()->value('relation'));
    }

    public function test_update_adds_new_member_into_existing_confirmation_room_capacity_before_creating_new_group(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-CAPACITY-001',
            'name' => 'Umrah Capacity Assignment',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $members = collect();

        foreach (range(1, 4) as $index) {
            $user = User::factory()->create(['name' => 'Capacity Member '.$index]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            $members->push(CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $index === 1,
                'status' => 'confirmed',
                'role' => 'member',
                'sharing_plan' => 'double',
            ]));
        }

        $first = $members->get(0);
        $second = $members->get(1);
        $third = $members->get(2);
        $fourth = $members->get(3);

        $this->post(route('manifests.store'), [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $first->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 1',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-a',
                ],
                [
                    'customer_confirmation_member_id' => $second->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 2',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-a',
                ],
                [
                    'customer_confirmation_member_id' => $third->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Capacity Member 3',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'group-double-b',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $first->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 1',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-a',
                        'room_type' => 'double',
                    ],
                    [
                        'customer_confirmation_member_id' => $second->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 2',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-a',
                        'room_type' => 'double',
                    ],
                    [
                        'customer_confirmation_member_id' => $third->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Capacity Member 3',
                        'sharing_plan' => 'double',
                        'sharing_group_key' => 'room-double-b',
                        'room_type' => 'double',
                    ],
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $manifest = Manifest::query()->firstOrFail();
        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);

        $payload['travelers'][] = [
            'customer_confirmation_member_id' => $fourth->id,
            'customer_confirmation_id' => $confirmation->id,
            'name_as_per_passport' => 'Capacity Member 4',
            'sharing_plan' => 'double',
            'sharing_group_key' => 'solo-'.$fourth->id,
        ];

        $payload['roomLists']['makkah'][] = [
            'customer_confirmation_member_id' => $fourth->id,
            'customer_confirmation_id' => $confirmation->id,
            'name_as_per_passport' => 'Capacity Member 4',
            'sharing_plan' => 'double',
            'sharing_group_key' => 'solo-'.$fourth->id,
            'room_type' => 'double',
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $groupMemberCounts = $manifest->manifestSharingGroups()
            ->where('customer_confirmation_id', $confirmation->id)
            ->withCount('members')
            ->orderBy('id')
            ->get()
            ->pluck('members_count')
            ->sort()
            ->values()
            ->all();

        $roomMemberCounts = $manifest->rooms()
            ->withCount('roomMembers')
            ->orderBy('id')
            ->get()
            ->pluck('room_members_count')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([2, 2], $groupMemberCounts);
        $this->assertSame([2, 2], $roomMemberCounts);
    }

    public function test_update_keeps_new_confirmation_members_separate_from_official_group_and_assigns_rooms_per_location(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-MIXED-GROUP-001',
            'name' => 'Mixed Group Isolation',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Member One', $actingUser->id, $confirmation->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Member Two', $actingUser->id, $confirmation->id);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Official One',
            'type' => 'mutawwif',
            'passport_number' => 'OFF-MIX-001',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-MIXED-GROUP-001',
            'status' => 'draft',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'package_official_id' => $official->id,
                    'name_as_per_passport' => 'Official One',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $memberOne->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Member One',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 2,
                ],
                [
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Member Two',
                    'sharing_group_key' => 'shared-main-group',
                    'sharing_plan' => 'double',
                    'group_sort_order' => 2,
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member One',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MK-001',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member Two',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MK-001',
                    ],
                    [
                        'package_official_id' => $official->id,
                        'name_as_per_passport' => 'Official One',
                        'sharing_group_key' => 'room-shared-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MK-001',
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => $memberOne->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member One',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MD-001',
                    ],
                    [
                        'customer_confirmation_member_id' => $memberTwo->id,
                        'customer_confirmation_id' => $confirmation->id,
                        'name_as_per_passport' => 'Member Two',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MD-001',
                    ],
                    [
                        'package_official_id' => $official->id,
                        'name_as_per_passport' => 'Official One',
                        'sharing_group_key' => 'room-shared-b',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_no' => 'MD-001',
                    ],
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();

        $groups = $manifest->manifestSharingGroups()
            ->withCount('members')
            ->orderBy('sort_order')
            ->get();

        $this->assertSame([1, 2], $groups->pluck('members_count')->sort()->values()->all());
        $this->assertSame(1, $groups->whereNotNull('customer_confirmation_id')->count());
    }

    public function test_update_persists_group_member_sort_order_and_keeps_official_groups_last(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ORDER-001',
            'name' => 'Ordering Package',
            'status' => 'open',
        ]);

        $customerUser = User::factory()->create(['name' => 'Sorted Member']);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
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
            'status' => 'confirmed',
            'role' => 'member',
            'sharing_plan' => 'double',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'name' => 'Official Member',
            'type' => 'mutawwif',
            'passport_number' => 'OFF-0001',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ORDER-001',
            'status' => 'draft',
        ]);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'package_official_id' => $official->id,
                    'name_as_per_passport' => 'Official Member',
                    'sharing_group_key' => 'official-group',
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $member->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Sorted Member',
                    'sharing_group_key' => 'member-group',
                    'group_sort_order' => 2,
                    'sort_order' => 1,
                ],
            ],
        ], (int) $manifest->id);

        $manifest->refresh();
        $orderedGroups = $manifest->manifestSharingGroups()
            ->orderBy('sort_order')
            ->with('members')
            ->get();

        $this->assertCount(2, $orderedGroups);
        $this->assertNotNull($orderedGroups[0]->customer_confirmation_id);
        $this->assertNull($orderedGroups[1]->customer_confirmation_id);

        $result = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $travelers = $result['travelers'];

        $this->assertCount(2, $travelers);
        $this->assertSame($member->id, $travelers[0]['customer_confirmation_member_id']);
        $this->assertSame($official->id, $travelers[1]['package_official_id']);
        $this->assertSame(1, (int) ($travelers[0]['group_sort_order'] ?? 0));
        $this->assertSame(2, (int) ($travelers[1]['group_sort_order'] ?? 0));
    }

    public function test_update_keeps_member_order_stable_inside_same_sharing_group_across_repeated_updates(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-MEMBER-ORDER-001',
            'name' => 'Member Order Stability',
            'status' => 'open',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'triple',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $makeMember = function (string $name) use ($confirmation): CustomerConfirmationMember {
            $user = User::factory()->create(['name' => $name]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'status' => 'confirmed',
                'role' => 'member',
                'sharing_plan' => 'triple',
            ]);
        };

        $memberA = $makeMember('Order A');
        $memberB = $makeMember('Order B');
        $memberC = $makeMember('Order C');

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-MEMBER-ORDER-001',
            'status' => 'draft',
        ]);

        $sharedGroupKey = 'stable-group-1';

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $memberA->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order A',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 1,
                ],
                [
                    'customer_confirmation_member_id' => $memberB->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order B',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 2,
                ],
                [
                    'customer_confirmation_member_id' => $memberC->id,
                    'customer_confirmation_id' => $confirmation->id,
                    'name_as_per_passport' => 'Order C',
                    'sharing_group_key' => $sharedGroupKey,
                    'group_sort_order' => 1,
                    'sort_order' => 3,
                ],
            ],
        ], (int) $manifest->id);

        $service = app(ManifestService::class);
        $payload = $service->getForEditShow((int) $manifest->id);

        $this->assertSame($memberA->id, $payload['travelers'][0]['customer_confirmation_member_id']);
        $this->assertSame($memberB->id, $payload['travelers'][1]['customer_confirmation_member_id']);
        $this->assertSame($memberC->id, $payload['travelers'][2]['customer_confirmation_member_id']);

        app(ManifestService::class)->update([
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => $payload['travelers'],
        ], (int) $manifest->id);

        $payloadAfterSecondUpdate = $service->getForEditShow((int) $manifest->id);

        $this->assertSame($memberA->id, $payloadAfterSecondUpdate['travelers'][0]['customer_confirmation_member_id']);
        $this->assertSame($memberB->id, $payloadAfterSecondUpdate['travelers'][1]['customer_confirmation_member_id']);
        $this->assertSame($memberC->id, $payloadAfterSecondUpdate['travelers'][2]['customer_confirmation_member_id']);
    }

    public function test_update_removes_members_missing_from_room_list_and_drops_empty_rooms(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-UNASSIGN-001',
            'name' => 'Room Unassign Cleanup',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-UNASSIGN-001',
            'status' => 'draft',
        ]);

        $memberOne = $this->createMemberForPackage($package->id, 'Room Keep One', $actingUser->id);
        $memberTwo = $this->createMemberForPackage($package->id, 'Room Remove Two', $actingUser->id);
        $memberThree = $this->createMemberForPackage($package->id, 'Room Remove Three', $actingUser->id);

        $travelerOne = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberOne->id,
            'name' => 'Room Keep One',
        ]);

        $travelerTwo = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberTwo->id,
            'name' => 'Room Remove Two',
        ]);

        $travelerThree = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $memberThree->id,
            'name' => 'Room Remove Three',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'id' => $travelerOne->id,
                    'customer_confirmation_member_id' => $memberOne->id,
                    'name_as_per_passport' => 'Room Keep One',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $travelerTwo->id,
                    'customer_confirmation_member_id' => $memberTwo->id,
                    'name_as_per_passport' => 'Room Remove Two',
                    'sharing_plan' => 'double',
                ],
                [
                    'id' => $travelerThree->id,
                    'customer_confirmation_member_id' => $memberThree->id,
                    'name_as_per_passport' => 'Room Remove Three',
                    'sharing_plan' => 'single',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'manifest_traveler_id' => $travelerOne->id,
                        'customer_confirmation_member_id' => $memberOne->id,
                        'name_as_per_passport' => 'Room Keep One',
                        'sharing_group_key' => 'room-keep-a',
                        'sharing_plan' => 'double',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'room_label' => 'Room Keep',
                        'room_no' => 'MK-801',
                    ],
                ],
            ],
        ];

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();
        $manifest->load(['rooms.roomMembers', 'travelers']);

        $this->assertCount(1, $manifest->rooms);
        $this->assertSame('MK-801', $manifest->rooms[0]->room_number);
        $this->assertCount(1, $manifest->rooms[0]->roomMembers);

        $persistedTravelerOneId = (int) $manifest->travelers()
            ->where('customer_confirmation_member_id', $memberOne->id)
            ->value('id');

        $this->assertSame($persistedTravelerOneId, (int) $manifest->rooms[0]->roomMembers[0]->manifest_traveler_id);
    }

    public function test_collection_items_pdf_export_returns_pdf_response(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-COLLECTION-PDF-001',
            'name' => 'Collection PDF Package',
            'status' => 'open',
            'departure_date' => '2026-03-20',
            'return_date' => '2026-03-30',
        ]);

        PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel One',
            'check_in' => '2026-03-21',
            'check_out' => '2026-03-25',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-COLLECTION-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Collection Pdf Member', $actingUser->id);

        $this->put(route('manifests.update', $manifest->id), [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Collection Pdf Member',
                    'course_1' => true,
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.collection-items-pdf', $manifest->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_room_check_pdf_export_returns_pdf_response_for_location(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-ROOM-CHECK-PDF-001',
            'name' => 'Room Check PDF Package',
            'status' => 'open',
            'departure_date' => '2026-04-01',
            'return_date' => '2026-04-10',
        ]);

        PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel Check',
            'check_in' => '2026-04-02',
            'check_out' => '2026-04-06',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-ROOM-CHECK-PDF-001',
            'status' => 'draft',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Room Check Member', $actingUser->id);

        $this->put(route('manifests.update', $manifest->id), [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'customer_confirmation_member_id' => $member->id,
                    'name_as_per_passport' => 'Room Check Member',
                    'sharing_plan' => 'double',
                    'sharing_group_key' => 'room-check-group-1',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'customer_confirmation_member_id' => $member->id,
                        'name_as_per_passport' => 'Room Check Member',
                        'sharing_group_key' => 'room-check-group-1',
                        'room_relationship' => 'Family',
                        'room_label' => 'Room 1',
                        'room_no' => 'MK-401',
                        'room_type' => 'double',
                        'bed_type' => 'king',
                        'number_of_beds_checked' => true,
                        'meal' => 'Breakfast Only',
                        'remarks' => 'Member note',
                        'room_remarks' => 'Room note',
                    ],
                ],
            ],
        ])->assertRedirect(route('manifests.index'));

        $response = $this->get(route('manifests.room-check-pdf', [
            'id' => $manifest->id,
            'location' => 'makkah',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_get_for_edit_show_fills_financial_columns_from_receipts_and_discount_extensions(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-001',
            'name' => 'Manifest Financial Snapshot',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-001',
        ]);

        $member = $this->createMemberForPackage($package->id, 'Financial Member', $actingUser->id);
        $member->update(['sharing_plan' => 'double']);

        $manifestTraveler = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $member->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $member->customer_id,
            'customer_confirmation_id' => $member->customer_confirmation_id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        QuotationExtension::create([
            'quotation_id' => $quotation->id,
            'name' => 'Promo Discount',
            'type' => 'discount',
            'amount' => -300,
            'sort_order' => 1,
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'description' => 'Package installment',
            'is_header' => false,
            'quantity' => 1,
            'rate' => 5000,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'amount' => 1000,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1000,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'amount' => 1500,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 1500,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'amount' => 500,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync([$quotationItem->id]);

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 500,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $travelerRow = collect($rehydrated['travelers'])
            ->firstWhere('customer_confirmation_member_id', $member->id);

        $this->assertNotNull($travelerRow);
        $this->assertSame(300.0, (float) ($travelerRow['discount'] ?? 0));
        $this->assertSame(
            Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            $travelerRow['date_of_deposit_payment']
        );
        $this->assertSame(1000.0, (float) ($travelerRow['deposit_payment'] ?? 0));
        $this->assertSame(
            Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            $travelerRow['date_of_second_payment']
        );
        $this->assertSame(2000.0, (float) ($travelerRow['second_payment'] ?? 0));
        $this->assertSame(1700.0, (float) ($travelerRow['balance_due'] ?? 0));
    }

    public function test_get_for_edit_show_splits_receipt_amounts_by_carried_members_in_each_receipt(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-FIN-002',
            'name' => 'Manifest Financial Shared Receipt Snapshot',
            'status' => 'open',
            'price_double' => 5000,
            'total_seats' => 20,
            'seats_left' => 20,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-FIN-002',
        ]);

        $confirmation = CustomerConfirmation::create([
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => 'classic_umrah',
            'created_by' => $actingUser->id,
        ]);

        $memberUsers = [
            User::factory()->create(['name' => 'Shared Receipt Member One']),
            User::factory()->create(['name' => 'Shared Receipt Member Two']),
            User::factory()->create(['name' => 'Shared Receipt Member Three']),
        ];

        $members = collect($memberUsers)->map(function (User $user) use ($confirmation) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            return CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => false,
                'status' => 'confirmed',
                'role' => 'member',
                'sharing_plan' => 'double',
            ]);
        })->values();

        $primaryMember = $members->first();

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $primaryMember->id,
            'sharing_plan' => 'double',
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create([
            'customer_id' => $primaryMember->customer_id,
            'customer_confirmation_id' => $confirmation->id,
            'quotation_date' => '2026-03-01',
            'expiry_date' => '2026-03-31',
            'payment_plan' => 'installment',
            'status' => 'converted',
        ]);

        QuotationExtension::create([
            'quotation_id' => $quotation->id,
            'name' => 'Group Discount',
            'type' => 'discount',
            'amount' => -300,
            'sort_order' => 1,
        ]);

        $quotationItems = $members->map(function (CustomerConfirmationMember $member, int $index) use ($quotation) {
            return QuotationItem::create([
                'quotation_id' => $quotation->id,
                'customer_confirmation_member_id' => $member->id,
                'description' => 'Installment member #'.($index + 1),
                'is_header' => false,
                'quantity' => 1,
                'rate' => 5000,
                'sort_order' => $index + 1,
            ]);
        });

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'installment',
        ]);

        $firstInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Deposit invoice',
            'amount' => 1500,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'status' => 'issued',
        ]);
        $firstInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $firstInvoice->id,
            'amount' => 1500,
            'receipt_date' => '2026-03-01',
            'payment_method' => 'transfer',
        ]);

        $secondInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Second invoice',
            'amount' => 3000,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-03-10',
            'status' => 'issued',
        ]);
        $secondInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $secondInvoice->id,
            'amount' => 3000,
            'receipt_date' => '2026-03-10',
            'payment_method' => 'transfer',
        ]);

        $thirdInvoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Final invoice',
            'amount' => 600,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-20',
            'status' => 'issued',
        ]);
        $thirdInvoice->quotationItems()->sync($quotationItems->pluck('id')->all());

        Receipt::create([
            'invoice_id' => $thirdInvoice->id,
            'amount' => 600,
            'receipt_date' => '2026-03-20',
            'payment_method' => 'transfer',
        ]);

        $rehydrated = app(ManifestService::class)->getForEditShow($manifest->id);

        $travelerRow = collect($rehydrated['travelers'])
            ->firstWhere('customer_confirmation_member_id', $primaryMember->id);

        $this->assertNotNull($travelerRow);
        $this->assertSame(100.0, (float) ($travelerRow['discount'] ?? 0));
        $this->assertSame(
            Carbon::parse('2026-03-01')->translatedFormat('d F Y'),
            $travelerRow['date_of_deposit_payment']
        );
        $this->assertSame(500.0, (float) ($travelerRow['deposit_payment'] ?? 0));
        $this->assertSame(
            Carbon::parse('2026-03-20')->translatedFormat('d F Y'),
            $travelerRow['date_of_second_payment']
        );
        $this->assertSame(1200.0, (float) ($travelerRow['second_payment'] ?? 0));
        $this->assertSame(3200.0, (float) ($travelerRow['balance_due'] ?? 0));
    }

    private function createMemberForPackage(int $packageId, string $customerName, int $createdBy, ?int $confirmationId = null): CustomerConfirmationMember
    {
        $user = User::factory()->create(['name' => $customerName]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        if ($confirmationId === null) {
            $confirmation = CustomerConfirmation::create([
                'package_id' => $packageId,
                'package_room_type' => 'double',
                'package_category' => 'classic_umrah',
                'created_by' => $createdBy,
            ]);

            $confirmationId = $confirmation->id;
        }

        return CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmationId,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'role' => 'member',
        ]);
    }
}

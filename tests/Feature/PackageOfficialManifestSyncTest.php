<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ManifestService;
use App\Services\PackageSeatService;
use App\Services\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageOfficialManifestSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_package_adds_officials_to_auto_created_manifest_without_consuming_seats(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Manifest Sync Package',
            'status' => 'open',
            'total_seats' => 5,
            'officials' => [
                [
                    'type' => 'mutawif',
                    'name' => 'Ustaz Adam',
                    'contact_number' => '0101001001',
                    'nationality' => 'Malaysian',
                    'passport_number' => 'OFF-0001',
                    'gender' => 'male',
                    'date_of_birth' => '1980-01-10',
                    'passport_issue_date' => '2022-01-01',
                    'passport_expiry_date' => '2032-01-01',
                    'passport_place_of_issue' => 'Kuala Lumpur',
                    'place_of_birth' => 'Kuala Lumpur',
                ],
                [
                    'type' => 'official',
                    'name' => 'Ops Lead',
                    'contact_number' => '0101001002',
                ],
            ],
        ]);

        $manifest = $package->manifests()->first();

        $this->assertNotNull($manifest);

        $officialMembers = $manifest->members()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', '[package-official]%')
            ->get();

        $this->assertCount(2, $officialMembers);
        $this->assertNotNull($officialMembers->first()?->package_official_id);
        $this->assertSame('Ustaz Adam', $officialMembers->first()?->name);
        $this->assertSame('0101001001', $officialMembers->first()?->contact_number);
        $this->assertSame('OFF-0001', $officialMembers->first()?->passport_number);
        $this->assertSame('mutawif', $officialMembers->first()?->relationship);
        $this->assertSame('single', $officialMembers->first()?->sharing_plan);

        $package->refresh();
        $this->assertEquals(5, $package->seats_left);

        $manifestPayload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $officialRow = collect($manifestPayload['members'] ?? [])->first(function (array $member): bool {
            return ($member['name_as_per_passport'] ?? null) === 'Ustaz Adam';
        });

        $this->assertNotNull($officialRow);
        $this->assertSame('Ustaz Adam', $officialRow['name_as_per_passport']);
        $this->assertSame('0101001001', $officialRow['contact_no']);
    }

    public function test_updating_manifest_official_updates_package_official_and_manifest_snapshot(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Sync Edit Package',
            'status' => 'open',
            'total_seats' => 5,
            'officials' => [
                [
                    'type' => 'mutawif',
                    'name' => 'Ustaz Initial',
                    'contact_number' => '0110000000',
                    'nationality' => 'Malaysian',
                    'passport_number' => 'OFF-INIT',
                    'gender' => 'male',
                    'date_of_birth' => '1982-01-01',
                    'passport_issue_date' => '2021-01-01',
                    'passport_expiry_date' => '2031-01-01',
                    'passport_place_of_issue' => 'Kuala Lumpur',
                    'place_of_birth' => 'Kuala Lumpur',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $payload['members'][0]['name_as_per_passport'] = 'Ustaz Updated';
        $payload['members'][0]['contact_no'] = '0122222222';
        $payload['members'][0]['nationality'] = 'Indonesian';
        $payload['members'][0]['passport_number'] = 'OFF-UPD';
        $payload['members'][0]['gender'] = 'male';
        $payload['members'][0]['date_of_birth'] = '01 January 1985';
        $payload['members'][0]['date_of_issue'] = '01 February 2022';
        $payload['members'][0]['date_of_expiry'] = '01 February 2032';
        $payload['members'][0]['issue_place'] = 'Bandung';
        $payload['members'][0]['birth_place'] = 'Bandung';
        $payload['members'][0]['role'] = 'guide';
        $payload['members'][0]['sharing_plan'] = 'single';

        app(ManifestService::class)->update($payload, (int) $manifest->id);

        $packageOfficialId = (int) $payload['members'][0]['package_official_id'];

        $this->assertDatabaseHas('package_officials', [
            'id' => $packageOfficialId,
            'name' => 'Ustaz Updated',
            'contact_number' => '0122222222',
            'passport_number' => 'OFF-UPD',
            'passport_place_of_issue' => 'Bandung',
            'place_of_birth' => 'Bandung',
        ]);

        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'package_official_id' => $packageOfficialId,
            'name' => 'Ustaz Updated',
            'contact_number' => '0122222222',
            'passport_number' => 'OFF-UPD',
            'passport_place_of_issue' => 'Bandung',
            'place_of_birth' => 'Bandung',
        ]);
    }

    public function test_manifest_official_falls_back_to_package_official_data_when_snapshot_is_empty(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Fallback Package',
            'status' => 'open',
            'total_seats' => 3,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Fallback Official',
                    'contact_number' => '0133333333',
                    'nationality' => 'Malaysian',
                    'passport_number' => 'OFF-FALLBACK',
                    'gender' => 'female',
                    'passport_place_of_issue' => 'Putrajaya',
                    'place_of_birth' => 'Putrajaya',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();
        $officialMember = $manifest->members()->whereNotNull('package_official_id')->firstOrFail();

        $officialMember->update([
            'name' => null,
            'contact_number' => null,
            'passport_number' => null,
        ]);

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $officialRow = collect($payload['members'] ?? [])->first(function (array $member): bool {
            return ($member['package_official_id'] ?? null) !== null;
        });

        $this->assertNotNull($officialRow);
        $this->assertSame('Fallback Official', $officialRow['name_as_per_passport']);
        $this->assertSame('0133333333', $officialRow['contact_no']);
        $this->assertSame('OFF-FALLBACK', $officialRow['passport_number']);
    }

    public function test_manifest_and_package_counts_exclude_official_members(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Count Exclusion Package',
            'status' => 'open',
            'total_seats' => 5,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops Official',
                    'contact_number' => '0190000000',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();

        $manifest->members()->create([
            'name' => 'Regular Member',
            'sort_order' => 2,
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $manifestData = app(ManifestService::class)
            ->getForDataTable()
            ->firstWhere('id', $manifest->id);

        $this->assertNotNull($manifestData);
        $this->assertSame(1, $manifestData['members_count']);

        $packageData = app(PackageService::class)
            ->getForDataTable()
            ->firstWhere('id', $package->id);

        $this->assertNotNull($packageData);
        $this->assertSame(1, $packageData['occupied_seats']);
        $this->assertSame(4, $packageData['seats_left']);

        $package->refresh();
        $this->assertSame(4, $package->seats_left);
    }

    public function test_package_official_sync_creates_and_removes_official_rooms(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Room Sync Package',
            'status' => 'open',
            'total_seats' => 10,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops One',
                    'contact_number' => '0191010101',
                ],
                [
                    'type' => 'official',
                    'name' => 'Ops Two',
                    'contact_number' => '0192020202',
                ],
            ],
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Hotel A',
                    'type_of_meal' => 'Breakfast Only',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();

        $officialRooms = $manifest->rooms()
            ->where('remarks', 'like', '[package-official-room]%')
            ->with('roomMembers.member')
            ->get();

        $this->assertNotEmpty($officialRooms);
        $this->assertTrue($officialRooms->every(function ($room): bool {
            return $room->roomMembers->isNotEmpty()
                && $room->roomMembers->every(function ($member): bool {
                    return $member->member?->package_official_id !== null
                        && (bool) $member->is_assigned === true;
                });
        }));

        app(PackageService::class)->update([
            'officials' => [],
        ], (int) $package->id);

        $this->assertDatabaseCount('package_officials', 0);
        $this->assertSame(0, $manifest->members()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', '[package-official]%')
            ->count());
        $this->assertSame(0, $manifest->rooms()
            ->where('remarks', 'like', '[package-official-room]%')
            ->count());
    }

    public function test_package_update_preserves_existing_official_ids_when_updating_officials(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Stable ID Package',
            'status' => 'open',
            'total_seats' => 8,
            'officials' => [
                [
                    'type' => 'mutawif',
                    'name' => 'Guide One',
                    'contact_number' => '0194000001',
                ],
                [
                    'type' => 'official',
                    'name' => 'Ops One',
                    'contact_number' => '0194000002',
                ],
            ],
        ]);

        $officials = $package->officials()->orderBy('id')->get()->values();
        $this->assertCount(2, $officials);

        $firstOfficialId = (int) $officials[0]->id;
        $secondOfficialId = (int) $officials[1]->id;

        app(PackageService::class)->update([
            'officials' => [
                [
                    'id' => $firstOfficialId,
                    'type' => 'mutawif',
                    'name' => 'Guide One Updated',
                    'contact_number' => '0194999991',
                ],
                [
                    'id' => $secondOfficialId,
                    'type' => 'official',
                    'name' => 'Ops One',
                    'contact_number' => '0194000002',
                ],
                [
                    'type' => 'official',
                    'name' => 'Ops Two New',
                    'contact_number' => '0194000003',
                ],
            ],
        ], (int) $package->id);

        $package->refresh();
        $manifest = $package->manifests()->firstOrFail();

        $updatedOfficials = $package->officials()->orderBy('id')->get();
        $this->assertCount(3, $updatedOfficials);

        $this->assertTrue($updatedOfficials->contains(fn ($official): bool => (int) $official->id === $firstOfficialId));
        $this->assertTrue($updatedOfficials->contains(fn ($official): bool => (int) $official->id === $secondOfficialId));

        $this->assertDatabaseHas('package_officials', [
            'id' => $firstOfficialId,
            'name' => 'Guide One Updated',
            'contact_number' => '0194999991',
        ]);

        $this->assertDatabaseHas('manifest_members', [
            'manifest_id' => $manifest->id,
            'package_official_id' => $firstOfficialId,
            'name' => 'Guide One Updated',
            'contact_number' => '0194999991',
        ]);
    }

    public function test_package_update_persists_official_hotel_map_per_accommodation(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Hotel Map Package',
            'status' => 'open',
            'total_seats' => 8,
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Makkah Base Hotel',
                ],
                [
                    'location' => 'Madinah',
                    'hotel_name' => 'Madinah Base Hotel',
                ],
            ],
            'officials' => [
                [
                    'type' => 'mutawif',
                    'name' => 'Guide Hotel Map',
                    'contact_number' => '0197000001',
                ],
            ],
        ]);

        $official = $package->officials()->firstOrFail();
        $accommodations = $package->accommodations()->orderBy('id')->get()->values();

        $makkahAccommodationId = (string) ($accommodations[0]->id ?? 0);
        $madinahAccommodationId = (string) ($accommodations[1]->id ?? 0);

        app(PackageService::class)->update([
            'officials' => [
                [
                    'id' => (int) $official->id,
                    'type' => 'mutawif',
                    'name' => 'Guide Hotel Map',
                    'contact_number' => '0197000001',
                    'hotel_map' => [
                        $makkahAccommodationId => 'Official Hotel Makkah',
                        $madinahAccommodationId => 'Official Hotel Madinah',
                    ],
                ],
            ],
        ], (int) $package->id);

        $updatedPackage = $package->fresh();
        $updatedOfficial = $updatedPackage->officials()->firstOrFail();

        $this->assertEquals([
            $makkahAccommodationId => 'Official Hotel Makkah',
            $madinahAccommodationId => 'Official Hotel Madinah',
        ], $updatedOfficial->hotel ?? []);

        $payload = app(PackageService::class)->getForEditShow((int) $package->id);
        $payloadOfficial = $payload['officials'][0] ?? [];

        $this->assertSame('Official Hotel Makkah', $payloadOfficial['hotel'] ?? null);
        $this->assertEquals([
            $makkahAccommodationId => 'Official Hotel Makkah',
            $madinahAccommodationId => 'Official Hotel Madinah',
        ], $payloadOfficial['hotel_map'] ?? []);
    }

    public function test_package_update_assigns_officials_into_manifest_sharing_groups_for_manifest_form_visibility(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Group Visibility Package',
            'status' => 'open',
            'total_seats' => 8,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops Grouped',
                    'contact_number' => '0195000001',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();
        $official = $package->officials()->firstOrFail();

        app(PackageService::class)->update([
            'officials' => [
                [
                    'id' => $official->id,
                    'type' => 'official',
                    'name' => 'Ops Grouped Updated',
                    'contact_number' => '0195000002',
                ],
            ],
        ], (int) $package->id);

        $officialMember = $manifest->members()
            ->where('package_official_id', $official->id)
            ->firstOrFail();

        $this->assertNotNull($officialMember->manifest_sharing_group_id);

        $this->assertDatabaseHas('manifest_sharing_groups', [
            'id' => (int) $officialMember->manifest_sharing_group_id,
            'manifest_id' => $manifest->id,
            'group_relationship' => 'official',
            'remarks' => '[package-official-group] '.$official->id,
        ]);

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $canonicalGroups = collect($payload['manifest_sharing_groups'] ?? []);
        $foundOfficialInCanonicalGroups = $canonicalGroups
            ->flatMap(fn (array $group) => $group['members'] ?? [])
            ->contains(fn (array $member): bool => (int) ($member['package_official_id'] ?? 0) === (int) $official->id);

        $this->assertTrue($foundOfficialInCanonicalGroups);
    }

    public function test_updating_manifest_persists_official_room_members_with_id_fallback(): void
    {
        $actingUser = User::factory()->create();
        $this->actingAs($actingUser);

        $package = app(PackageService::class)->store([
            'name' => 'Official Room Payload Fallback Package',
            'status' => 'open',
            'total_seats' => 8,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops Fallback',
                    'contact_number' => '0180000001',
                ],
            ],
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Fallback Hotel',
                    'type_of_meal' => 'Breakfast Only',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();
        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);

        foreach ($payload['roomLists'] as $roomKey => $rows) {
            $payload['roomLists'][$roomKey] = array_map(function (array $row): array {
                if (! empty($row['package_official_id'])) {
                    unset($row['manifest_member_id']);
                }

                return $row;
            }, $rows);
        }

        $this->post(route('manifests.store'), [...$payload, 'id' => $manifest->id])
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $officialMemberIds = $manifest->members()
            ->whereNotNull('package_official_id')
            ->pluck('id');

        $this->assertTrue($officialMemberIds->isNotEmpty());

        $this->assertTrue($officialMemberIds->isNotEmpty());
    }

    public function test_package_update_preserves_user_managed_official_group_and_room_assignment(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Manual Placement Package',
            'status' => 'open',
            'total_seats' => 8,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops Manual',
                    'contact_number' => '0181111111',
                ],
            ],
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Manual Hotel',
                    'type_of_meal' => 'Breakfast Only',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();
        $officialMember = $manifest->members()
            ->whereNotNull('package_official_id')
            ->firstOrFail();

        $customGroup = $manifest->manifestSharingGroups()->create([
            'customer_confirmation_id' => null,
            'sort_order' => 999,
            'group_relationship' => 'family',
            'remarks' => 'manual-group',
        ]);

        $officialMember->update([
            'manifest_sharing_group_id' => $customGroup->id,
        ]);

        $customRoom = $manifest->rooms()->create([
            'sort_order' => 999,
            'location' => 'makkah',
            'group_relationship' => 'family',
            'room_label' => 'Manual Room',
            'room_type' => 'single',
            'bed_type' => 'single',
            'capacity' => 1,
            'status' => 'assigned',
            'meal' => 'Breakfast Only',
            'number_of_beds_checked' => false,
            'remarks' => null,
        ]);

        $existingAssignment = $officialMember->roomAssignments()->firstOrFail();
        $existingAssignment->update([
            'manifest_room_id' => $customRoom->id,
        ]);

        $official = $package->officials()->firstOrFail();

        app(PackageService::class)->update([
            'officials' => [
                [
                    'id' => $official->id,
                    'type' => 'official',
                    'name' => 'Ops Manual Updated',
                    'contact_number' => '0181111112',
                ],
            ],
        ], (int) $package->id);

        $officialMember->refresh();
        $existingAssignment->refresh();

        $this->assertSame((int) $customGroup->id, (int) $officialMember->manifest_sharing_group_id);
        $this->assertSame((int) $customRoom->id, (int) $existingAssignment->manifest_room_id);

        $this->assertDatabaseHas('manifest_members', [
            'id' => $officialMember->id,
            'manifest_sharing_group_id' => $customGroup->id,
            'name' => 'Ops Manual Updated',
            'contact_number' => '0181111112',
        ]);

        $this->assertDatabaseHas('manifest_room_members', [
            'id' => $existingAssignment->id,
            'manifest_room_id' => $customRoom->id,
            'manifest_member_id' => $officialMember->id,
        ]);
    }

    public function test_package_update_does_not_duplicate_officials_when_marker_remarks_are_missing(): void
    {
        $package = app(PackageService::class)->store([
            'name' => 'Official Markerless Sync Package',
            'status' => 'open',
            'total_seats' => 8,
            'officials' => [
                [
                    'type' => 'official',
                    'name' => 'Ops Markerless',
                    'contact_number' => '0187777001',
                ],
            ],
            'accommodations' => [
                [
                    'location' => 'Makkah',
                    'hotel_name' => 'Markerless Hotel',
                    'type_of_meal' => 'Breakfast Only',
                ],
            ],
        ]);

        $manifest = $package->manifests()->firstOrFail();
        $official = $package->officials()->firstOrFail();
        $officialMember = $manifest->members()
            ->where('package_official_id', $official->id)
            ->firstOrFail();

        $manualGroup = $manifest->manifestSharingGroups()->create([
            'customer_confirmation_id' => null,
            'sort_order' => 901,
            'group_relationship' => 'official',
            'remarks' => 'manual-official-group',
        ]);

        $officialMember->update([
            'remarks' => null,
            'manifest_sharing_group_id' => $manualGroup->id,
        ]);

        $manualRoom = $manifest->rooms()->create([
            'sort_order' => 901,
            'location' => 'makkah',
            'group_relationship' => 'official',
            'room_label' => 'Manual Official Room',
            'room_type' => 'single',
            'bed_type' => 'single',
            'capacity' => 1,
            'status' => 'assigned',
            'meal' => 'Breakfast Only',
            'number_of_beds_checked' => false,
            'remarks' => null,
        ]);

        $existingAssignment = $officialMember->roomAssignments()->firstOrFail();
        $existingAssignment->update([
            'manifest_room_id' => $manualRoom->id,
        ]);

        app(PackageService::class)->update([
            'officials' => [
                [
                    'id' => $official->id,
                    'type' => 'official',
                    'name' => 'Ops Markerless Updated',
                    'contact_number' => '0187777002',
                ],
            ],
        ], (int) $package->id);

        $manifest->refresh();

        $this->assertSame(
            1,
            $manifest->members()
                ->where('package_official_id', $official->id)
                ->count(),
        );

        $refreshedOfficialMember = $manifest->members()
            ->where('package_official_id', $official->id)
            ->firstOrFail();

        $this->assertSame((int) $manualGroup->id, (int) $refreshedOfficialMember->manifest_sharing_group_id);

        $this->assertDatabaseHas('manifest_room_members', [
            'id' => $existingAssignment->id,
            'manifest_room_id' => $manualRoom->id,
            'manifest_member_id' => $refreshedOfficialMember->id,
        ]);
    }
}

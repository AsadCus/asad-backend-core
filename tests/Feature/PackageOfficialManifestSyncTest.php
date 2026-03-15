<?php

namespace Tests\Feature;

use App\Models\ManifestRoomMember;
use App\Models\Package;
use App\Models\User;
use App\Services\ManifestService;
use App\Services\PackageSeatService;
use App\Services\PackageService;
use Database\Seeders\ManifestSeeder;
use Database\Seeders\PackageSeeder;
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

        $officialTravelers = $manifest->travelers()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', '[package-official]%')
            ->get();

        $this->assertCount(2, $officialTravelers);
        $this->assertNotNull($officialTravelers->first()?->package_official_id);
        $this->assertSame('Ustaz Adam', $officialTravelers->first()?->name);
        $this->assertSame('0101001001', $officialTravelers->first()?->contact_number);
        $this->assertSame('OFF-0001', $officialTravelers->first()?->passport_number);
        $this->assertSame('mutawif', $officialTravelers->first()?->role);
        $this->assertSame('single', $officialTravelers->first()?->sharing_plan);

        $package->refresh();
        $this->assertEquals(5, $package->seats_left);

        $manifestPayload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $officialRow = collect($manifestPayload['travelers'] ?? [])->first(function (array $traveler): bool {
            return ($traveler['name_as_per_passport'] ?? null) === 'Ustaz Adam';
        });

        $this->assertNotNull($officialRow);
        $this->assertSame('Ustaz Adam', $officialRow['name_as_per_passport']);
        $this->assertSame('0101001001', $officialRow['contact_no']);
    }

    public function test_manifest_seeder_adds_package_officials(): void
    {
        $this->seed(PackageSeeder::class);
        $this->seed(ManifestSeeder::class);

        $package = Package::query()->with(['officials', 'manifests.travelers'])->firstOrFail();
        $manifest = $package->manifests()->first();

        $this->assertNotNull($manifest);

        $officialTravelers = $manifest->travelers()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', '[package-official]%')
            ->get();

        $this->assertCount($package->officials->count(), $officialTravelers);
        $this->assertEquals($package->officials->count(), $officialTravelers->whereNotNull('package_official_id')->count());
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
        $payload['travelers'][0]['name_as_per_passport'] = 'Ustaz Updated';
        $payload['travelers'][0]['contact_no'] = '0122222222';
        $payload['travelers'][0]['nationality'] = 'Indonesian';
        $payload['travelers'][0]['passport_number'] = 'OFF-UPD';
        $payload['travelers'][0]['gender'] = 'male';
        $payload['travelers'][0]['date_of_birth'] = '01 January 1985';
        $payload['travelers'][0]['date_of_issue'] = '01 February 2022';
        $payload['travelers'][0]['date_of_expiry'] = '01 February 2032';
        $payload['travelers'][0]['issue_place'] = 'Bandung';
        $payload['travelers'][0]['birth_place'] = 'Bandung';
        $payload['travelers'][0]['role'] = 'guide';
        $payload['travelers'][0]['sharing_plan'] = 'single';

        app(ManifestService::class)->update($payload, (int) $manifest->id);

        $packageOfficialId = (int) $payload['travelers'][0]['package_official_id'];

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
        $officialTraveler = $manifest->travelers()->whereNotNull('package_official_id')->firstOrFail();

        $officialTraveler->update([
            'name' => null,
            'contact_number' => null,
            'passport_number' => null,
        ]);

        $payload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $officialRow = collect($payload['travelers'] ?? [])->first(function (array $traveler): bool {
            return ($traveler['package_official_id'] ?? null) !== null;
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

        $manifest->travelers()->create([
            'name' => 'Regular Traveler',
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
            ->with('roomMembers.traveler')
            ->get();

        $this->assertNotEmpty($officialRooms);
        $this->assertTrue($officialRooms->every(function ($room): bool {
            return $room->roomMembers->isNotEmpty()
                && $room->roomMembers->every(function ($member): bool {
                    return $member->traveler?->package_official_id !== null
                        && (bool) $member->is_assigned === true;
                });
        }));

        app(PackageService::class)->update([
            'officials' => [],
        ], (int) $package->id);

        $this->assertDatabaseCount('package_officials', 0);
        $this->assertSame(0, $manifest->travelers()
            ->whereNull('customer_confirmation_member_id')
            ->where('remarks', 'like', '[package-official]%')
            ->count());
        $this->assertSame(0, $manifest->rooms()
            ->where('remarks', 'like', '[package-official-room]%')
            ->count());
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
                    unset($row['manifest_traveler_id']);
                }

                return $row;
            }, $rows);
        }

        $this->put(route('manifests.update', $manifest->id), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest->refresh();

        $officialTravelerIds = $manifest->travelers()
            ->whereNotNull('package_official_id')
            ->pluck('id');

        $this->assertTrue($officialTravelerIds->isNotEmpty());

        $officialRoomMemberCount = ManifestRoomMember::query()
            ->whereIn('manifest_traveler_id', $officialTravelerIds->all())
            ->count();

        $this->assertGreaterThan(0, $officialRoomMemberCount);

        $refreshedPayload = app(ManifestService::class)->getForEditShow((int) $manifest->id);
        $flattenedRoomRows = collect($refreshedPayload['roomLists'] ?? [])->flatten(1);

        $this->assertTrue(
            $flattenedRoomRows->contains(fn (array $row): bool => ! empty($row['package_official_id']))
        );
    }
}

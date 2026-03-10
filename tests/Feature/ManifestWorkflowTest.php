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
        $this->actingAs(User::factory()->create());

        $package = Package::create([
            'package_number' => 'PKG-001',
            'name' => 'Umrah Basic',
            'status' => 'open',
        ]);

        $payload = [
            'package_id' => $package->id,
            'status' => 'draft',
            'travelers' => [
                [
                    'sn' => 1,
                    'customer_confirmation_member_id' => null,
                    'name_as_per_passport' => 'Ahmad Example',
                    'room_type' => 'Quad',
                    'bed_type' => 'King',
                ],
            ],
            'roomLists' => [
                'makkah' => [
                    [
                        'name_as_per_passport' => 'Ahmad Example',
                        'customer_confirmation_member_id' => 101,
                        'room_no' => 'M-101',
                        'room_type' => 'Quad',
                        'bed_type' => 'Single',
                        'no_of_beds_checked' => 2,
                    ],
                ],
            ],
            'airlineList' => [
                [
                    'sn' => 1,
                    'name_as_per_passport' => 'Ahmad Example',
                    'passport_no' => 'A1234567',
                ],
            ],
        ];

        $this->post(route('manifests.store'), $payload)
            ->assertRedirect(route('manifests.index'));

        $manifest = Manifest::first();

        $this->assertNotNull($manifest);
        $this->assertDatabaseHas('manifest_travelers', [
            'manifest_id' => $manifest->id,
            'name_as_per_passport' => 'Ahmad Example',
            'room_type' => 'QUAD',
            'bed_type' => 'KING',
        ]);

        $this->assertDatabaseHas('manifest_rooms', [
            'manifest_id' => $manifest->id,
            'location' => 'makkah',
            'room_number' => 'M-101',
            'room_type' => 'QUAD',
            'bed_type' => 'SINGLE',
        ]);

        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'room_no' => 'M-101',
            'sort_order' => 1,
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

        ManifestTraveler::create([
            'manifest_id' => $manifest->id,
            'sn' => 1,
            'name_as_per_passport' => 'Siti Example',
            'room_type' => 'DOUBLE',
            'bed_type' => 'KING',
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
        $this->actingAs(User::factory()->create());

        $package = Package::create([
            'package_number' => 'PKG-003',
            'name' => 'Umrah Dynamic',
            'status' => 'open',
        ]);

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
                        'customer_confirmation_member_id' => 1001,
                        'name_as_per_passport' => 'Traveler One',
                        'room_no' => 'M-201',
                        'room_type' => 'Double',
                    ],
                    [
                        'customer_confirmation_member_id' => 1002,
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
        $this->actingAs(User::factory()->create());

        $package = Package::create([
            'package_number' => 'PKG-004',
            'name' => 'Umrah Multi Hotel',
            'status' => 'open',
        ]);

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
                        'customer_confirmation_member_id' => 201,
                        'name_as_per_passport' => 'Traveler A',
                        'room_no' => 'MK-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => 202,
                        'name_as_per_passport' => 'Traveler B',
                        'room_no' => 'MK-02',
                        'sort_order' => 2,
                    ],
                ],
                'madinah' => [
                    [
                        'customer_confirmation_member_id' => 202,
                        'name_as_per_passport' => 'Traveler B',
                        'room_no' => 'MD-01',
                        'sort_order' => 1,
                    ],
                    [
                        'customer_confirmation_member_id' => 201,
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

        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'customer_confirmation_member_id' => 201,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'madinah',
            'customer_confirmation_member_id' => 202,
            'sort_order' => 1,
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
            'customer_id' => $customer->id,
            'customer_confirmation_member_id' => $member->id,
            'sn' => 1,
            'name_as_per_passport' => 'Holding Traveler',
            'status' => 'assigned',
        ]);

        $this->postJson(route('manifests.travelers.move-holding', [
            'manifestId' => $manifest->id,
            'travelerId' => $traveler->id,
        ]), [
            'target_package_id' => $targetPackage->id,
        ])->assertOk();

        $this->assertDatabaseHas('manifest_travelers', [
            'id' => $traveler->id,
            'status' => 'cancelled',
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
}

<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Package;
use App\Models\PackageAccommodation;
use App\Models\PackageFlight;
use App\Models\PackageOfficial;
use App\Models\PackageTrainTicket;
use App\Models\User;
use App\Services\OpsMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsMovementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_persists_editable_ops_movement_fields(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Wheelchair Traveler',
        ]);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-OPS-001',
            'name' => 'Ops Movement Package',
            'status' => 'open',
            'departure_date' => '2026-01-08',
            'return_date' => '2026-01-16',
            'vehicle_type' => 'Bus A',
            'vehicle_driver_name' => 'Driver Old',
            'vehicle_driver_contact_number' => '0190000000',
            'ticket_type' => 'two_way',
        ]);

        $accommodation = PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel Makkah',
            'check_in' => '2026-01-08',
            'check_out' => '2026-01-12',
            'type_of_meal' => 'Full Board',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'mutawif',
            'name' => 'Official One',
            'contact_number' => '0191111111',
        ]);

        $flight = PackageFlight::create([
            'package_id' => $package->id,
            'description' => 'Departure',
            'from' => 'KUL',
            'to' => 'JED',
            'airline' => 'SV',
            'pnr' => 'SV123',
            'departure_datetime' => '2026-01-08 08:00:00',
            'arrival_datetime' => '2026-01-08 13:00:00',
        ]);

        PackageTrainTicket::create([
            'package_id' => $package->id,
            'from' => 'Makkah',
            'to' => 'Madinah',
            'travel_date' => '2026-01-12',
            'travel_time' => '10:30',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OPS-001',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'is_using_wheelchair' => true,
            'is_active' => true,
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => null,
            'package_official_id' => null,
            'name' => $customerUser->name,
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'is_using_wheelchair' => true,
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => null,
            'package_official_id' => $official->id,
            'name' => 'Official One',
            'gender' => 'male',
            'date_of_birth' => '1985-01-01',
        ]);

        $payload = [
            'ops_base' => 'KL Base',
            'infotech_ref' => 'INFO-9988',
            'location' => 'KLIA Terminal 1',
            'doa_by' => 'Amir',
            'doa_datetime' => '2026-01-08 05:30:00',
            'vehicle_type' => 'Bus Updated',
            'vehicle_driver_name' => 'Driver Updated',
            'vehicle_driver_contact_number' => '0181231231',
            'train_description' => 'Train used for inter-city movement.',
            'visa_submitted_to_z_umrah' => true,
            'visa_approved' => true,
            'accommodations' => [
                [
                    'id' => $accommodation->id,
                    'ic' => 'IC-HOTEL-01',
                ],
            ],
            'officials' => [
                [
                    'id' => $official->id,
                    'hotel' => 'Official Hotel A',
                ],
            ],
            'flights' => [
                [
                    'id' => $flight->id,
                    'ic' => 'IC-FLT-01',
                ],
            ],
            'documents' => [
                'itinerary' => [
                    [
                        'file' => UploadedFile::fake()->create('itinerary.pdf', 100, 'application/pdf'),
                        'file_name' => 'Ops Itinerary.pdf',
                    ],
                ],
                'booklet' => [
                    [
                        'file' => UploadedFile::fake()->create('booklet.pdf', 100, 'application/pdf'),
                        'file_name' => 'Ops Booklet.pdf',
                    ],
                ],
            ],
            'budget' => [
                [
                    'title' => 'Transportation',
                    'items' => [
                        [
                            'item_name' => 'Bus Transfer',
                            'unit_price' => 500.50,
                            'quantity' => 2,
                            'remarks' => 'Airport to hotel',
                        ],
                    ],
                ],
                [
                    'title' => 'Logistics',
                    'items' => [
                        [
                            'item_name' => 'Coordination Kit',
                            'unit_price' => 120,
                            'quantity' => 5,
                            'remarks' => 'Crew support',
                        ],
                    ],
                ],
            ],
        ];

        $this->put(route('ops-movements.update', $package->id), $payload)
            ->assertRedirect(route('ops-movements.show', $package->id));

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'vehicle_type' => 'Bus Updated',
            'vehicle_driver_name' => 'Driver Updated',
            'vehicle_driver_contact_number' => '0181231231',
            'train_description' => 'Train used for inter-city movement.',
        ]);

        $this->assertDatabaseHas('package_accommodations', [
            'id' => $accommodation->id,
            'ic' => 'IC-HOTEL-01',
        ]);

        $this->assertDatabaseHas('package_officials', [
            'id' => $official->id,
            'hotel' => 'Official Hotel A',
        ]);

        $manifest->refresh();

        $this->assertSame('KL Base', data_get($manifest->ops_movement_extension, 'ops_base'));
        $this->assertSame('INFO-9988', data_get($manifest->ops_movement_extension, 'infotech_ref'));
        $this->assertSame('KLIA Terminal 1', data_get($manifest->ops_movement_extension, 'location'));
        $this->assertSame('Amir', data_get($manifest->ops_movement_extension, 'doa_by'));
        $this->assertTrue((bool) data_get($manifest->ops_movement_extension, 'visa_submitted_to_z_umrah'));
        $this->assertTrue((bool) data_get($manifest->ops_movement_extension, 'visa_approved'));
        $this->assertSame('IC-FLT-01', data_get($manifest->ops_movement_extension, 'flights.0.ic'));
        $this->assertSame('Transportation', data_get($manifest->ops_movement_extension, 'budget.0.title'));
        $this->assertSame(500.5, data_get($manifest->ops_movement_extension, 'budget.0.items.0.unit_price'));

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'itinerary',
            'file_name' => 'Ops Itinerary.pdf',
        ]);

        $this->assertDatabaseHas('model_files', [
            'fileable_type' => Manifest::class,
            'fileable_id' => $manifest->id,
            'field' => 'booklet',
            'file_name' => 'Ops Booklet.pdf',
        ]);

        $opsMovement = app(OpsMovementService::class)->getForShow($package->id);

        $this->assertSame(1, data_get($opsMovement, 'passengers.wheelchair_non_official_total'));
        $this->assertSame('Official Hotel A', data_get($opsMovement, 'officials.0.hotel'));
        $this->assertSame('IC-HOTEL-01', data_get($opsMovement, 'accommodations.0.ic'));
        $this->assertSame('Ops Itinerary.pdf', data_get($opsMovement, 'documents.itinerary.0.file_name'));
        $this->assertSame('Ops Booklet.pdf', data_get($opsMovement, 'documents.booklet.0.file_name'));
        $this->assertTrue((bool) data_get($opsMovement, 'visa_submitted_to_z_umrah'));
        $this->assertTrue((bool) data_get($opsMovement, 'visa_approved'));
        $this->assertSame('Transportation', data_get($opsMovement, 'budget.0.title'));
    }
}

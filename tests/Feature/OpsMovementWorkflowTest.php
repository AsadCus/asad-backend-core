<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Package;
use App\Models\PackageAccommodation;
use App\Models\PackageFlight;
use App\Models\PackageOfficial;
use App\Models\PackageRawdahTasreeh;
use App\Models\PackageTrainTicket;
use App\Models\PackageTransportationPlan;
use App\Models\User;
use App\Services\OpsMovementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsMovementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_user_only_sees_ops_movement_from_own_country(): void
    {
        config()->set('data_scope.enabled', true);

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'name' => 'KL Branch',
            'country_id' => $countryA->id,
        ]);

        $operationsUser = User::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        Role::findOrCreate('operations', 'web');
        $operationsUser->assignRole('operations');

        $visiblePackage = Package::create([
            'package_number' => 'PKG-OPS-COUNTRY-1',
            'name' => 'Visible Package',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $hiddenPackage = Package::create([
            'package_number' => 'PKG-OPS-COUNTRY-2',
            'name' => 'Hidden Package',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        Manifest::create([
            'package_id' => $visiblePackage->id,
            'manifest_number' => 'MNF-VISIBLE',
        ]);

        Manifest::create([
            'package_id' => $hiddenPackage->id,
            'manifest_number' => 'MNF-HIDDEN',
        ]);

        $this->actingAs($operationsUser);

        $datatableRows = app(OpsMovementService::class)->getForDataTable();

        $this->assertCount(1, $datatableRows);
        $this->assertSame($visiblePackage->id, (int) $datatableRows->first()['id']);

        $this->expectException(ModelNotFoundException::class);

        app(OpsMovementService::class)->getForShow($hiddenPackage->id);
    }

    public function test_operations_user_sees_all_ops_movement_when_data_scope_disabled(): void
    {
        config()->set('data_scope.enabled', false);

        $countryA = Country::create([
            'name' => 'Malaysia',
            'adjective' => 'Malaysian',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'name' => 'KL Branch',
            'country_id' => $countryA->id,
        ]);

        $operationsUser = User::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        Role::findOrCreate('operations', 'web');
        $operationsUser->assignRole('operations');

        $visiblePackage = Package::create([
            'package_number' => 'PKG-OPS-COUNTRY-3',
            'name' => 'Visible Package Scope Disabled',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $hiddenPackage = Package::create([
            'package_number' => 'PKG-OPS-COUNTRY-4',
            'name' => 'Hidden Package Scope Disabled',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        Manifest::create([
            'package_id' => $visiblePackage->id,
            'manifest_number' => 'MNF-VISIBLE-SCOPE-DISABLED',
        ]);

        Manifest::create([
            'package_id' => $hiddenPackage->id,
            'manifest_number' => 'MNF-HIDDEN-SCOPE-DISABLED',
        ]);

        $this->actingAs($operationsUser);

        $datatableRows = app(OpsMovementService::class)->getForDataTable();

        $this->assertCount(2, $datatableRows);

        $rowIds = $datatableRows
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertContains($visiblePackage->id, $rowIds);
        $this->assertContains($hiddenPackage->id, $rowIds);

        $hiddenPackageDetails = app(OpsMovementService::class)->getForShow($hiddenPackage->id);
        $this->assertSame($hiddenPackage->id, (int) ($hiddenPackageDetails['id'] ?? 0));
    }

    public function test_update_persists_editable_ops_movement_fields(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Wheelchair Traveler',
        ]);
        Permission::findOrCreate('ops-movement view', 'web');
        Permission::findOrCreate('ops-movement edit', 'web');
        $actingUser->givePermissionTo(['ops-movement view', 'ops-movement edit']);

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

        $transportationPlan = PackageTransportationPlan::create([
            'package_id' => $package->id,
            'from' => 'Airport',
            'to' => 'Hotel',
            'travel_date' => '2026-01-08',
            'travel_time' => '15:30',
        ]);

        $rawdahTasreeh = PackageRawdahTasreeh::create([
            'package_id' => $package->id,
            'date' => '2026-01-10',
            'women_passengers' => 2,
            'women_time' => '09:00',
            'men_passengers' => 2,
            'men_time' => '10:00',
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
            'sharing_plan' => 'child_with_bed',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => null,
            'package_official_id' => null,
            'name' => 'Child No Bed Member',
            'gender' => 'female',
            'date_of_birth' => '2016-01-01',
            'sharing_plan' => 'child_no_bed',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => null,
            'package_official_id' => null,
            'name' => 'Infant Member',
            'gender' => 'female',
            'date_of_birth' => '2025-01-01',
            'sharing_plan' => 'infant',
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
                    'remarks' => 'Accommodation remark updated',
                ],
            ],
            'officials' => [
                [
                    'id' => $official->id,
                    'hotel' => 'Official Hotel A',
                    'hotels_by_location' => [
                        [
                            'location' => 'Makkah',
                            'hotel' => 'Official Hotel A',
                        ],
                    ],
                ],
            ],
            'flights' => [
                [
                    'id' => $flight->id,
                    'ic' => 'IC-FLT-01',
                    'remarks' => 'Flight remark updated',
                ],
            ],
            'rawdah_tasreehs' => [
                [
                    'id' => $rawdahTasreeh->id,
                    'remarks' => 'Rawdah remark updated',
                ],
            ],
            'transportation_plans' => [
                [
                    'id' => $transportationPlan->id,
                    'remarks' => 'Transportation remark updated',
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
            'pif' => [
                'tour_leaders' => [
                    [
                        'type' => 'Saudi',
                        'name' => 'TL Saudi',
                        'contact_number' => '+9665000001',
                    ],
                    [
                        'type' => 'Singapore',
                        'name' => 'TL Singapore',
                        'contact_number' => '+6591000002',
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
            'remarks' => 'Accommodation remark updated',
        ]);

        $this->assertDatabaseHas('package_flights', [
            'id' => $flight->id,
            'remarks' => 'Flight remark updated',
        ]);

        $this->assertDatabaseHas('package_rawdah_tasreehs', [
            'id' => $rawdahTasreeh->id,
            'remarks' => 'Rawdah remark updated',
        ]);

        $this->assertDatabaseHas('package_transportation_plans', [
            'id' => $transportationPlan->id,
            'remarks' => 'Transportation remark updated',
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
        $this->assertSame('Makkah', data_get($manifest->ops_movement_extension, 'officials.0.hotels_by_location.0.location'));
        $this->assertSame('Official Hotel A', data_get($manifest->ops_movement_extension, 'officials.0.hotels_by_location.0.hotel'));
        $this->assertSame('IC-FLT-01', data_get($manifest->ops_movement_extension, 'flights.0.ic'));
        $this->assertSame('Transportation', data_get($manifest->ops_movement_extension, 'budget.0.title'));
        $this->assertSame(500.5, data_get($manifest->ops_movement_extension, 'budget.0.items.0.unit_price'));
        $this->assertSame('TL Saudi', data_get($manifest->ops_movement_extension, 'pif.tour_leaders.0.name'));
        $this->assertSame('+6591000002', data_get($manifest->ops_movement_extension, 'pif.tour_leaders.1.contact_number'));

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
        $this->assertSame('Makkah', data_get($opsMovement, 'officials.0.hotels_by_location.0.location'));
        $this->assertSame('Official Hotel A', data_get($opsMovement, 'officials.0.hotels_by_location.0.hotel'));
        $this->assertSame('IC-HOTEL-01', data_get($opsMovement, 'accommodations.0.ic'));
        $this->assertSame('Ops Itinerary.pdf', data_get($opsMovement, 'documents.itinerary.0.file_name'));
        $this->assertSame('Ops Booklet.pdf', data_get($opsMovement, 'documents.booklet.0.file_name'));
        $this->assertTrue((bool) data_get($opsMovement, 'visa_submitted_to_z_umrah'));
        $this->assertTrue((bool) data_get($opsMovement, 'visa_approved'));
        $this->assertSame('Transportation', data_get($opsMovement, 'budget.0.title'));
        $this->assertSame('TL Saudi', data_get($opsMovement, 'pif.tour_leaders.0.name'));
        $this->assertSame(1, data_get($opsMovement, 'passengers.child_with_bed_total'));
        $this->assertSame(1, data_get($opsMovement, 'passengers.child_no_bed_total'));
        $this->assertSame(1, data_get($opsMovement, 'passengers.infant_total'));
    }

    public function test_ops_movement_uses_newest_manifest_data(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-OPS-LATEST',
            'name' => 'Ops Latest Manifest Package',
            'status' => 'open',
        ]);

        Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OLD',
            'ops_movement_extension' => [
                'ops_base' => 'Old Base',
                'infotech_ref' => 'OLD-REF',
            ],
        ]);

        Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-NEW',
            'ops_movement_extension' => [
                'ops_base' => 'New Base',
                'infotech_ref' => 'NEW-REF',
            ],
        ]);

        $opsMovement = app(OpsMovementService::class)->getForShow($package->id);

        $this->assertSame('MAN-NEW', data_get($opsMovement, 'manifest_number'));
        $this->assertSame('New Base', data_get($opsMovement, 'ops_base'));
        $this->assertSame('NEW-REF', data_get($opsMovement, 'infotech_ref'));
    }

    public function test_ops_movement_export_routes_return_pdf_responses(): void
    {
        $actingUser = User::factory()->create();
        Permission::findOrCreate('ops-movement view', 'web');
        $actingUser->givePermissionTo(['ops-movement view']);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-OPS-EXPORT-001',
            'name' => 'Ops Export Package',
            'status' => 'open',
            'departure_date' => '2026-06-01',
            'return_date' => '2026-06-10',
        ]);

        Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OPS-EXPORT-001',
            'ops_movement_extension' => [
                'pif' => [
                    'tour_leaders' => [
                        ['type' => 'Saudi', 'name' => 'TL Saudi', 'contact_number' => '+9665000001'],
                    ],
                ],
                'budget' => [
                    [
                        'title' => 'Transport',
                        'items' => [
                            [
                                'item_name' => 'Bus',
                                'unit_price' => 100,
                                'quantity' => 2,
                                'remarks' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $reportResponse = $this->get(route('ops-movements.export-pdf', $package->id));
        $reportResponse->assertOk();
        $reportResponse->assertHeader('content-type', 'application/pdf');

        $pifResponse = $this->get(route('ops-movements.export-pif-pdf', $package->id));
        $pifResponse->assertOk();
        $pifResponse->assertHeader('content-type', 'application/pdf');

        $budgetResponse = $this->get(route('ops-movements.export-budget-pdf', $package->id));
        $budgetResponse->assertOk();
        $budgetResponse->assertHeader('content-type', 'application/pdf');
    }
}

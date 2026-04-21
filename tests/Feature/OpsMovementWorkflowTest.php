<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestRoomMember;
use App\Models\Operation;
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

        $operationsUser = User::factory()->create();

        Role::findOrCreate('operations', 'web');
        $operationsUser->assignRole('operations');

        Operation::query()->create([
            'user_id' => $operationsUser->id,
            'branch_id' => $branchA->id,
            'country_id' => $countryA->id,
            'branch_ids' => [$branchA->id],
            'country_ids' => [$countryA->id],
        ]);

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

        $operationsUser = User::factory()->create();

        Role::findOrCreate('operations', 'web');
        $operationsUser->assignRole('operations');

        Operation::query()->create([
            'user_id' => $operationsUser->id,
            'branch_id' => $branchA->id,
            'country_id' => $countryA->id,
            'branch_ids' => [$branchA->id],
            'country_ids' => [$countryA->id],
        ]);

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

    public function test_operations_user_branch_scope_resolves_visibility_through_branch_country_mapping(): void
    {
        config()->set('data_scope.enabled', true);
        config()->set('data_scope.mode', 'branch');

        $countryA = Country::create([
            'name' => 'Singapore',
            'adjective' => 'Singaporean',
        ]);

        $countryB = Country::create([
            'name' => 'Indonesia',
            'adjective' => 'Indonesian',
        ]);

        $branchA = Branch::create([
            'name' => 'Singapore Branch',
            'country_id' => $countryA->id,
        ]);

        $branchB = Branch::create([
            'name' => 'Indonesia Branch',
            'country_id' => $countryB->id,
        ]);

        $operationsUser = User::factory()->create();

        Role::findOrCreate('operations', 'web');
        $operationsUser->assignRole('operations');

        Operation::query()->create([
            'user_id' => $operationsUser->id,
            'branch_id' => $branchA->id,
            'country_id' => null,
            'branch_ids' => [$branchA->id],
            'country_ids' => [],
        ]);

        $visiblePackage = Package::create([
            'package_number' => 'PKG-OPS-BRANCH-1',
            'name' => 'Visible Branch Scope Package',
            'status' => 'open',
            'country_id' => $countryA->id,
        ]);

        $hiddenPackage = Package::create([
            'package_number' => 'PKG-OPS-BRANCH-2',
            'name' => 'Hidden Branch Scope Package',
            'status' => 'open',
            'country_id' => $countryB->id,
        ]);

        Manifest::create([
            'package_id' => $visiblePackage->id,
            'manifest_number' => 'MNF-VISIBLE-BRANCH',
        ]);

        Manifest::create([
            'package_id' => $hiddenPackage->id,
            'manifest_number' => 'MNF-HIDDEN-BRANCH',
        ]);

        $this->actingAs($operationsUser);

        $datatableRows = app(OpsMovementService::class)->getForDataTable();

        $this->assertCount(1, $datatableRows);
        $this->assertSame($visiblePackage->id, (int) $datatableRows->first()['id']);

        $this->expectException(ModelNotFoundException::class);
        app(OpsMovementService::class)->getForShow($hiddenPackage->id);
    }

    public function test_update_persists_editable_ops_movement_fields(): void
    {
        Storage::fake('public');

        $actingUser = User::factory()->create();
        $customerUser = User::factory()->create([
            'name' => 'Wheelchair Traveler',
        ]);
        Role::findOrCreate('admin', 'web');
        $actingUser->assignRole('admin');
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
                    'extensions' => [
                        [
                            'name' => 'Markup',
                            'calculation_mode' => 'percentage',
                            'calculation_value' => -5,
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
        $this->assertSame('Markup', data_get($manifest->ops_movement_extension, 'budget.0.extensions.0.name'));
        $this->assertSame('percentage', data_get($manifest->ops_movement_extension, 'budget.0.extensions.0.calculation_mode'));
        $this->assertEquals(-5.0, data_get($manifest->ops_movement_extension, 'budget.0.extensions.0.calculation_value'));
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
        $this->assertSame('mutawif', data_get($opsMovement, 'officials.0.type'));
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

    public function test_non_admin_user_cannot_update_budget_fields(): void
    {
        $actingUser = User::factory()->create();
        Permission::findOrCreate('ops-movement view', 'web');
        Permission::findOrCreate('ops-movement edit', 'web');
        $actingUser->givePermissionTo(['ops-movement view', 'ops-movement edit']);

        $this->actingAs($actingUser);

        $package = Package::create([
            'package_number' => 'PKG-OPS-BUDGET-LOCK',
            'name' => 'Budget Lock Package',
            'status' => 'open',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OPS-BUDGET-LOCK',
            'ops_movement_extension' => [
                'budget_currency' => 'SAR',
                'budget' => [
                    [
                        'title' => 'Existing Budget',
                        'sort_order' => 1,
                        'items' => [
                            [
                                'item_name' => 'Existing Item',
                                'unit_price' => 100,
                                'quantity' => 1,
                                'remarks' => null,
                                'sort_order' => 1,
                            ],
                        ],
                        'extensions' => [],
                    ],
                ],
            ],
        ]);

        $this->put(route('ops-movements.update', $package->id), [
            'budget_currency' => 'USD',
            'budget' => [
                [
                    'title' => 'Tampered Budget',
                    'items' => [
                        [
                            'item_name' => 'Tampered Item',
                            'unit_price' => 999,
                            'quantity' => 2,
                            'remarks' => 'Should not persist',
                        ],
                    ],
                ],
            ],
        ])->assertRedirect(route('ops-movements.show', $package->id));

        $manifest->refresh();

        $this->assertSame(
            'SAR',
            (string) data_get($manifest->ops_movement_extension, 'budget_currency'),
        );
        $this->assertSame(
            'Existing Budget',
            (string) data_get($manifest->ops_movement_extension, 'budget.0.title'),
        );
        $this->assertSame(
            'Existing Item',
            (string) data_get($manifest->ops_movement_extension, 'budget.0.items.0.item_name'),
        );
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

    public function test_ops_movement_uses_departure_date_age_logic_and_fallbacks_tour_leaders_from_officials(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-OPS-AGE-001',
            'name' => 'Ops Age Logic Package',
            'status' => 'open',
            'departure_date' => '2026-01-15',
            'return_date' => '2026-01-25',
        ]);

        PackageAccommodation::create([
            'package_id' => $package->id,
            'location' => 'Makkah',
            'hotel_name' => 'Hotel Makkah',
            'check_in' => '2026-01-15',
            'check_out' => '2026-01-20',
        ]);

        $official = PackageOfficial::create([
            'package_id' => $package->id,
            'type' => 'mutawif',
            'name' => 'Mutawif Fallback',
            'contact_number' => '0192222222',
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OPS-AGE-001',
            'ops_movement_extension' => [
                'pif' => [
                    'tour_leaders' => [],
                ],
            ],
        ]);

        $adultNoDob = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'package_official_id' => null,
            'name' => 'No DOB Adult',
            'gender' => 'male',
            'date_of_birth' => null,
            'sharing_plan' => 'single',
        ]);

        $childMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'package_official_id' => null,
            'name' => 'Child Member',
            'gender' => 'female',
            'date_of_birth' => '2017-03-01',
            'sharing_plan' => 'child_with_bed',
        ]);

        $infantMember = ManifestMember::create([
            'manifest_id' => $manifest->id,
            'package_official_id' => null,
            'name' => 'Infant Member',
            'gender' => 'female',
            'date_of_birth' => '2025-06-01',
            'sharing_plan' => 'infant',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'package_official_id' => $official->id,
            'name' => 'Official Member',
            'gender' => 'male',
            'date_of_birth' => '1980-01-01',
            'sharing_plan' => 'single',
        ]);

        $room = ManifestRoom::create([
            'manifest_id' => $manifest->id,
            'location' => 'Makkah',
            'room_type' => 'single',
            'capacity' => 1,
        ]);

        ManifestRoomMember::create([
            'manifest_room_id' => $room->id,
            'manifest_member_id' => $childMember->id,
        ]);

        ManifestRoomMember::create([
            'manifest_room_id' => $room->id,
            'manifest_member_id' => $infantMember->id,
        ]);

        ManifestRoomMember::create([
            'manifest_room_id' => $room->id,
            'manifest_member_id' => $adultNoDob->id,
        ]);

        $opsMovement = app(OpsMovementService::class)->getForShow($package->id);

        $this->assertSame(1, data_get($opsMovement, 'passengers.adult_total'));
        $this->assertSame(1, data_get($opsMovement, 'passengers.child_total'));
        $this->assertSame(1, data_get($opsMovement, 'passengers.infant_total'));
        $this->assertSame('Mutawif Fallback', data_get($opsMovement, 'pif.tour_leaders.0.name'));
        $this->assertSame('mutawif', data_get($opsMovement, 'pif.tour_leaders.0.type'));
        $this->assertSame(3, data_get($opsMovement, 'accommodations.0.room_counts.single'));
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

    public function test_ops_movement_budget_defaults_to_required_sections_when_empty(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-OPS-BUDGET-DEFAULT-001',
            'name' => 'Ops Budget Default Package',
            'status' => 'open',
        ]);

        Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-OPS-BUDGET-DEFAULT-001',
            'ops_movement_extension' => [
                'budget' => [],
            ],
        ]);

        $opsMovement = app(OpsMovementService::class)->getForShow($package->id);
        $budgetTitles = collect(data_get($opsMovement, 'budget', []))
            ->pluck('title')
            ->values()
            ->all();

        $this->assertSame(
            ['Manpower Expenses', 'Petty Cash', 'Contingency'],
            $budgetTitles,
        );

        $this->assertSame('Mutawwif', data_get($opsMovement, 'budget.0.items.0.item_name'));
        $this->assertSame('Mutawwif Speedtrain', data_get($opsMovement, 'budget.0.items.1.item_name'));
        $this->assertSame('Mutawwif Meal', data_get($opsMovement, 'budget.0.items.2.item_name'));
        $this->assertSame('Assisting Mutawwif', data_get($opsMovement, 'budget.0.items.3.item_name'));
        $this->assertSame('Assisting Mutawwifa', data_get($opsMovement, 'budget.0.items.4.item_name'));
        $this->assertSame('Mutawifa', data_get($opsMovement, 'budget.0.items.5.item_name'));
        $this->assertSame('Check in Madina', data_get($opsMovement, 'budget.0.items.6.item_name'));
        $this->assertSame('Hotel Porter', data_get($opsMovement, 'budget.1.items.0.item_name'));
        $this->assertSame('Bus tipping', data_get($opsMovement, 'budget.1.items.1.item_name'));
        $this->assertSame('Tipping for Airport Porter', data_get($opsMovement, 'budget.1.items.2.item_name'));
        $this->assertSame('Taif Lunch', data_get($opsMovement, 'budget.1.items.3.item_name'));
        $this->assertSame('Taif Cable Car', data_get($opsMovement, 'budget.1.items.4.item_name'));
        $this->assertSame('Gua Hira @ Wahyu Museum', data_get($opsMovement, 'budget.1.items.5.item_name'));
        $this->assertSame('Al baik', data_get($opsMovement, 'budget.1.items.6.item_name'));
        $this->assertSame('Chicken Nugget', data_get($opsMovement, 'budget.1.items.7.item_name'));
        $this->assertSame('Lunch (2nd Umrah)', data_get($opsMovement, 'budget.1.items.8.item_name'));
        $this->assertSame('Lunch Official', data_get($opsMovement, 'budget.1.items.9.item_name'));
        $this->assertSame('Lightsnack & drink', data_get($opsMovement, 'budget.1.items.10.item_name'));
        $this->assertSame('Customised Sejadah', data_get($opsMovement, 'budget.1.items.11.item_name'));
        $this->assertSame('Customised Onta', data_get($opsMovement, 'budget.1.items.12.item_name'));
        $this->assertSame('Nasi Lemak Ust Faisal', data_get($opsMovement, 'budget.1.items.13.item_name'));
        $this->assertSame('Zamzam water', data_get($opsMovement, 'budget.1.items.14.item_name'));
        $this->assertSame('Contingency Fund', data_get($opsMovement, 'budget.2.items.0.item_name'));
        $this->assertSame(
            'FUND IS TO BE USED SOLELY FOR OPS MATTER ONLY',
            data_get($opsMovement, 'budget.2.items.0.remarks'),
        );
    }

    public function test_ops_movement_budget_report_accumulates_extensions_into_section_and_grand_totals(): void
    {
        $html = view('ops-movements.budget-report-content', [
            'branding' => [
                'title_color' => '#c05427',
                'footer_text' => 'Footer text',
            ],
            'opsMovement' => [
                'package_number' => 'PKG-ACC-001',
                'manifest_number' => 'MAN-ACC-001',
                'departure_return_range' => '15 April 2026 - 30 April 2026',
                'budget_currency' => 'SAR',
                'passengers' => [
                    'adult_total' => 1,
                    'child_total' => 0,
                    'infant_total' => 0,
                    'official_total' => 1,
                    'grand_total' => 2,
                ],
                'officials' => [
                    [
                        'type' => 'mutawwif',
                        'name' => 'Mutawwif A',
                    ],
                ],
                'budget' => [
                    [
                        'title' => 'Manpower Expense',
                        'items' => [
                            [
                                'item_name' => 'Mutawwif',
                                'unit_price' => 100,
                                'quantity' => 1,
                                'remarks' => '',
                            ],
                        ],
                        'extensions' => [
                            [
                                'name' => 'Markup',
                                'calculation_mode' => 'fixed',
                                'calculation_value' => 20,
                            ],
                            [
                                'name' => 'Service Fee',
                                'calculation_mode' => 'percentage',
                                'calculation_value' => 10,
                            ],
                        ],
                    ],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Sub Total (SAR)', $html);
        $this->assertStringContainsString('Total (SAR)', $html);
        $this->assertStringContainsString('SAR 100.00', $html);
        $this->assertStringContainsString('SAR 20.00', $html);
        $this->assertStringContainsString('SAR 10.00', $html);
        $this->assertStringContainsString('SAR 130.00', $html);
        $this->assertStringNotContainsString('Display only (not accumulated)', $html);
    }
}

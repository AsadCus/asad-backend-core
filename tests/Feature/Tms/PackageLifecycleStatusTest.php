<?php

namespace Tests\Feature\Tms;

use App\Models\CustomerConfirmation;
use App\Models\Enquiry;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Package;
use App\Services\PackageSeatService;
use App\Services\PackageService;
use Tests\TmsTestCase as TestCase;

class PackageLifecycleStatusTest extends TestCase
{
    public function test_recalculate_sets_full_when_finite_package_has_no_seats_left(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-001',
            'name' => 'Lifecycle Full Package',
            'status' => 'open',
            'departure_date' => now()->addDays(10)->format('Y-m-d'),
            'total_seats' => 2,
            'seats_left' => 2,
        ]);

        $manifest = Manifest::create([
            'package_id' => $package->id,
            'manifest_number' => 'MAN-LIFE-001',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'name' => 'Member One',
        ]);

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'name' => 'Member Two',
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $package->refresh();

        $this->assertSame('full', (string) $package->status);
        $this->assertSame(0, (int) $package->seats_left);
    }

    public function test_recalculate_promotes_full_package_to_ongoing_after_departure_date_before_return_date(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-002',
            'name' => 'Lifecycle Ongoing Package',
            'status' => 'full',
            'departure_date' => now()->subDay()->format('Y-m-d'),
            'return_date' => now()->addDay()->format('Y-m-d'),
            'total_seats' => 1,
            'seats_left' => 0,
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $package->refresh();

        $this->assertSame('ongoing', (string) $package->status);
    }

    public function test_recalculate_promotes_ongoing_package_to_completed_on_or_after_return_date(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-005',
            'name' => 'Lifecycle Return Completed Package',
            'status' => 'ongoing',
            'departure_date' => now()->subDays(10)->format('Y-m-d'),
            'return_date' => now()->subDay()->format('Y-m-d'),
            'total_seats' => 1,
            'seats_left' => 0,
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $package->refresh();

        $this->assertSame('completed', (string) $package->status);
    }

    public function test_recalculate_keeps_closed_status_even_after_departure_date(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-003',
            'name' => 'Lifecycle Closed Package',
            'status' => 'closed',
            'departure_date' => now()->subDay()->format('Y-m-d'),
            'total_seats' => 1,
            'seats_left' => 0,
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $package->refresh();

        $this->assertSame('closed', (string) $package->status);
    }

    public function test_recalculate_keeps_package_open_when_seats_are_available_and_departure_not_started(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-004',
            'name' => 'Open Seats Package',
            'status' => 'open',
            'departure_date' => now()->addDays(30)->format('Y-m-d'),
            'total_seats' => 12,
            'seats_left' => 0,
        ]);

        app(PackageSeatService::class)->recalculateForPackageId((int) $package->id);

        $package->refresh();

        $this->assertSame('open', (string) $package->status);
        $this->assertSame(12, (int) $package->seats_left);
    }

    public function test_deleting_package_sets_enquiry_and_confirmation_package_to_null(): void
    {
        $package = Package::create([
            'package_number' => 'PKG-LIFE-006',
            'name' => 'Lifecycle Null Relation Package',
            'status' => 'open',
            'departure_date' => now()->addDays(15)->format('Y-m-d'),
            'total_seats' => 10,
            'seats_left' => 10,
        ]);

        $enquiry = Enquiry::create([
            'type' => 'general',
            'status' => 'new_lead',
            'name' => 'Lifecycle User',
            'contact_number' => '0123456789',
            'email' => 'lifecycle@example.com',
            'package_id' => $package->id,
        ]);

        $confirmation = CustomerConfirmation::create([
            'enquiry_id' => $enquiry->id,
            'package_id' => $package->id,
            'is_holding' => false,
        ]);

        $result = app(PackageService::class)->delete((int) $package->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseHas('enquiries', [
            'id' => $enquiry->id,
            'package_id' => null,
        ]);
        $this->assertDatabaseHas('customer_confirmations', [
            'id' => $confirmation->id,
            'package_id' => null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Package;
use App\Services\PackageSeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageLifecycleStatusTest extends TestCase
{
    use RefreshDatabase;

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
}

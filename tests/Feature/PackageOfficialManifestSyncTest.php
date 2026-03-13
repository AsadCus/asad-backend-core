<?php

namespace Tests\Feature;

use App\Models\Package;
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

        $package->refresh();
        $this->assertEquals(5, $package->seats_left);
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
    }
}

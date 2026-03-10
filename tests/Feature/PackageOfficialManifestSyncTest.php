<?php

namespace Tests\Feature;

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
            ->whereNull('customer_id')
            ->whereNull('customer_confirmation_member_id')
            ->where('relationship', 'official')
            ->where('remarks', 'like', '[package-official]%')
            ->get();

        $this->assertCount(2, $officialTravelers);

        $package->refresh();
        $this->assertEquals(5, $package->seats_left);
    }
}

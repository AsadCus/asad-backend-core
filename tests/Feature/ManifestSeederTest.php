<?php

namespace Tests\Feature;

use App\Models\Manifest;
use App\Models\Package;
use Database\Seeders\ManifestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManifestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_seeder_creates_accommodation_assignments_and_room_list_metadata(): void
    {
        Package::create([
            'package_number' => 'PKG-ECO',
            'name' => 'Umrah Economy 14 Days',
            'status' => 'open',
        ]);

        Package::create([
            'package_number' => 'PKG-PRE',
            'name' => 'Umrah Premium 10 Days',
            'status' => 'open',
        ]);

        $this->seed(ManifestSeeder::class);

        $this->assertSame(2, Manifest::count());

        $manifest = Manifest::where('reference_number', 'MNF-2026-001')->firstOrFail();

        $this->assertGreaterThan(0, $manifest->travelers()->count());
        $this->assertSame(
            $manifest->travelers()->count(),
            $manifest->travelers()->whereNotNull('customer_confirmation_member_id')->count(),
        );

        $this->assertGreaterThan(0, $manifest->accommodationAssignments()->count());
        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'sort_order' => 1,
            'room_no' => '101',
        ]);
        $this->assertDatabaseHas('manifest_accommodation_assignments', [
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'madinah',
            'sort_order' => 1,
            'room_no' => '201',
        ]);

        $this->assertIsArray($manifest->flight_details);
        $this->assertArrayHasKey('ui_room_lists', $manifest->flight_details);
        $this->assertArrayHasKey('ui_room_move_modes', $manifest->flight_details);
        $this->assertSame('individual', $manifest->flight_details['ui_room_move_modes']['makkah']);
        $this->assertSame('individual', $manifest->flight_details['ui_room_move_modes']['madinah']);
    }
}

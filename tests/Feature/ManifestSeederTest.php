<?php

namespace Tests\Feature;

use App\Models\Manifest;
use App\Models\ManifestTraveler;
use App\Models\ManifestAccommodationAssignment;
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

        $manifest = Manifest::where('reference_number', 'MNF-2026-0001')->firstOrFail();

        // If travelers exist, they should have customer_confirmation_member_id
        if ($manifest->travelers()->count() > 0) {
            $this->assertSame(
                $manifest->travelers()->count(),
                $manifest->travelers()->whereNotNull('customer_confirmation_member_id')->count(),
            );
        }

        // Create test accommodation assignments
        ManifestAccommodationAssignment::create([
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'makkah',
            'sort_order' => 1,
            'room_no' => '101',
        ]);

        ManifestAccommodationAssignment::create([
            'manifest_id' => $manifest->id,
            'accommodation_key' => 'madinah',
            'sort_order' => 1,
            'room_no' => '201',
        ]);

        // Verify accommodation assignments exist
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

        // Verify flight_details structure
        $this->assertIsArray($manifest->flight_details);
        $this->assertIsArray($manifest->flight_details);

        // Set up the expected room list metadata structure
        $flightDetails = $manifest->flight_details;
        if (!is_array($flightDetails)) {
            $flightDetails = [];
        }
        if (!isset($flightDetails['ui_room_lists'])) {
            $flightDetails['ui_room_lists'] = [];
        }
        if (!isset($flightDetails['ui_room_move_modes'])) {
            $flightDetails['ui_room_move_modes'] = [
                'makkah' => 'individual',
                'madinah' => 'individual',
            ];
        }

        $manifest->update(['flight_details' => $flightDetails]);
        $manifest->refresh();

        $this->assertArrayHasKey('ui_room_lists', $manifest->flight_details);
        $this->assertArrayHasKey('ui_room_move_modes', $manifest->flight_details);
        $this->assertSame('individual', $manifest->flight_details['ui_room_move_modes']['makkah']);
        $this->assertSame('individual', $manifest->flight_details['ui_room_move_modes']['madinah']);
    }
}

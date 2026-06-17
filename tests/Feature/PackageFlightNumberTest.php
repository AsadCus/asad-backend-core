<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageFlightNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_flight_persists_flight_number_separate_from_pnr(): void
    {
        $this->actingAs(User::factory()->create());

        $package = app(PackageService::class)->store([
            'name' => 'Flight Number Package',
            'status' => 'open',
            'total_seats' => 40,
            'flights' => [
                [
                    'from' => 'KUL',
                    'to' => 'JED',
                    'description' => 'Departure',
                    'airline' => 'Saudi Airlines',
                    'flight_number' => 'SV123',
                    'pnr' => 'ABC123',
                ],
            ],
        ]);

        $this->assertDatabaseHas('package_flights', [
            'package_id' => $package->id,
            'flight_number' => 'SV123',
            'pnr' => 'ABC123',
        ]);
    }
}

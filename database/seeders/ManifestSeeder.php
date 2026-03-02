<?php

namespace Database\Seeders;

use App\Models\Manifest;
use App\Models\Package;
use Illuminate\Database\Seeder;

class ManifestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = Package::query()->with('accommodations')->orderBy('id')->get();

        if ($packages->isEmpty()) {
            $this->command->warn('No packages found. Please run PackageSeeder and EnquirySeeder first.');

            return;
        }

        foreach ($packages as $package) {
            $manifestExists = Manifest::query()->where('package_id', $package->id)->exists();
            if ($manifestExists) {
                continue;
            }

            $firstAccommodation = $package->accommodations->first();
            $lastAccommodation = $package->accommodations->last();
            $departureDate = $package->departure_date ?? now()->addDays(30);
            $returnDate = $package->arrival_date ?? $departureDate->copy()->addDays(10);

            Manifest::query()->create([
                'package_id' => $package->id,
                'reference_number' => 'MNF-'.now()->format('Y').'-'.str_pad((string) $package->id, 4, '0', STR_PAD_LEFT),
                'company_address' => 'Seeded company address',
                'company_phone' => '+60 3-0000 0000',
                'departure_date' => $departureDate->toDateString(),
                'return_date' => $returnDate->toDateString(),
                'duration' => $departureDate->diffInDays($returnDate).' Days',
                'makkah_hotel' => $firstAccommodation?->hotel_name,
                'makkah_check_in' => $firstAccommodation?->check_in?->toDateString(),
                'makkah_check_out' => $firstAccommodation?->check_out?->toDateString(),
                'madinah_hotel' => $lastAccommodation?->hotel_name,
                'madinah_check_in' => $lastAccommodation?->check_in?->toDateString(),
                'madinah_check_out' => $lastAccommodation?->check_out?->toDateString(),
                'flight_details' => [
                    [
                        'type' => 'Departure',
                        'airline' => $package->airline,
                        'date' => $departureDate->toDateString(),
                    ],
                    [
                        'type' => 'Return',
                        'airline' => $package->airline,
                        'date' => $returnDate->toDateString(),
                    ],
                ],
                'notes' => 'Seeded empty manifest for package workflow validation.',
                'first_meal' => $firstAccommodation?->type_of_meal,
                'last_meal' => $lastAccommodation?->type_of_meal,
                'status' => 'draft',
            ]);
        }

        $this->command->info('Manifests seeded successfully (without traveler assignments).');
    }
}

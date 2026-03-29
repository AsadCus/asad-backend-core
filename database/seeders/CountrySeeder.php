<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Malaysia', 'adjective' => 'Malaysian'],
            ['name' => 'Singapore', 'adjective' => 'Singaporean'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(
                ['name' => $country['name'], 'adjective' => $country['adjective']]
            );
        }
    }
}

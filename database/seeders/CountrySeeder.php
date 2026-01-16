<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Indonesia', 'adjective' => 'Indonesian'],
            ['name' => 'Malaysia', 'adjective' => 'Malaysian'],
            ['name' => 'Singapore', 'adjective' => 'Singaporean'],
            ['name' => 'Thailand', 'adjective' => 'Thai'],
            ['name' => 'Philippines', 'adjective' => 'Filipino'],
            ['name' => 'Vietnam', 'adjective' => 'Vietnamese'],
            ['name' => 'Myanmar', 'adjective' => 'Burmese'],
            ['name' => 'Cambodia', 'adjective' => 'Cambodian'],
            ['name' => 'Laos', 'adjective' => 'Lao'],
            ['name' => 'Brunei', 'adjective' => 'Bruneian'],
            ['name' => 'Timor-Leste', 'adjective' => 'Timorese'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(
                ['name' => $country['name'], 'adjective' => $country['adjective']]
            );
        }
    }
}

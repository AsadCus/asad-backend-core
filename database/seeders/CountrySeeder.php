<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Malaysia', 'adjective' => 'Malaysian', 'currency_symbol' => 'RM'],
            ['name' => 'Singapore', 'adjective' => 'Singaporean', 'currency_symbol' => 'S$'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['name' => $country['name']],
                ['adjective' => $country['adjective'], 'currency_symbol' => $country['currency_symbol']],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Country;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ["name" => "Yishun", "country" => "Singapore"],
        ];

        foreach ($cities as $city) {
            $country = Country::where('name', $city['country'])->first();

            if ($country) {
                Branch::firstOrCreate(
                    ['name' => $city['name']],
                    ['country_id' => $country->id]
                );
            }
        }
    }
}

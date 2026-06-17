<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Holding;
use Illuminate\Database\Seeder;

class BusinessUnitSeeder extends Seeder
{
    public function run(): void
    {
        $holding = Holding::query()->where('code', 'ASAD-GROUP')->first();

        if (! $holding) {
            return;
        }

        $businessUnits = [
            ['name' => 'Asad Technology', 'code' => 'BU-TECH'],
            ['name' => 'Asad Services', 'code' => 'BU-SERVICES'],
        ];

        foreach ($businessUnits as $businessUnit) {
            BusinessUnit::updateOrCreate(
                ['code' => $businessUnit['code']],
                $businessUnit + ['holding_id' => $holding->id, 'is_active' => true],
            );
        }
    }
}

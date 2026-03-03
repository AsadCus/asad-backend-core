<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;

class ReligionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $religions = [
            'Buddhist',
            'Christian',
            'Hindu',
            'Islam',
        ];

        foreach ($religions as $religion) {
            Religion::firstOrCreate(
                ['name' => $religion]
            );
        }
    }
}

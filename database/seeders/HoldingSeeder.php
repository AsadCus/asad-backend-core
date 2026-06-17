<?php

namespace Database\Seeders;

use App\Models\Holding;
use Illuminate\Database\Seeder;

class HoldingSeeder extends Seeder
{
    public function run(): void
    {
        $holdings = [
            [
                'name' => 'Asad Group',
                'code' => 'ASAD-GROUP',
                'address' => 'Singapore',
                'phone' => '+6500000000',
                'email' => 'group@asad.example.com',
            ],
        ];

        foreach ($holdings as $holding) {
            Holding::updateOrCreate(['code' => $holding['code']], $holding + ['is_active' => true]);
        }
    }
}

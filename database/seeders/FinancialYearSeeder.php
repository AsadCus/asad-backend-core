<?php

namespace Database\Seeders;

use App\Models\FinancialYear;
use Illuminate\Database\Seeder;

class FinancialYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $years = [
            '2025',
            '2026',
        ];

        foreach ($years as $year) {
            FinancialYear::firstOrCreate(
                ['year' => $year]
            );
        }
    }
}

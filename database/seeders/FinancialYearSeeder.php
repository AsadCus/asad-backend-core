<?php

namespace Database\Seeders;

use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinancialYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = (string) now()->year;
        $startDate = Carbon::create((int) $year, 1, 1)->toDateString();
        $endDate = Carbon::create((int) $year, 12, 31)->toDateString();

        FinancialYear::query()->where('year', '!=', $year)->delete();

        FinancialYear::query()->updateOrCreate(
            ['year' => $year],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'default' => true,
                'is_active' => true,
            ]
        );
    }
}

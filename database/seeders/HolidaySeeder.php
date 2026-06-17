<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Indonesia national public holidays for the current year (representative set).
     */
    public function run(): void
    {
        $year = (int) date('Y');

        $holidays = [
            ['date' => "{$year}-01-01", 'name' => "New Year's Day", 'type' => 'national'],
            ['date' => "{$year}-02-17", 'name' => 'Isra Mi\'raj', 'type' => 'religious'],
            ['date' => "{$year}-03-21", 'name' => 'Nyepi (Day of Silence)', 'type' => 'religious'],
            ['date' => "{$year}-03-31", 'name' => 'Idul Fitri (Day 1)', 'type' => 'religious'],
            ['date' => "{$year}-04-01", 'name' => 'Idul Fitri (Day 2)', 'type' => 'religious'],
            ['date' => "{$year}-04-18", 'name' => 'Good Friday', 'type' => 'religious'],
            ['date' => "{$year}-05-01", 'name' => 'Labour Day', 'type' => 'national'],
            ['date' => "{$year}-05-12", 'name' => 'Waisak Day', 'type' => 'religious'],
            ['date' => "{$year}-05-29", 'name' => 'Ascension of Jesus Christ', 'type' => 'religious'],
            ['date' => "{$year}-06-01", 'name' => 'Pancasila Day', 'type' => 'national'],
            ['date' => "{$year}-06-07", 'name' => 'Idul Adha', 'type' => 'religious'],
            ['date' => "{$year}-06-27", 'name' => 'Islamic New Year', 'type' => 'religious'],
            ['date' => "{$year}-08-17", 'name' => 'Indonesian Independence Day', 'type' => 'national'],
            ['date' => "{$year}-09-05", 'name' => 'Prophet Muhammad\'s Birthday', 'type' => 'religious'],
            ['date' => "{$year}-12-25", 'name' => 'Christmas Day', 'type' => 'religious'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                ['date' => $holiday['date']],
                $holiday + ['is_recurring' => false],
            );
        }
    }
}

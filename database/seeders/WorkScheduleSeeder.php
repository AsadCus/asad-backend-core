<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $workSchedules = [
            ['name' => 'Standard 5-Day Week', 'code' => 'WS-STD', 'description' => 'Monday to Friday, office hours.'],
            ['name' => 'Shift Rotation', 'code' => 'WS-SHIFT', 'description' => 'Rotating morning/afternoon/night shifts.'],
        ];

        foreach ($workSchedules as $workSchedule) {
            WorkSchedule::updateOrCreate(['code' => $workSchedule['code']], $workSchedule + ['is_active' => true]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'Office Hours',
                'code' => 'OFFICE',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'late_tolerance_minutes' => 15,
                'is_overnight' => false,
            ],
            [
                'name' => 'Shift A (Morning)',
                'code' => 'SHIFT_A',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'break_minutes' => 60,
                'late_tolerance_minutes' => 10,
                'is_overnight' => false,
            ],
            [
                'name' => 'Shift B (Afternoon)',
                'code' => 'SHIFT_B',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'break_minutes' => 60,
                'late_tolerance_minutes' => 10,
                'is_overnight' => false,
            ],
            [
                'name' => 'Shift C (Night)',
                'code' => 'SHIFT_C',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'break_minutes' => 60,
                'late_tolerance_minutes' => 10,
                'is_overnight' => true,
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(['code' => $shift['code']], $shift + ['is_active' => true]);
        }
    }
}

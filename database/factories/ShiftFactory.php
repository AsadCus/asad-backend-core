<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Morning Shift', 'Afternoon Shift', 'Night Shift']),
            'code' => 'SHIFT_'.strtoupper(Str::random(6)),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'is_overnight' => false,
            'is_active' => true,
        ];
    }
}

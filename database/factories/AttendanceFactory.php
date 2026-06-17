<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');
        $checkIn = (clone $date)->setTime(8, fake()->numberBetween(0, 30));
        $checkOut = (clone $date)->setTime(17, fake()->numberBetween(0, 30));

        return [
            'employee_id' => Employee::factory(),
            'date' => $date->format('Y-m-d'),
            'check_in_at' => $checkIn,
            'check_in_lat' => fake()->latitude(-6.5, -6.0),
            'check_in_lng' => fake()->longitude(106.5, 107.0),
            'check_out_at' => $checkOut,
            'check_out_lat' => fake()->latitude(-6.5, -6.0),
            'check_out_lng' => fake()->longitude(106.5, 107.0),
            'status' => fake()->randomElement([
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ]),
            'late_minutes' => fake()->numberBetween(0, 30),
            'early_leave_minutes' => 0,
            'work_minutes' => fake()->numberBetween(480, 540),
        ];
    }
}

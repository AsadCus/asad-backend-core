<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Office Mon-Fri', 'Standard 5-Day Week', 'Six-Day Office']),
            'code' => 'WS_'.strtoupper(Str::random(6)),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (WorkSchedule $schedule) {
            $shift = Shift::query()->first() ?? Shift::factory()->create();

            // Mon (1) – Fri (5) workdays; Sat (6) and Sun (0) off
            for ($day = 0; $day <= 6; $day++) {
                $isWorkday = $day >= 1 && $day <= 5;
                WorkScheduleDay::create([
                    'work_schedule_id' => $schedule->id,
                    'day_of_week' => $day,
                    'shift_id' => $isWorkday ? $shift->id : null,
                    'is_workday' => $isWorkday,
                ]);
            }
        });
    }
}

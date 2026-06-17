<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('+1 day', '+1 year'),
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement(HolidayType::values()),
            'description' => fake()->optional()->sentence(),
            'is_recurring' => false,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ManagementLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagementLevel>
 */
class ManagementLevelFactory extends Factory
{
    protected $model = ManagementLevel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Top', 'Middle', 'Low']),
            'code' => strtoupper(fake()->unique()->bothify('LVL-##')),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}

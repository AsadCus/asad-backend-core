<?php

namespace Database\Factories;

use App\Enums\PositionLevel;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => 'POS_'.strtoupper(Str::random(6)),
            'level' => fake()->randomElement(PositionLevel::values()),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationLevel>
 */
class EducationLevelFactory extends Factory
{
    protected $model = EducationLevel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Primary', 'Secondary', 'Diploma', 'Bachelor']),
        ];
    }
}

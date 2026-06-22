<?php

namespace Database\Factories;

use App\Models\RoleGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleGroup>
 */
class RoleGroupFactory extends Factory
{
    protected $model = RoleGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->bothify('GRP-##')),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}

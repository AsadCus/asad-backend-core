<?php

namespace Database\Factories;

use App\Models\Holding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Holding>
 */
class HoldingFactory extends Factory
{
    protected $model = Holding::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'code' => strtoupper(Str::slug($name, '_')).'_'.fake()->unique()->numberBetween(100, 9999),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' Office',
            'country_id' => Country::factory(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'latitude' => fake()->latitude(-6.5, -6.0),
            'longitude' => fake()->longitude(106.5, 107.0),
            'geofence_radius_meters' => 100,
        ];
    }
}

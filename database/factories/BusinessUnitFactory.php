<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\Holding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    protected $model = BusinessUnit::class;

    public function definition(): array
    {
        $name = fake()->company().' Unit';

        return [
            'holding_id' => Holding::factory(),
            'name' => $name,
            'code' => 'BU_'.strtoupper(Str::random(6)),
            'is_active' => true,
        ];
    }
}

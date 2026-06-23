<?php

namespace Database\Factories;

use App\Models\OrgInfo;
use App\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgInfo>
 */
class OrgInfoFactory extends Factory
{
    protected $model = OrgInfo::class;

    public function definition(): array
    {
        return [
            'org_unit_id' => OrgUnit::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'sort_order' => 0,
        ];
    }
}

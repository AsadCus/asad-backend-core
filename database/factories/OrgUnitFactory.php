<?php

namespace Database\Factories;

use App\Enums\OrgUnitType;
use App\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    protected $model = OrgUnit::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => OrgUnitType::Holding,
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('OU-####')),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Place this unit under a parent with a specific type.
     */
    public function type(OrgUnitType $type, ?OrgUnit $parent = null): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'parent_id' => $parent?->id,
        ]);
    }
}

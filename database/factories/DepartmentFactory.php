<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'name' => fake()->randomElement([
                'Finance', 'Human Resources', 'Engineering', 'Sales',
                'Marketing', 'Operations', 'Customer Support', 'Legal',
            ]),
            'code' => 'DEPT_'.strtoupper(Str::random(6)),
            'is_active' => true,
        ];
    }
}

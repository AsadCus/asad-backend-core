<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Annual Leave', 'Sick Leave', 'Compassionate Leave']),
            'code' => 'LT_'.strtoupper(Str::random(6)),
            'max_days_per_year' => fake()->randomElement([12, 30, 90]),
            'requires_balance' => true,
            'requires_attachment' => false,
            'is_paid' => true,
            'gender_restriction' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}

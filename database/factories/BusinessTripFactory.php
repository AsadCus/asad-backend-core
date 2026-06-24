<?php

namespace Database\Factories;

use App\Enums\BusinessTripStatus;
use App\Models\BusinessTrip;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessTrip>
 */
class BusinessTripFactory extends Factory
{
    protected $model = BusinessTrip::class;

    public function definition(): array
    {
        return [
            'btr_no' => 'BTF/HCD/'.strtoupper(Str::random(8)),
            'employee_id' => Employee::factory(),
            'work_type' => 'operational',
            'project_name' => fake()->bs(),
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'destination_address' => fake()->address(),
            'depart_at' => fake()->dateTimeBetween('now', '+10 days'),
            'return_at' => fake()->dateTimeBetween('+11 days', '+15 days'),
            'bank' => 'BANK MANDIRI',
            'account_no' => fake()->numerify('##########'),
            'account_holder' => fake()->name(),
            'cost_breakdown' => [
                ['title' => 'General', 'items' => [
                    ['description' => 'Uang Makan', 'cost' => 75000, 'qty' => 2, 'unit' => 'Makan'],
                ]],
            ],
            'grand_total' => 150000,
            'status' => BusinessTripStatus::PendingLeader->value,
        ];
    }
}

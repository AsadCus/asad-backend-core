<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_no' => 'EMP-'.strtoupper(Str::random(8)),
            'nik' => fake()->numerify('################'),
            'gender' => fake()->randomElement(Gender::values()),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years'),
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'employment_status' => fake()->randomElement(EmploymentStatus::values()),
            'termination_date' => null,
            'org_unit_id' => OrgUnit::factory(),
            'branch_id' => Branch::factory(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}

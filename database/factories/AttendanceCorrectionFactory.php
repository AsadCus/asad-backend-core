<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\AttendanceCorrectionType;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition(): array
    {
        return [
            'correction_no' => 'COR-'.strtoupper(Str::random(8)),
            'employee_id' => Employee::factory(),
            'date' => fake()->dateTimeBetween('-15 days', 'now')->format('Y-m-d'),
            'correction_type' => fake()->randomElement(AttendanceCorrectionType::values()),
            'reason' => fake()->sentence(),
            'status' => ApprovalStatus::PendingSupervisor->value,
        ];
    }
}

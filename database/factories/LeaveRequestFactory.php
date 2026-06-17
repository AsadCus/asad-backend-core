<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('+1 day', '+30 days'));
        $end = (clone $start)->addDays(fake()->numberBetween(0, 4));

        return [
            'request_no' => 'LR-'.strtoupper(Str::random(8)),
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days' => $start->diffInDays($end) + 1,
            'reason' => fake()->sentence(),
            'status' => ApprovalStatus::PendingSupervisor->value,
        ];
    }
}

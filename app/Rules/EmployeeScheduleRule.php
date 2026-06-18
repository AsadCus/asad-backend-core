<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class EmployeeScheduleRule
{
    public function rules(?string $id = null): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'work_schedule_id' => ['required', 'integer', Rule::exists('work_schedules', 'id')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

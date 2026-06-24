<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class WorkScheduleRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('work_schedules', 'code')->ignore($id)],
            'owner_org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            // Weekly pattern: one row per weekday (0=Sun … 6=Sat).
            'days' => ['nullable', 'array', 'max:7'],
            'days.*.day_of_week' => ['required_with:days', 'integer', 'between:0,6'],
            'days.*.shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'days.*.is_workday' => ['boolean'],
        ];
    }
}

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
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}

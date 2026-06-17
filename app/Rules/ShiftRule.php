<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class ShiftRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('shifts', 'code')->ignore($id)],
            'start_time' => ['required', 'date_format:H:i:s,H:i'],
            'end_time' => ['required', 'date_format:H:i:s,H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'is_overnight' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}

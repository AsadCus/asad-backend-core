<?php

namespace App\Rules;

use App\Enums\Gender;
use Illuminate\Validation\Rule;

class LeaveTypeRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'code')->ignore($id)],
            'max_days_per_year' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'requires_balance' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'is_paid' => ['boolean'],
            'gender_restriction' => ['nullable', Rule::in(array_column(Gender::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}

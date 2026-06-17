<?php

namespace App\Rules;

use App\Enums\HolidayType;
use Illuminate\Validation\Rule;

class HolidayRule
{
    public function rules(?string $id = null): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(HolidayType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_recurring' => ['boolean'],
        ];
    }
}

<?php

namespace App\Rules;

use App\Enums\PositionLevel;
use Illuminate\Validation\Rule;

class PositionRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('positions', 'code')->ignore($id)],
            'level' => ['required', Rule::in(array_column(PositionLevel::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}

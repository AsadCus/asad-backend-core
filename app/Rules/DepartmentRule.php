<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class DepartmentRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('departments', 'code')->ignore($id)],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'is_active' => ['boolean'],
        ];
    }
}

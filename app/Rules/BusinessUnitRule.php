<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class BusinessUnitRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('business_units', 'code')->ignore($id)],
            'holding_id' => ['required', 'integer', 'exists:holdings,id'],
            'is_active' => ['boolean'],
        ];
    }
}

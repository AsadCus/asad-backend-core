<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class OrgInfoRule
{
    public function rules(?string $id = null): array
    {
        return [
            'org_unit_id' => ['required', 'integer', Rule::exists('org_units', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

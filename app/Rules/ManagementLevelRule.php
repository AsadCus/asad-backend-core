<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class ManagementLevelRule
{
    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', Rule::unique('management_levels', 'code')->ignore($id)],
            'color' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}

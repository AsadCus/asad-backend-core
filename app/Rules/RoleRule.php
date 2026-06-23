<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class RoleRule
{
    public function rules(?string $id = null): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'role_group_id' => ['nullable', 'integer', Rule::exists('role_groups', 'id')],
            'management_level_id' => ['nullable', 'integer', Rule::exists('management_levels', 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}

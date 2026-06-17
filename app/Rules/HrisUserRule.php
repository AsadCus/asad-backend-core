<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class HrisUserRule
{
    public const ROLES = ['employee', 'supervisor', 'hr', 'manager', 'administrator'];

    public function rules(?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($id),
            ],
            'contact' => ['nullable', 'string', 'max:255'],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in(self::ROLES)],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
        ];
    }
}

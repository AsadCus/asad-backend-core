<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

class HrisUserRule
{
    /** Core system roles used for the role-specific user list pages + stats. */
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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            // Jabatan = role; any existing role is assignable (roles are user-managed now).
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
        ];
    }
}

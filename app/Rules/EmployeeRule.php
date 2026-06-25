<?php

namespace App\Rules;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Models\Employee;
use Illuminate\Validation\Rule;

class EmployeeRule
{
    /**
     * One form drives both the login account and the Employee profile. `employee_no` is
     * auto-generated (not user-supplied); the email belongs to the linked user.
     */
    public function rules(?string $id = null): array
    {
        $userId = $id ? Employee::find($id)?->user_id : null;

        return [
            // Account (login) fields
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($userId)],
            // Required on create, optional on edit (blank = keep current password).
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            // The administrator role is granted out-of-band, never through this form.
            'role' => ['required', 'string', 'not_in:administrator', Rule::exists('roles', 'name')],

            // Profile fields
            'nik' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'birth_date' => ['nullable', 'date'],
            'hire_date' => ['required', 'date'],
            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],
            'termination_date' => ['nullable', 'date'],
            'org_unit_id' => ['required', 'integer', Rule::exists('org_units', 'id')],
            'work_location_org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}

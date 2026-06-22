<?php

namespace App\Rules;

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use Illuminate\Validation\Rule;

class EmployeeRule
{
    public function rules(?string $id = null): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('employees', 'user_id')->ignore($id)],
            'employee_no' => ['required', 'string', 'max:100', Rule::unique('employees', 'employee_no')->ignore($id)],
            'nik' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(Gender::values())],
            'birth_date' => ['nullable', 'date'],
            'religion_id' => ['nullable', 'integer', Rule::exists('religions', 'id')],
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],
            'hire_date' => ['required', 'date'],
            'employment_status' => ['required', Rule::in(EmploymentStatus::values())],
            'termination_date' => ['nullable', 'date'],
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'work_location_org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'scope_org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];
    }
}

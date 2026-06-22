<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\HrisScope;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function getForDataTable()
    {
        return HrisScope::apply(Employee::query()->with(['user.roles', 'orgUnit']))
            ->orderBy('employee_no')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'employee_no' => $q->employee_no,
                'name' => $q->user?->name,
                'nik' => $q->nik,
                'role_name' => $q->user?->roles->first()?->label ?? $q->user?->roles->first()?->name,
                'org_unit_name' => $q->orgUnit?->name,
                'employment_status' => $q->employment_status?->value,
                'is_active' => (bool) $q->is_active,
            ]);
    }

    public function getForFilter()
    {
        return Employee::query()
            ->with('user')
            ->orderBy('employee_no')
            ->get()
            ->map(fn ($q) => [
                'value' => $q->id,
                'label' => $q->employee_no.($q->user ? ' - '.$q->user->name : ''),
            ]);
    }

    public function getForEditShow($id)
    {
        $employee = Employee::findOrFail($id);

        return [
            'id' => $employee->id,
            'user_id' => $employee->user_id,
            'employee_no' => $employee->employee_no,
            'nik' => $employee->nik,
            'gender' => $employee->gender?->value,
            'birth_date' => $employee->birth_date?->format('Y-m-d'),
            'religion_id' => $employee->religion_id,
            'education_level_id' => $employee->education_level_id,
            'hire_date' => $employee->hire_date?->format('Y-m-d'),
            'employment_status' => $employee->employment_status?->value,
            'termination_date' => $employee->termination_date?->format('Y-m-d'),
            'org_unit_id' => $employee->org_unit_id,
            'work_location_org_unit_id' => $employee->work_location_org_unit_id,
            'scope_org_unit_id' => $employee->scope_org_unit_id,
            'supervisor_id' => $employee->supervisor_id,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
            'is_active' => (bool) $employee->is_active,
        ];
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $employee = Employee::create($data);

            activity()->performedOn($employee)->log('Employee created successfully #'.($employee->id ?? null));

            return $employee;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $employee = Employee::findOrFail($id);
            $employee->update($data);

            activity()->performedOn($employee)->log('Employee updated successfully #'.($employee->id ?? null));

            return $employee;
        });
    }

    public function delete($id)
    {
        $employee = Employee::find($id);

        if (! $employee) {
            return false;
        }

        $employee->delete();

        activity()->performedOn($employee)->log('Employee deleted successfully #'.($employee->id ?? null));

        return true;
    }
}

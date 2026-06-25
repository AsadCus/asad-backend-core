<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Support\HrisScope;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private HrisUserService $accounts) {}

    public function getForDataTable()
    {
        return HrisScope::apply(Employee::query()->with(['user.roles', 'orgUnit']))
            ->orderBy('employee_no')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'employee_no' => $q->employee_no,
                'name' => $q->user?->name,
                'email' => $q->user?->email,
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
            ->with('user.roles')
            ->get()
            ->map(fn ($q) => [
                'value' => $q->id,
                'label' => ($q->user?->name ?? $q->employee_no)
                    .($q->user?->roles->first() ? ' ('.($q->user->roles->first()->label ?? $q->user->roles->first()->name).')' : ''),
            ])
            ->sortBy('label')
            ->values();
    }

    public function getForEditShow($id)
    {
        $employee = Employee::with('user.roles')->findOrFail($id);

        return [
            'id' => $employee->id,
            'user_id' => $employee->user_id,
            'employee_no' => $employee->employee_no,
            'name' => $employee->user?->name,
            'email' => $employee->user?->email,
            'role' => $employee->user?->roles->first()?->name,
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
            // One "person" = a login account (name/email/password/role) + an Employee profile.
            $user = $this->accounts->provisionAccount($data);
            $employee = Employee::create($this->employeeAttributes($data, $user, null));

            activity()->performedOn($employee)->log('Employee created successfully #'.($employee->id ?? null));

            return $employee;
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $employee = Employee::findOrFail($id);
            $user = $this->accounts->provisionAccount($data, $employee->user_id);
            $employee->update($this->employeeAttributes($data, $user, $employee));

            activity()->performedOn($employee)->log('Employee updated successfully #'.($employee->id ?? null));

            return $employee;
        });
    }

    /**
     * Map the form payload to Employee columns. `employee_no` is auto-generated from the linked
     * user id on first create and never regenerated; `user_id` links the login account.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function employeeAttributes(array $data, User $user, ?Employee $existing): array
    {
        return [
            'user_id' => $user->id,
            'employee_no' => $existing?->employee_no ?? sprintf('EMP-%04d', $user->id),
            'nik' => $data['nik'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'hire_date' => $data['hire_date'],
            'employment_status' => $data['employment_status'],
            'termination_date' => $data['termination_date'] ?? null,
            'org_unit_id' => $data['org_unit_id'] ?? null,
            'work_location_org_unit_id' => $data['work_location_org_unit_id'] ?? null,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
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

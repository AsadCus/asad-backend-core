<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Employee;
use App\Models\GhostUser;
use App\Models\Holding;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * One login per HRIS role + the ghost administrator (asad@example.com).
     * Each user gets a linked Employee record carrying its position/jabatan.
     */
    public function run(): void
    {
        $holding = Holding::query()->where('code', 'ASAD-GROUP')->first();
        $businessUnit = BusinessUnit::query()->where('code', 'BU-TECH')->first();
        $hrDepartment = Department::query()->where('code', 'DEPT-HR')->first();
        $engDepartment = Department::query()->where('code', 'DEPT-ENG')->first();

        $positions = Position::query()->pluck('id', 'code'); // ['CEO' => 1, ...]

        // [email, name, role, position code, department, is ghost]
        $users = [
            ['asad@example.com', 'Asad', 'administrator', 'CEO', $hrDepartment, true],
            ['administrator@example.com', 'Administrator', 'administrator', 'DIRECTOR', $hrDepartment, false],
            ['manager@example.com', 'Manager', 'manager', 'HR_MANAGER', $hrDepartment, false],
            ['supervisor@example.com', 'Supervisor', 'supervisor', 'SUPERVISOR', $engDepartment, false],
            ['hr@example.com', 'HR Personnel', 'hr', 'HR_OFFICER', $hrDepartment, false],
            ['employee@example.com', 'Employee', 'employee', 'STAFF', $engDepartment, false],
        ];

        $sequence = 1;

        foreach ($users as [$email, $name, $roleName, $positionCode, $department, $isGhost]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([Role::findByName($roleName)]);

            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_no' => sprintf('EMP-%04d', $sequence),
                    'position_id' => $positions[$positionCode] ?? null,
                    'holding_id' => $holding?->id,
                    'business_unit_id' => $businessUnit?->id,
                    'department_id' => $department?->id,
                    'hire_date' => now()->toDateString(),
                    'employment_status' => EmploymentStatus::Permanent->value,
                    'is_active' => true,
                ],
            );

            if ($isGhost) {
                GhostUser::firstOrCreate(['user_id' => $user->id]);
            }

            $sequence++;
        }

        $this->command->info('HRIS users seeded: 1 per role + asad@example.com (ghost administrator). Password: password');
    }
}

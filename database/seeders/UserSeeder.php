<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\GhostUser;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * One login per HRIS role + the ghost administrator (asad@example.com).
     * Each user gets a linked Employee placed in the org tree. Jabatan = the user's role.
     */
    public function run(): void
    {
        $orgUnits = OrgUnit::query()->pluck('id', 'code'); // ['SMGI' => 1, ...]

        // [email, name, role, org-unit code, is ghost]
        $users = [
            ['asad@example.com', 'Asad', 'administrator', 'SMGI', true],
            ['administrator@example.com', 'Administrator', 'administrator', 'SMGI', false],
            ['manager@example.com', 'Manager', 'manager', 'SSM-HR', false],
            ['supervisor@example.com', 'Supervisor', 'supervisor', 'SSM-HR-POPS', false],
            ['hr@example.com', 'HR Personnel', 'hr', 'SSM-HR', false],
            ['employee@example.com', 'Employee', 'employee', 'SSM-HR-POPS', false],
        ];

        $sequence = 1;

        foreach ($users as [$email, $name, $roleName, $orgUnitCode, $isGhost]) {
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
                    'org_unit_id' => $orgUnits[$orgUnitCode] ?? null,
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

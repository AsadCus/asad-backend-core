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
     * `supervisor_email` wires up the reporting line so the demo accounts can actually
     * exercise the team-scoped screens (Approval Inbox, corrections view-team, etc.) —
     * without it every "view-team" query returns empty for every seeded supervisor.
     */
    public function run(): void
    {
        $orgUnits = OrgUnit::query()->pluck('id', 'code'); // ['SMGI' => 1, ...]

        // [email, name, role, org-unit code, is ghost, supervisor_email]
        $users = [
            ['asad@example.com', 'Asad', 'administrator', 'SMGI', true, null],
            ['administrator@example.com', 'Administrator', 'administrator', 'SMGI', false, null],
            ['manager@example.com', 'Manager', 'manager', 'SSM-HR', false, null],
            ['supervisor@example.com', 'Supervisor', 'supervisor', 'SSM-HR', false, 'manager@example.com'],
            ['hr@example.com', 'HR Personnel', 'hr', 'SSM-HR', false, null],
            ['employee@example.com', 'Employee', 'employee', 'SSM-HR', false, 'supervisor@example.com'],
        ];

        $sequence = 1;
        $employeeIds = []; // email => employee id, for the supervisor_id pass below.

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

            $employee = Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_no' => sprintf('EMP-%04d', $sequence),
                    'org_unit_id' => $orgUnits[$orgUnitCode] ?? null,
                    'hire_date' => now()->toDateString(),
                    'employment_status' => EmploymentStatus::Permanent->value,
                    'is_active' => true,
                ],
            );
            $employeeIds[$email] = $employee->id;

            if ($isGhost) {
                GhostUser::firstOrCreate(['user_id' => $user->id]);
            }

            $sequence++;
        }

        // Second pass: every employee now exists, so the supervisor_email → id lookup is safe
        // regardless of seeding order.
        foreach ($users as [$email, , , , , $supervisorEmail]) {
            if ($supervisorEmail === null) {
                continue;
            }
            Employee::where('id', $employeeIds[$email])
                ->update(['supervisor_id' => $employeeIds[$supervisorEmail]]);
        }

        $this->command->info('HRIS users seeded: 1 per role + asad@example.com (ghost administrator). Password: password');
    }
}

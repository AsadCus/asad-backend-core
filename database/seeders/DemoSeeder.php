<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo seed: 3-level hierarchy (manager → supervisor → 2 employees) per business unit.
 * Also registers hris.wfh-visit-request permissions and grants them to supervisor/hr.
 *
 * Purpose: test multi-level approval flows for WFH/Visit and attendance corrections.
 *
 * Accounts created (password: password)
 * ──────────────────────────────────────
 * SMGI Holding   smgi.manager@example.com  smgi.supervisor@example.com  smgi.emp1/2@example.com
 * SSM            ssm.manager@example.com   ssm.supervisor@example.com   ssm.emp1/2@example.com
 * GSL            gsl.manager@example.com   gsl.supervisor@example.com   gsl.emp1/2@example.com
 * SAMU           samu.manager@example.com  samu.supervisor@example.com  samu.emp1/2@example.com
 *
 * emp1 → Standard 5-Day (09-17)   emp2 → Shift Rotation (malam)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->registerWfhVisitPermissions();

        $orgUnits = OrgUnit::query()->pluck('id', 'code');

        // [buPrefix, managerOrgCode, supervisorOrgCode, employeeOrgCode]
        $businessUnits = [
            ['smgi', 'SMGI', 'SMGI', 'SMGI'],
            ['ssm',  'SSM-HR', 'SSM-HR-POPS', 'SSM-HR-POPS'],
            ['gsl',  'GSL-HO', 'GSL-HO', 'GSL-HO'],
            ['samu', 'SAMU-HO', 'SAMU-HO', 'SAMU-HO'],
        ];

        $standardSchedule = WorkSchedule::where('code', 'WS-STD')->first();
        $shiftSchedule = WorkSchedule::where('code', 'WS-SHIFT')->first();

        $sequence = 100; // Start employee_no at EMP-0100 to avoid collision with UserSeeder

        foreach ($businessUnits as [$prefix, $mgrOrg, $supOrg, $empOrg]) {
            // ── Manager (atasannya lagi) ────────────────────────────────────────────
            $manager = $this->upsertUser(
                email: "{$prefix}.manager@example.com",
                name: strtoupper($prefix).' Manager',
                roleName: 'manager',
                orgUnitId: $orgUnits[$mgrOrg] ?? null,
                employeeNo: sprintf('EMP-%04d', $sequence++),
            );

            // ── Supervisor (atasan) ─────────────────────────────────────────────────
            $supervisor = $this->upsertUser(
                email: "{$prefix}.supervisor@example.com",
                name: strtoupper($prefix).' Supervisor',
                roleName: 'supervisor',
                orgUnitId: $orgUnits[$supOrg] ?? null,
                employeeNo: sprintf('EMP-%04d', $sequence++),
                supervisorId: $manager->id,
            );

            // ── Employee 1 — Regular schedule (9-to-5) ─────────────────────────────
            $emp1 = $this->upsertUser(
                email: "{$prefix}.emp1@example.com",
                name: strtoupper($prefix).' Employee 1',
                roleName: 'employee',
                orgUnitId: $orgUnits[$empOrg] ?? null,
                employeeNo: sprintf('EMP-%04d', $sequence++),
                supervisorId: $supervisor->id,
            );

            // ── Employee 2 — Shift/night schedule ──────────────────────────────────
            $emp2 = $this->upsertUser(
                email: "{$prefix}.emp2@example.com",
                name: strtoupper($prefix).' Employee 2',
                roleName: 'employee',
                orgUnitId: $orgUnits[$empOrg] ?? null,
                employeeNo: sprintf('EMP-%04d', $sequence++),
                supervisorId: $supervisor->id,
            );

            // Assign schedules (idempotent — skip if already has one)
            if ($standardSchedule) {
                $this->assignSchedule($emp1, $standardSchedule);
            }
            if ($shiftSchedule) {
                $this->assignSchedule($emp2, $shiftSchedule);
            }
        }

        $this->command->info(
            'Demo seed complete: 4 BUs × (manager + supervisor + 2 employees). Password: password'
        );
    }

    /** Create or update a User + Employee, sync role, return the Employee model. */
    private function upsertUser(
        string $email,
        string $name,
        string $roleName,
        ?int $orgUnitId,
        string $employeeNo,
        ?int $supervisorId = null,
    ): Employee {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([Role::findByName($roleName)]);

        return Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no' => $employeeNo,
                'org_unit_id' => $orgUnitId,
                'supervisor_id' => $supervisorId,
                'hire_date' => now()->toDateString(),
                'employment_status' => EmploymentStatus::Permanent->value,
                'is_active' => true,
            ],
        );
    }

    /** Assign a work schedule to an employee if they don't already have one starting today. */
    private function assignSchedule(Employee $employee, WorkSchedule $schedule): void
    {
        $exists = EmployeeSchedule::where('employee_id', $employee->id)
            ->where('work_schedule_id', $schedule->id)
            ->exists();

        if (! $exists) {
            EmployeeSchedule::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
            ]);
        }
    }

    /**
     * Register WFH/Visit permissions and grant them to the appropriate roles.
     * Safe to re-run — firstOrCreate is idempotent.
     */
    private function registerWfhVisitPermissions(): void
    {
        $wfhPerms = [
            'hris.wfh-visit-request create',
            'hris.wfh-visit-request view-own',
            'hris.wfh-visit-request view-team',
            'hris.wfh-visit-request view-all',
            'hris.wfh-visit-request approve-supervisor',
            'hris.wfh-visit-request verify-hr',
            'hris.wfh-visit-request cancel',
        ];

        foreach ($wfhPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Supervisor can approve first stage
        $supervisor = Role::findByName('supervisor');
        if ($supervisor) {
            $supervisor->givePermissionTo([
                'hris.wfh-visit-request view-team',
                'hris.wfh-visit-request approve-supervisor',
            ]);
        }

        // HR can verify second stage + see all
        $hr = Role::findByName('hr');
        if ($hr) {
            $hr->givePermissionTo([
                'hris.wfh-visit-request view-all',
                'hris.wfh-visit-request verify-hr',
            ]);
        }

        // Employees can create + view own
        $employee = Role::findByName('employee');
        if ($employee) {
            $employee->givePermissionTo([
                'hris.wfh-visit-request create',
                'hris.wfh-visit-request view-own',
                'hris.wfh-visit-request cancel',
            ]);
        }

        // Manager — read-only team visibility
        $manager = Role::findByName('manager');
        if ($manager) {
            $manager->givePermissionTo(['hris.wfh-visit-request view-team']);
        }

        // Administrator — all (idempotent via givePermissionTo)
        $administrator = Role::findByName('administrator');
        if ($administrator) {
            $administrator->givePermissionTo($wfhPerms);
        }
    }
}

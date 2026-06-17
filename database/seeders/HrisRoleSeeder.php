<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HrisRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // HRIS permissions — scoped by the Role & Access Matrix in HRIS_PRESENSI_FLOWCHARTS.md.
        $permissions = [
            // Master data
            'hris.holding view', 'hris.holding create', 'hris.holding edit', 'hris.holding delete',
            'hris.business-unit view', 'hris.business-unit create', 'hris.business-unit edit', 'hris.business-unit delete',
            'hris.department view', 'hris.department create', 'hris.department edit', 'hris.department delete',
            'hris.position view', 'hris.position create', 'hris.position edit', 'hris.position delete',
            'hris.work-location view', 'hris.work-location create', 'hris.work-location edit', 'hris.work-location delete',

            // Schedule
            'hris.shift view', 'hris.shift create', 'hris.shift edit', 'hris.shift delete',
            'hris.work-schedule view', 'hris.work-schedule create', 'hris.work-schedule edit', 'hris.work-schedule delete',
            'hris.holiday view', 'hris.holiday create', 'hris.holiday edit', 'hris.holiday delete',
            'hris.employee-schedule view', 'hris.employee-schedule assign',

            // Employee
            'hris.employee view-all', 'hris.employee view-team', 'hris.employee view-own',
            'hris.employee create', 'hris.employee edit', 'hris.employee delete',

            // Attendance
            'hris.attendance check-in', 'hris.attendance view-own',
            'hris.attendance view-team', 'hris.attendance view-all',

            // Correction
            'hris.attendance-correction create', 'hris.attendance-correction view-own',
            'hris.attendance-correction view-team', 'hris.attendance-correction view-all',
            'hris.attendance-correction approve-supervisor', 'hris.attendance-correction verify-hr',

            // Leave
            'hris.leave-type view', 'hris.leave-type create', 'hris.leave-type edit', 'hris.leave-type delete',
            'hris.leave-balance view-own', 'hris.leave-balance view-team', 'hris.leave-balance manage',
            'hris.leave-request create', 'hris.leave-request view-own',
            'hris.leave-request view-team', 'hris.leave-request view-all',
            'hris.leave-request approve-supervisor', 'hris.leave-request verify-hr',

            // Approval matrix + reports
            'hris.approval-matrix view', 'hris.approval-matrix edit',
            'hris.attendance-report view', 'hris.attendance-report export',
            'hris.leave-report view', 'hris.leave-report export',

            // Audit trail
            'hris.audit-trail view-all', 'hris.audit-trail view-related',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // HRIS roles
        $hrisRoles = ['hr', 'supervisor', 'manager', 'employee'];

        foreach ($hrisRoles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Administrator already exists (from RolePermissionSeeder); give them full HRIS access too.
        $administrator = Role::findByName('administrator');
        if ($administrator) {
            $administrator->givePermissionTo($permissions);
        }

        // HR (Personalia) — verify HR, manage master + reports + audit + user accounts.
        Role::findByName('hr')->syncPermissions([
            'dashboard view', 'master view',
            'user view', 'user create', 'user edit', 'user delete',
            'hris.employee view-all', 'hris.employee create', 'hris.employee edit',
            'hris.holding view', 'hris.holding create', 'hris.holding edit',
            'hris.business-unit view', 'hris.business-unit create', 'hris.business-unit edit',
            'hris.department view', 'hris.department create', 'hris.department edit',
            'hris.position view', 'hris.position create', 'hris.position edit',
            'hris.work-location view', 'hris.work-location create', 'hris.work-location edit',
            'hris.shift view', 'hris.shift create', 'hris.shift edit',
            'hris.work-schedule view', 'hris.work-schedule create', 'hris.work-schedule edit',
            'hris.holiday view', 'hris.holiday create', 'hris.holiday edit',
            'hris.employee-schedule view', 'hris.employee-schedule assign',
            'hris.attendance view-all',
            'hris.attendance-correction view-all', 'hris.attendance-correction verify-hr',
            'hris.leave-type view', 'hris.leave-type create', 'hris.leave-type edit',
            'hris.leave-balance manage',
            'hris.leave-request view-all', 'hris.leave-request verify-hr',
            'hris.approval-matrix view', 'hris.approval-matrix edit',
            'hris.attendance-report view', 'hris.attendance-report export',
            'hris.leave-report view', 'hris.leave-report export',
            'hris.audit-trail view-related',
        ]);

        // Supervisor — approve team's correction & leave; view team.
        Role::findByName('supervisor')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-team',
            'hris.attendance view-team',
            'hris.attendance-correction view-team', 'hris.attendance-correction approve-supervisor',
            'hris.leave-request view-team', 'hris.leave-request approve-supervisor',
            'hris.leave-balance view-team',
            'hris.attendance-report view',
            'hris.leave-report view',
        ]);

        // Manager — read-only summary.
        Role::findByName('manager')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-team',
            'hris.attendance view-team',
            'hris.attendance-correction view-team',
            'hris.leave-request view-team',
            'hris.leave-balance view-team',
            'hris.attendance-report view', 'hris.attendance-report export',
            'hris.leave-report view', 'hris.leave-report export',
        ]);

        // Employee — own data + create attendance/correction/leave.
        Role::findByName('employee')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-own',
            'hris.attendance check-in', 'hris.attendance view-own',
            'hris.attendance-correction create', 'hris.attendance-correction view-own',
            'hris.leave-balance view-own',
            'hris.leave-request create', 'hris.leave-request view-own',
        ]);
    }
}

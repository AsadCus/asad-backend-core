<?php

namespace Database\Seeders;

use App\Models\ManagementLevel;
use App\Models\Role;
use App\Models\RoleGroup;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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
            'hris.attendance-eligibility manage',

            // Correction
            'hris.attendance-correction create', 'hris.attendance-correction view-own',
            'hris.attendance-correction view-team', 'hris.attendance-correction view-all',
            'hris.attendance-correction approve-supervisor', 'hris.attendance-correction verify-hr',

            // Business trip — submit → leader → HC → finance approval, then disbursement + report.
            'hris.business-trip create', 'hris.business-trip view-own',
            'hris.business-trip view-team', 'hris.business-trip view-all',
            'hris.business-trip approve-leader', 'hris.business-trip approve-hc',
            'hris.business-trip approve-finance', 'hris.business-trip pay',

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

            // Menu management — global sidebar config (administrator only).
            'hris.menu manage',

            // Role = Jabatan management + classification masters.
            'hris.role view', 'hris.role create', 'hris.role edit', 'hris.role delete',
            'hris.role-group view', 'hris.role-group create', 'hris.role-group edit', 'hris.role-group delete',
            'hris.management-level view', 'hris.management-level create', 'hris.management-level edit', 'hris.management-level delete',

            // Company info (Informasi Perusahaan) per org unit.
            'hris.company-info view', 'hris.company-info manage',

            // Simple CRUD aliases consumed by the admin/HR master harness (view/create/edit/delete).
            // The granular view-team/view-own strings above drive the Tier-2 self-service screens.
            'hris.employee view',
            'hris.employee-schedule create', 'hris.employee-schedule edit', 'hris.employee-schedule delete',
            'hris.approval-matrix create', 'hris.approval-matrix delete',
            'hris.leave-balance view', 'hris.leave-balance create', 'hris.leave-balance edit', 'hris.leave-balance delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // HRIS roles
        $hrisRoles = ['hr', 'supervisor', 'manager', 'employee'];

        foreach ($hrisRoles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Administrator already exists (from RolePermissionSeeder). Grant every permission that
        // exists so it keeps full access without the removed full-access flag — additive, so it
        // composes with the core grants regardless of seeder order.
        $administrator = Role::findByName('administrator');
        if ($administrator) {
            $administrator->givePermissionTo(Permission::query()->pluck('name')->all());
        }

        // HR (Personalia) — verify HR, manage master + reports + audit + user accounts.
        Role::findByName('hr')->syncPermissions([
            'dashboard view', 'master view',
            'user view', 'user create', 'user edit', 'user delete',
            'hris.employee view-all', 'hris.employee view', 'hris.employee create', 'hris.employee edit',
            'hris.employee-schedule create', 'hris.employee-schedule edit',
            'hris.approval-matrix create',
            'hris.leave-balance view', 'hris.leave-balance create', 'hris.leave-balance edit',
            'hris.holding view', 'hris.holding create', 'hris.holding edit',
            'hris.business-unit view', 'hris.business-unit create', 'hris.business-unit edit',
            'hris.department view', 'hris.department create', 'hris.department edit',
            'hris.position view', 'hris.position create', 'hris.position edit',
            'hris.work-location view', 'hris.work-location create', 'hris.work-location edit',
            'hris.shift view', 'hris.shift create', 'hris.shift edit',
            'hris.work-schedule view', 'hris.work-schedule create', 'hris.work-schedule edit',
            'hris.holiday view', 'hris.holiday create', 'hris.holiday edit',
            'hris.employee-schedule view', 'hris.employee-schedule assign',
            'hris.attendance check-in', 'hris.attendance view-own', 'hris.attendance view-all',
            'hris.attendance-correction view-all', 'hris.attendance-correction verify-hr',
            'hris.leave-type view', 'hris.leave-type create', 'hris.leave-type edit',
            'hris.leave-balance manage',
            'hris.leave-request view-all', 'hris.leave-request verify-hr',
            'hris.approval-matrix view', 'hris.approval-matrix edit',
            'hris.attendance-report view', 'hris.attendance-report export',
            'hris.leave-report view', 'hris.leave-report export',
            'hris.audit-trail view-related',
            'hris.company-info view', 'hris.company-info manage',
            'hris.business-trip view-all', 'hris.business-trip approve-hc',
        ]);

        // Supervisor — approve team's correction & leave; view team.
        Role::findByName('supervisor')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-team',
            'hris.attendance check-in', 'hris.attendance view-own', 'hris.attendance view-team',
            'hris.attendance-correction view-team', 'hris.attendance-correction approve-supervisor',
            'hris.leave-request view-team', 'hris.leave-request approve-supervisor',
            'hris.leave-balance view-team',
            'hris.attendance-report view',
            'hris.leave-report view',
            'hris.business-trip view-team', 'hris.business-trip approve-leader',
        ]);

        // Manager — read-only summary.
        Role::findByName('manager')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-team',
            'hris.attendance check-in', 'hris.attendance view-own', 'hris.attendance view-team',
            'hris.attendance-correction view-team',
            'hris.leave-request view-team',
            'hris.leave-balance view-team',
            'hris.attendance-report view', 'hris.attendance-report export',
            'hris.leave-report view', 'hris.leave-report export',
            'hris.business-trip view-team',
        ]);

        // Employee — own data + create attendance/correction/leave.
        Role::findByName('employee')->syncPermissions([
            'dashboard view', 'master view',
            'hris.employee view-own',
            'hris.attendance check-in', 'hris.attendance view-own',
            'hris.attendance-correction create', 'hris.attendance-correction view-own',
            'hris.leave-balance view-own',
            'hris.leave-request create', 'hris.leave-request view-own',
            'hris.business-trip create', 'hris.business-trip view-own',
        ]);

        $this->applyRoleMetadata();
    }

    /**
     * Role = Jabatan: stamp display + classification metadata on the core roles,
     * and seed Director / Finance as editable starter roles. Machine names stay stable.
     */
    private function applyRoleMetadata(): void
    {
        $groups = RoleGroup::query()->pluck('id', 'code');
        $levels = ManagementLevel::query()->pluck('id', 'code');

        // name => [label, group code, level code]
        $core = [
            'administrator' => ['Administrator', 'LEAD', 'TOP'],
            'manager' => ['Manager', 'LEAD', 'MID'],
            'supervisor' => ['Supervisor', 'GEN', 'MID'],
            'hr' => ['HR', 'HRADM', 'MID'],
            'employee' => ['Staff', 'GEN', 'LOW'],
        ];

        foreach ($core as $name => [$label, $group, $level]) {
            Role::findByName($name, 'web')->update([
                'label' => $label,
                'role_group_id' => $groups[$group] ?? null,
                'management_level_id' => $levels[$level] ?? null,
            ]);
        }

        // Editable starter roles requested as defaults — admins assign permissions via the UI.
        $starters = [
            'director' => ['Director', 'LEAD', 'TOP'],
            'finance' => ['Finance', 'HRADM', 'MID'],
        ];

        foreach ($starters as $name => [$label, $group, $level]) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->update([
                'label' => $label,
                'role_group_id' => $groups[$group] ?? null,
                'management_level_id' => $levels[$level] ?? null,
            ]);
            $role->syncPermissions(['dashboard view', 'master view']);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ERP permissions
        $permissions = [
            'dashboard view',
            'master view',
            'user view',
            'user create',
            'user edit',
            'user delete',
            'sales view',
            'sales create',
            'sales edit',
            'sales delete',
            'customer view',
            'customer create',
            'customer edit',
            'customer delete',
            'quotation view',
            'quotation create',
            'quotation edit',
            'quotation delete',
            'order view',
            'order create',
            'order edit',
            'order delete',
            'invoice view',
            'invoice create',
            'invoice edit',
            'invoice delete',
            'receipt view',
            'receipt create',
            'receipt edit',
            'receipt delete',

            'general-enquiry view',
            'general-enquiry create',
            'general-enquiry edit',
            'general-enquiry delete',
            'private-enquiry view',
            'private-enquiry create',
            'private-enquiry edit',
            'private-enquiry delete',

            'package view',
            'package create',
            'package edit',
            'package delete',
            'manifest view',
            'manifest create',
            'manifest edit',
            'ops-movement view',
            'ops-movement edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ERP roles — `administrator` replaces the old superadmin+admin pair.
        $roles = [
            'administrator',
            'sales',
            'customer',
            'operations',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        // Administrator gets the union of what superadmin+admin used to hold (i.e. full ERP).
        $administratorPermissions = $permissions;

        $salesPermissions = [
            'dashboard view',
            'customer view',
            'customer create',
            'customer edit',
            'customer delete',
            'quotation view',
            'quotation create',
            'quotation edit',
            'quotation delete',
            'order view',
            'order create',
            'order edit',
            'order delete',
            'invoice view',
            'invoice create',
            'invoice edit',
            'invoice delete',
            'receipt view',
            'receipt create',
            'receipt edit',
            'receipt delete',

            'general-enquiry view',
            'general-enquiry create',
            'general-enquiry edit',
            'general-enquiry delete',
            'private-enquiry view',
            'private-enquiry create',
            'private-enquiry edit',
            'private-enquiry delete',
            'manifest view',
            'manifest create',
            'manifest edit',
        ];

        Role::findByName('administrator')->syncPermissions($administratorPermissions);
        Role::findByName('sales')->syncPermissions($salesPermissions);
        Role::findByName('customer')->syncPermissions(['dashboard view']);
        Role::findByName('operations')->syncPermissions(['ops-movement view', 'ops-movement edit']);
    }
}

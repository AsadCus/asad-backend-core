<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

            'product-services view',
            'product-services edit',

            'package view',
            'package create',
            'package edit',
            'package delete',
            'package-proposal view',
            'package-proposal create',
            'package-proposal edit',
            'package-proposal delete',
            'package-proposal approve',
            'manifest view',
            'manifest create',
            'manifest edit',
            'ops-movement view',
            'ops-movement edit',

            'user-log view',
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
            'official',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        // Administrator gets the union of what superadmin+admin used to hold (i.e. full ERP).
        $administratorPermissions = $permissions;
        $superadminPermissions = $permissions;

        $salesPermissions = [
            'dashboard view',
            'customer view', 'customer create', 'customer edit', 'customer delete',
            'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
            'order view', 'order create', 'order edit', 'order delete',
            'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
            'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
            'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
            'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
            'product-services view', 'product-services edit',
            'package view',
            'package-proposal view', 'package-proposal create', 'package-proposal edit', 'package-proposal delete',
            'manifest view',
            'ops-movement view',
        ];

        Role::findByName('administrator')->syncPermissions($administratorPermissions);
        Role::findByName('sales')->syncPermissions($salesPermissions);
        Role::findByName('customer')->syncPermissions(['dashboard view']);
        Role::findByName('operations')->syncPermissions(['ops-movement view', 'ops-movement edit']);
        $adminPermissions = [
            'dashboard view',
            'customer view', 'customer create', 'customer edit', 'customer delete',
            'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
            'order view', 'order create', 'order edit', 'order delete',
            'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
            'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
            'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
            'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
            'package view',
            'package-proposal view', 'package-proposal create', 'package-proposal edit', 'package-proposal delete',
            'manifest view', 'manifest create', 'manifest edit',
            'ops-movement view',
        ];

        $operationsPermissions = [
            'customer view', 'customer create', 'customer edit', 'customer delete',
            'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
            'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
            'package view',
            'package-proposal view',
            'manifest view',
            'ops-movement view', 'ops-movement edit',
        ];

        // ponytail: superadmin/admin are legacy roles removed by the
        // migrate_admin_superadmin_to_administrator migration — only sync them if they still exist.
        if (Role::where('name', 'superadmin')->where('guard_name', 'web')->exists()) {
            Role::findByName('superadmin')->syncPermissions($superadminPermissions);
        }
        Role::findByName('sales')->syncPermissions($salesPermissions);
        if (Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
            Role::findByName('admin')->syncPermissions($adminPermissions);
        }
        Role::findByName('operations')->syncPermissions($operationsPermissions);
        Role::findByName('customer')->givePermissionTo(['dashboard view']);
        Role::findByName('official')->givePermissionTo(['dashboard view']);
    }
}

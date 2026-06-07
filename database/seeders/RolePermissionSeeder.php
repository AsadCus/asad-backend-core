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

        // Create permissions
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

        // Create roles
        $roles = [
            'superadmin',
            'admin',
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
            'manifest view',
            'ops-movement view',
        ];

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
            'manifest view', 'manifest create', 'manifest edit',
            'ops-movement view',
        ];

        $operationsPermissions = [
            'customer view', 'customer create', 'customer edit', 'customer delete',
            'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
            'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
            'package view',
            'manifest view',
            'ops-movement view', 'ops-movement edit',
        ];

        Role::findByName('superadmin')->syncPermissions($superadminPermissions);
        Role::findByName('sales')->syncPermissions($salesPermissions);
        Role::findByName('admin')->syncPermissions($adminPermissions);
        Role::findByName('operations')->syncPermissions($operationsPermissions);
        Role::findByName('customer')->givePermissionTo(['dashboard view']);
    }
}

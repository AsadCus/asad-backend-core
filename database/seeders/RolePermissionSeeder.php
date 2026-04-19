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
            'schedule view',
            'schedule create',
            'schedule edit',
            'schedule delete',
            'agreement view',
            'agreement create',
            'agreement edit',
            'agreement delete',

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

        // Create roles
        $roles = [
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

        // Assign permissions
        Role::findByName('admin')->givePermissionTo([
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
            'schedule view',
            'schedule create',
            'schedule edit',
            'schedule delete',
            'agreement view',
            'agreement create',
            'agreement edit',
            'agreement delete',

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
        ]);
        Role::findByName('sales')->givePermissionTo([
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
            'schedule view',
            'schedule create',
            'schedule edit',
            'agreement view',
            'agreement create',
            'agreement edit',

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
        ]);
        Role::findByName('customer')->givePermissionTo([
            'dashboard view',
        ]);

        Role::findByName('operations')->givePermissionTo([
            'ops-movement view',
            'ops-movement edit',
        ]);
    }
}

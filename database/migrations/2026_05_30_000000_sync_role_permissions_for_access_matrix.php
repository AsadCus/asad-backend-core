<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        foreach (['product-services view', 'product-services edit', 'user-log view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $targets = [
            'superadmin' => [
                'dashboard view',
                'master view',
                'user view', 'user create', 'user edit', 'user delete',
                'sales view', 'sales create', 'sales edit', 'sales delete',
                'customer view', 'customer create', 'customer edit', 'customer delete',
                'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
                'order view', 'order create', 'order edit', 'order delete',
                'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
                'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
                'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
                'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
                'product-services view', 'product-services edit',
                'package view', 'package create', 'package edit', 'package delete',
                'manifest view', 'manifest create', 'manifest edit',
                'ops-movement view', 'ops-movement edit',
                'user-log view',
            ],
            'sales' => [
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
            ],
            'admin' => [
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
            ],
            'operations' => [
                'customer view', 'customer create', 'customer edit', 'customer delete',
                'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
                'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
                'package view',
                'manifest view',
                'ops-movement view', 'ops-movement edit',
            ],
        ];

        foreach ($targets as $name => $perms) {
            $role = Role::where('name', $name)->where('guard_name', $guard)->first();
            if ($role) {
                $role->syncPermissions($perms);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $previous = [
            'superadmin' => [
                'dashboard view', 'master view',
                'user view', 'user create', 'user edit', 'user delete',
                'sales view', 'sales create', 'sales edit', 'sales delete',
                'customer view', 'customer create', 'customer edit', 'customer delete',
                'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
                'order view', 'order create', 'order edit', 'order delete',
                'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
                'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
                'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
                'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
                'package view', 'package create', 'package edit', 'package delete',
                'manifest view', 'manifest create', 'manifest edit',
                'ops-movement view', 'ops-movement edit',
            ],
            'sales' => [
                'dashboard view',
                'customer view', 'customer create', 'customer edit', 'customer delete',
                'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
                'order view', 'order create', 'order edit', 'order delete',
                'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
                'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
                'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
                'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
                'manifest view', 'manifest create', 'manifest edit',
            ],
            'admin' => [
                'customer view', 'customer create', 'customer edit', 'customer delete',
                'quotation view', 'quotation create', 'quotation edit', 'quotation delete',
                'order view', 'order create', 'order edit', 'order delete',
                'invoice view', 'invoice create', 'invoice edit', 'invoice delete',
                'receipt view', 'receipt create', 'receipt edit', 'receipt delete',
                'general-enquiry view', 'general-enquiry create', 'general-enquiry edit', 'general-enquiry delete',
                'private-enquiry view', 'private-enquiry create', 'private-enquiry edit', 'private-enquiry delete',
                'manifest view', 'manifest create', 'manifest edit',
            ],
            'operations' => [
                'ops-movement view', 'ops-movement edit',
            ],
        ];

        foreach ($previous as $name => $perms) {
            $role = Role::where('name', $name)->where('guard_name', $guard)->first();
            if ($role) {
                $role->syncPermissions($perms);
            }
        }

        Permission::whereIn('name', ['product-services view', 'product-services edit', 'user-log view'])
            ->where('guard_name', $guard)
            ->get()
            ->each(fn ($p) => $p->delete());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

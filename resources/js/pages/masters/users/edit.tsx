import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/user';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
import { resolveUserRoleLabel } from './create';
import { UserForm } from './form';
import { UserSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'User',
        href: index().url,
    },
];

interface EditUserProps {
    data: UserSchema;
    dataRole: [];
    dataBranch: [];
    dataCountry: [];
    dataSales: [];
    isSuperadmin: boolean;
    isAdmin: boolean;
    isSales: boolean;
    isOperations: boolean;
    isCustomer: boolean;
    isOfficial: boolean;
    scopeMode?: 'country' | 'branch';
}

export default function EditUser({
    data,
    dataRole,
    dataBranch,
    dataCountry,
    dataSales,
    isSuperadmin = false,
    isAdmin = false,
    isSales = false,
    isOperations = false,
    isCustomer = false,
    isOfficial = false,
    scopeMode = 'country',
}: EditUserProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const roleLabel = resolveUserRoleLabel({
        isSuperadmin,
        isAdmin,
        isSales,
        isOperations,
        isCustomer,
        isOfficial,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        {roleLabel} - Edit
                    </h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <UserForm
                        mode="edit"
                        initialData={data}
                        branches={dataBranch}
                        countries={dataCountry}
                        roles={dataRole}
                        salesList={dataSales}
                        onCancel={handleCancel}
                        isSuperadmin={isSuperadmin}
                        isAdmin={isAdmin}
                        isSales={isSales}
                        isOperations={isOperations}
                        isCustomer={isCustomer}
                        isOfficial={isOfficial}
                        scopeMode={scopeMode}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

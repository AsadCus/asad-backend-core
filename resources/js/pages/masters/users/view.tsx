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

interface ViewUserProps {
    data: UserSchema;
    dataRole: [];
    dataBranch: [];
    dataCountry: [];
    dataSales: [];
    isAdmin: boolean;
    isSales: boolean;
    isOperations: boolean;
    isCustomer: boolean;
    scopeMode?: 'country' | 'branch';
}

export default function ViewUser({
    data,
    dataRole,
    dataBranch,
    dataCountry,
    dataSales,
    isAdmin = false,
    isSales = false,
    isOperations = false,
    isCustomer = false,
    scopeMode = 'country',
}: ViewUserProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const roleLabel = resolveUserRoleLabel({
        isAdmin,
        isSales,
        isOperations,
        isCustomer,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        {roleLabel} - View
                    </h2>
                </div>
                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <UserForm
                        mode="view"
                        initialData={data}
                        branches={dataBranch}
                        countries={dataCountry}
                        roles={dataRole}
                        salesList={dataSales}
                        onCancel={handleCancel}
                        isOperations={isOperations}
                        scopeMode={scopeMode}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

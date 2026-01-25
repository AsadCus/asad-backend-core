import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index } from '@/routes/master/user';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useCallback } from 'react';
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

interface CreateUserProps {
    data: UserSchema[];
    dataRole: [];
    dataBranch: [];
    dataCountry: [];
    dataSales: [];
    isAdmin: boolean;
    isSales: boolean;
    isSupplier: boolean;
    isCustomer: boolean;
    submitUrl?: string;
}

export function resolveUserRoleLabel({
    isAdmin,
    isSales,
    isSupplier,
    isCustomer,
}: {
    isAdmin: boolean;
    isSales: boolean;
    isSupplier: boolean;
    isCustomer: boolean;
}) {
    if (isAdmin) return 'Admin';
    if (isSales) return 'Sales';
    if (isSupplier) return 'Supplier';
    if (isCustomer) return 'Customer';
    return 'User';
}

export default function CreateUser({
    dataRole,
    dataBranch,
    dataCountry,
    dataSales,
    isAdmin = false,
    isSales = false,
    isSupplier = false,
    isCustomer = false,
    submitUrl,
}: CreateUserProps) {
    const handleCancel = useCallback(() => {
        window.history.back();
    }, []);

    const roleLabel = resolveUserRoleLabel({
        isAdmin,
        isSales,
        isSupplier,
        isCustomer,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        {roleLabel} - Create
                    </h2>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <UserForm
                        mode="create"
                        branches={dataBranch}
                        countries={dataCountry}
                        roles={dataRole}
                        salesList={dataSales}
                        onCancel={handleCancel}
                        isAdmin={isAdmin}
                        isSales={isSales}
                        isSupplier={isSupplier}
                        isCustomer={isCustomer}
                        submitUrl={submitUrl}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

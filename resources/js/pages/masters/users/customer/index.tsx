import { type ActionType } from '@/components/action-column';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { index as userIndex } from '@/routes/master/user';
import { index, createQuotation } from '@/routes/master/user/customer';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { UserSchema } from '../schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'User',
        href: userIndex().url,
    },
    {
        title: 'Customer',
        href: index().url,
    },
];

const actions: ActionType[] = ['view', 'create-quotation'];

const columns: ColumnDef<UserSchema>[] = [
    createSelectColumn<UserSchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'role',
        header: 'Role',
        meta: { exportable: true },
        cell: ({ row }) => (
            <span className="capitalize">{row.getValue('role')}</span>
        ),
    },
];

interface CustomerProps {
    dataUser: UserSchema[];
}

export default function Customer({ dataUser }: CustomerProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Customer</h2>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={dataUser}
                        actions={actions}
                        onAction={(action, row) => {
                            const userId = row?.original.id;

                            if (userId !== undefined) {
                                if (action === 'view') {
                                    router.get(`/master/user/${userId}`);
                                } else if (action === 'create-quotation') {
                                    router.post(createQuotation(userId).url);
                                }
                            }
                        }}
                        initialState={{
                            columnVisibility: { id: false },
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

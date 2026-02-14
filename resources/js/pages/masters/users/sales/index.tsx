import { type ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { destroy, edit, show, index as userIndex } from '@/routes/master/user';
import { create, index } from '@/routes/master/user/sales';
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
        title: 'Salesperson',
        href: index().url,
    },
];

const actions: ActionType[] = ['add', 'view', 'edit', 'delete'];

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

interface SalesProps {
    dataUser: UserSchema[];
}

export default function Sales({ dataUser }: SalesProps) {
    const { confirm, ConfirmDialog } = useConfirmDialog();
    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Salesperson" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Salesperson</h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={dataUser}
                            actions={actions}
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                    return;
                                }

                                const userId = row?.original.id;

                                if (userId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(userId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(userId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Sales',
                                            message: `Are you sure you want to delete sales "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(userId).url,
                                                );
                                            },
                                        });
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
            <ConfirmDialog />
        </>
    );
}

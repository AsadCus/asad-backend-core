import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { create, destroy, edit, index, show } from '@/routes/sales';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Salesperson',
        href: index().url,
    },
];

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
        accessorKey: 'contact',
        header: 'Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'branch_id',
        header: 'Branch Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'branch_name',
        header: 'Branch',
        meta: { exportable: true },
    },
];

interface SalesProps {
    data: UserSchema[];
    dataBranch: [];
}

export default function Sales({ data, dataBranch }: SalesProps) {
    const { auth } = usePage<SharedData>().props;

    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    if (userPermissions.includes('sales create')) actions.push('add');
    if (userPermissions.includes('sales view')) actions.push('view');
    if (userPermissions.includes('sales edit')) actions.push('edit');
    if (userPermissions.includes('sales delete')) actions.push('delete');

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
                            data={data}
                            actions={actions}
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
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
                                columnVisibility: {
                                    id: false,
                                    contact: false,
                                    branch_id: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <ColumnFilter
                                    table={table}
                                    columnId="branch_id"
                                    title="Branch"
                                    options={dataBranch}
                                />
                            )}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

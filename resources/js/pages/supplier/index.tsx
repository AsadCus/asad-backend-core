import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    show,
    index as supplierIndex,
} from '@/routes/supplier';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier',
        href: supplierIndex().url,
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
        accessorKey: 'company_name',
        header: 'Company Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'address',
        header: 'Address',
        meta: { exportable: true },
        cell: ({ row }) => {
            const address = row.getValue('address');

            return (
                <div
                    dangerouslySetInnerHTML={{ __html: String(address) }}
                    className="max-w-xs break-words whitespace-pre-wrap"
                />
            );
        },
    },
    {
        accessorKey: 'commission',
        header: 'Commission',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.commission),
    },
    {
        accessorKey: 'total_cost_of_maid',
        header: 'Total Cost of Maid',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.total_cost_of_maid),
    },
];

interface SupplierProps {
    data: {
        suppliers: UserSchema[];
    };
}

export default function Supplier({ data }: SupplierProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    if (userPermissions.includes('supplier create')) actions.push('add');
    if (userPermissions.includes('supplier view')) actions.push('view');
    if (userPermissions.includes('supplier edit')) actions.push('edit');
    if (userPermissions.includes('supplier delete')) actions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Supplier" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Supplier</h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={data.suppliers}
                            actions={actions}
                            url={supplierIndex().url}
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
                                            title: 'Delete User',
                                            message: `Are you sure you want to delete "${row?.original.name}"?`,
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
                                },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

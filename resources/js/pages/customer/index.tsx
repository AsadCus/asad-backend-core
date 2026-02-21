import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatUserTime } from '@/lib/timezone';
import {
    create,
    destroy,
    disable,
    edit,
    enable,
    handle,
    index,
    show,
} from '@/routes/customer';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'List of Customers',
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
        accessorKey: 'customer_number',
        header: 'Customer No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'nric_number',
        header: 'NRIC No.',
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
        accessorKey: 'address',
        header: 'Address',
        meta: { exportable: true },
        cell: ({ row }) => {
            const address = row.getValue('address');
            return (
                <div
                    dangerouslySetInnerHTML={{ __html: String(address || '') }}
                    className="max-w-xs break-words whitespace-pre-wrap"
                />
            );
        },
    },
    {
        accessorKey: 'branch_id',
        header: 'Branch Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    // {
    //     accessorKey: 'branch_name',
    //     header: 'Branch',
    //     meta: { exportable: true },
    // },
    {
        accessorKey: 'handled_by',
        header: 'Handled By (ID)',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    // {
    //     accessorKey: 'handler_name',
    //     header: 'Handled By (Sales)',
    //     meta: { exportable: true },
    // },
    {
        accessorKey: 'last_login',
        header: 'Last Login',
        meta: { exportable: true },
        cell: ({ row }) => {
            const value = row.getValue('last_login');

            if (!value)
                return <span className="text-muted-foreground">Never</span>;

            return (
                <span className="text-base text-muted-foreground capitalize">
                    {formatUserTime(String(value))}
                </span>
            );
        },
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const isActive = row.getValue('is_active');
            return (
                <Badge
                    className="text-sm"
                    variant={isActive ? 'default' : 'destructive'}
                >
                    {isActive ? 'Active' : 'Inactive'}
                </Badge>
            );
        },
    },
];

interface CustomerProps {
    data: UserSchema[];
    dataBranch: OptionType[];
    dataSales: OptionType[];
}

export default function Customer({
    data,
    dataBranch,
    dataSales,
}: CustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Customers" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            List of Customers
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={data}
                            actions={actions}
                            addButtonText="Add New Customer"
                            getRowActions={(q) => {
                                const rowActions: ActionType[] = [];

                                if (userPermissions.includes('customer edit')) {
                                    if (q.is_active === false) {
                                        rowActions.push('enable-customer');
                                    } else {
                                        rowActions.push('disable-customer');
                                    }
                                }

                                return rowActions;
                            }}
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
                                    } else if (action == 'handle-customer') {
                                        router.put(handle(userId).url);
                                    } else if (action === 'enable-customer') {
                                        confirm({
                                            title: 'Enable Customer',
                                            message: `Are you sure you want to enable "${row?.original.name}"?`,
                                            confirmText: 'Enable',
                                            cancelText: 'Cancel',
                                            variant: 'primary',
                                            onConfirm: () => {
                                                router.put(enable(userId).url);
                                            },
                                        });
                                    } else if (action === 'disable-customer') {
                                        confirm({
                                            title: 'Disable Customer',
                                            message: `Are you sure you want to disable "${row?.original.name}"? They will not be able to login.`,
                                            confirmText: 'Disable',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.put(disable(userId).url);
                                            },
                                        });
                                    }
                                }
                            }}
                            initialState={{
                                columnVisibility: {
                                    id: false,
                                    nric_number: false,
                                    contact: false,
                                    address: false,
                                    branch_id: false,
                                    handled_by: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="branch_id"
                                        title="Branch"
                                        options={dataBranch}
                                    />
                                    {!auth.roles.includes('sales') && (
                                        <ColumnFilter
                                            table={table}
                                            columnId="handled_by"
                                            title="Sales"
                                            options={dataSales}
                                        />
                                    )}
                                </>
                            )}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

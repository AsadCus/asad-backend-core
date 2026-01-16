import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatUserTime } from '@/lib/timezone';
import { ages, experiences } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    handle,
    index,
    recommendMaidEdit,
    show,
} from '@/routes/customer';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { toast } from 'sonner';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Customer',
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
        accessorKey: 'age_preferences',
        header: 'Age Pref',
        meta: { exportable: true },
        cell: ({ row }) => {
            const ages = row.getValue('age_preferences');
            let values: string[] = [];

            if (Array.isArray(ages)) {
                values = ages;
            } else if (typeof ages === 'string') {
                values = ages.split(',').map((v) => v.trim());
            }

            return (
                <div className="flex flex-wrap gap-1">
                    {values.map((value: string, index: number) => (
                        <Badge
                            key={index}
                            variant="outline"
                            className="text-xs"
                        >
                            {value}
                        </Badge>
                    ))}
                </div>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'country_preferences',
        header: 'Country Pref',
        cell: ({ row }) => {
            const countries = row.getValue('country_preferences');
            let values: string[] = [];

            if (Array.isArray(countries)) {
                values = countries;
            } else if (typeof countries === 'string') {
                values = countries.split(',').map((v) => v.trim());
            }

            return (
                <div className="flex flex-wrap gap-1">
                    {values.map((value, index) => (
                        <Badge
                            key={index}
                            variant="secondary"
                            className="text-xs"
                        >
                            {value}
                        </Badge>
                    ))}
                </div>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'experience_preferences',
        header: 'Experience Pref',
        meta: { exportable: true },
        cell: ({ row }) => {
            const experiences = row.getValue('experience_preferences');
            let values: string[] = [];

            if (Array.isArray(experiences)) {
                values = experiences;
            } else if (typeof experiences === 'string') {
                values = experiences.split(',').map((v) => v.trim());
            }

            return (
                <div className="flex flex-wrap gap-1">
                    {values.map((value: string, index: number) => (
                        <Badge
                            key={index}
                            variant="outline"
                            className="text-xs"
                        >
                            {value.includes('year') ? value : `${value} year`}
                        </Badge>
                    ))}
                </div>
            );
        },
        filterFn: 'includesValue',
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
    {
        accessorKey: 'handled_by',
        header: 'Handled By (ID)',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'handler_name',
        header: 'Handled By (Sales)',
        meta: { exportable: true },
    },
    {
        accessorKey: 'last_login',
        header: 'Last Login',
        meta: { exportable: true },
        cell: ({ row }) => {
            const value = row.getValue('last_login');

            if (!value)
                return <span className="text-muted-foreground">Never</span>;

            return (
                <span className="text-sm text-muted-foreground capitalize">
                    {formatUserTime(String(value))}
                </span>
            );
        },
    },
];

interface CustomerProps {
    data: UserSchema[];
    dataBranch: [];
    dataSales: [];
    dataCountry: [];
}

export default function Customer({
    data,
    dataBranch,
    dataSales,
    dataCountry,
}: CustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('quotation create'))
        actions.push('quotation-create');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit'))
        actions.push('edit', 'handle-customer', 'recommend-maid');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Customer" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Customer</h2>
                    </div>
                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
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
                                const customerId = row?.original.customer_id;

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
                                    } else if (action == 'recommend-maid') {
                                        router.get(
                                            recommendMaidEdit(userId).url,
                                        );
                                    } else if (action === 'quotation-create') {
                                        if (!customerId) {
                                            toast.error(
                                                'Customer ID not found. Please contact support.',
                                            );
                                            return;
                                        }

                                        toast.loading(
                                            'Redirecting to quotation...',
                                        );

                                        router.visit(
                                            `/quotation/create?customer_id=${customerId}`,
                                            {
                                                method: 'get',
                                                preserveState: false,
                                                preserveScroll: false,
                                                onSuccess: () => {
                                                    toast.dismiss();
                                                    toast.success(
                                                        'Ready to create quotation for customer.',
                                                    );
                                                },
                                                onError: (errors) => {
                                                    console.error(errors);
                                                    toast.dismiss();
                                                    toast.error(
                                                        'Failed to navigate to quotation.',
                                                    );
                                                },
                                            },
                                        );
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
                                    <ColumnFilter
                                        table={table}
                                        columnId="age_preferences"
                                        title="Age"
                                        options={ages}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="country_preferences"
                                        title="Country"
                                        options={dataCountry}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="experience_preferences"
                                        title="Experience"
                                        options={experiences}
                                    />
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

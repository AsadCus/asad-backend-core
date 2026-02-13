import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef, Row } from '@tanstack/react-table';
import { useState } from 'react';
import CustomerConfirmationForm from '../enquiries/customer-confirmation-form';
import { type CustomerGroupSchema } from '../enquiries/schema';
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
    {
        accessorKey: 'is_active',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const isActive = row.getValue('is_active');
            return (
                <Badge
                    className="text-xs"
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
    dataBranch: [];
    dataSales: [];
    dataGroups: CustomerGroupSchema[];
}

const groupColumns: ColumnDef<CustomerGroupSchema>[] = [
    createSelectColumn<CustomerGroupSchema>(),
    {
        accessorKey: 'id',
        header: 'Group ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'leader_name',
        header: 'Group Leader',
        meta: { exportable: true },
    },
    {
        accessorKey: 'leader_email',
        header: 'Leader Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'leader_contact',
        header: 'Leader Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'leader_customer_number',
        header: 'Customer No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'member_count',
        header: 'Members',
        meta: { exportable: true },
        cell: ({ row }) => (
            <Badge variant="secondary" className="text-xs">
                {row.original.member_count}
            </Badge>
        ),
    },
    {
        accessorKey: 'enquiry_type',
        header: 'Enquiry Type',
        meta: { exportable: true },
        cell: ({ row }) => {
            const type = row.original.enquiry_type;
            if (!type) return <span className="text-muted-foreground">-</span>;

            const typeColors: Record<string, string> = {
                General:
                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                Private:
                    'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            };

            return (
                <Badge
                    className={`${typeColors[type] ?? ''} rounded-full px-3 py-1 text-sm`}
                >
                    {type}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'enquiry_status',
        header: 'Enquiry Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.enquiry_status;
            if (!status)
                return <span className="text-muted-foreground">-</span>;

            return (
                <Badge
                    variant="outline"
                    className="rounded-full px-3 py-1 text-sm"
                >
                    {status}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
];

const renderGroupSubComponent = (row: Row<CustomerGroupSchema>) => {
    const members = row.original.members;

    return (
        <div className="bg-muted/30 px-8 py-4">
            <h4 className="mb-3 text-sm font-semibold">Group Members</h4>
            <div className="overflow-hidden rounded-md border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/50">
                            <th className="p-2 text-left font-medium">Role</th>
                            <th className="p-2 text-left font-medium">Name</th>
                            <th className="p-2 text-left font-medium">Email</th>
                            <th className="p-2 text-left font-medium">
                                Contact
                            </th>
                            <th className="p-2 text-left font-medium">
                                Customer No.
                            </th>
                            <th className="p-2 text-left font-medium">
                                NRIC No.
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <tr
                                key={member.id}
                                className="border-b last:border-0"
                            >
                                <td className="p-2">
                                    <Badge
                                        variant={
                                            member.is_leader
                                                ? 'default'
                                                : 'secondary'
                                        }
                                        className="text-xs"
                                    >
                                        {member.is_leader ? 'Leader' : 'Member'}
                                    </Badge>
                                </td>
                                <td className="p-2">{member.name}</td>
                                <td className="p-2">{member.email}</td>
                                <td className="p-2">{member.contact}</td>
                                <td className="p-2">
                                    {member.customer_number}
                                </td>
                                <td className="p-2">{member.nric_number}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default function Customer({
    data,
    dataBranch,
    dataSales,
    dataGroups,
}: CustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit'))
        actions.push('edit', 'handle-customer');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    // Standalone Customer Group creation dialog
    const [createGroupOpen, setCreateGroupOpen] = useState(false);

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

                    <Tabs defaultValue="customers" className="w-full">
                        <TabsList className="grid w-full max-w-md grid-cols-2">
                            <TabsTrigger value="customers">
                                All Customers
                            </TabsTrigger>
                            <TabsTrigger value="groups">
                                Customer Groups ({dataGroups.length})
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="customers">
                            <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={columns}
                                    data={data}
                                    actions={actions}
                                    addButtonText="Add New Customer"
                                    getRowActions={(q) => {
                                        const rowActions: ActionType[] = [];

                                        if (
                                            userPermissions.includes(
                                                'customer edit',
                                            )
                                        ) {
                                            if (q.is_active === false) {
                                                rowActions.push(
                                                    'enable-customer',
                                                );
                                            } else {
                                                rowActions.push(
                                                    'disable-customer',
                                                );
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
                                        const customerId =
                                            row?.original.customer_id;

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
                                            } else if (
                                                action == 'handle-customer'
                                            ) {
                                                router.put(handle(userId).url);
                                            } else if (
                                                action === 'enable-customer'
                                            ) {
                                                confirm({
                                                    title: 'Enable Customer',
                                                    message: `Are you sure you want to enable "${row?.original.name}"?`,
                                                    confirmText: 'Enable',
                                                    cancelText: 'Cancel',
                                                    variant: 'primary',
                                                    onConfirm: () => {
                                                        router.put(
                                                            enable(userId).url,
                                                        );
                                                    },
                                                });
                                            } else if (
                                                action === 'disable-customer'
                                            ) {
                                                confirm({
                                                    title: 'Disable Customer',
                                                    message: `Are you sure you want to disable "${row?.original.name}"? They will not be able to login.`,
                                                    confirmText: 'Disable',
                                                    cancelText: 'Cancel',
                                                    onConfirm: () => {
                                                        router.put(
                                                            disable(userId).url,
                                                        );
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
                        </TabsContent>

                        <TabsContent value="groups">
                            <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                                <DataTable
                                    columns={groupColumns}
                                    data={dataGroups}
                                    actions={actions}
                                    addButtonText="Create Customer Group"
                                    enableExpand
                                    renderSubComponent={renderGroupSubComponent}
                                    url={index().url}
                                    onAction={(action) => {
                                        if (action === 'add') {
                                            setCreateGroupOpen(true);
                                        }
                                    }}
                                    initialState={{
                                        columnVisibility: {
                                            id: false,
                                        },
                                    }}
                                    renderFilter={(table) => (
                                        <ColumnFilter
                                            table={table}
                                            columnId="enquiry_type"
                                            title="Enquiry Type"
                                            options={[
                                                {
                                                    label: 'General',
                                                    value: 'General',
                                                },
                                                {
                                                    label: 'Private',
                                                    value: 'Private',
                                                },
                                            ]}
                                        />
                                    )}
                                />
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>
            </AppLayout>
            <ConfirmDialog />

            {/* Standalone Customer Group Creation Dialog */}
            <Dialog open={createGroupOpen} onOpenChange={setCreateGroupOpen}>
                <DialogContent className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <DialogTitle>Create Customer Group</DialogTitle>
                        <DialogDescription>
                            Create a new customer group with a leader and
                            participants.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        className="h-full w-full flex-1 overflow-y-auto"
                        style={{
                            scrollbarWidth: 'none',
                            msOverflowStyle: 'none',
                        }}
                    >
                        <CustomerConfirmationForm
                            onSuccess={() => {
                                setCreateGroupOpen(false);
                                router.reload();
                            }}
                            onCancel={() => setCreateGroupOpen(false)}
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

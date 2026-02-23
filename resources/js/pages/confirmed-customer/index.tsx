import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
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
import AppLayout from '@/layouts/app-layout';
import { index as confirmedCustomerIndex } from '@/routes/confirmed-customer';
import { generateEditLink, show as showGroup } from '@/routes/customer-groups';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef, Row } from '@tanstack/react-table';
import { useState } from 'react';
import CustomerConfirmationForm from '../customer/form';
import type {
    CustomerGroupDatatableSchema,
    CustomerGroupFormSchema,
} from '../customer/schema';
import { statusColors, typeColors } from '../enquiries/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Confirmed Customers',
        href: confirmedCustomerIndex().url,
    },
];

const groupColumns: ColumnDef<CustomerGroupDatatableSchema>[] = [
    createSelectColumn<CustomerGroupDatatableSchema>(),
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
            <Badge variant="secondary" className="text-sm">
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

            return (
                <Badge
                    className={`${typeColors[type] ?? ''} rounded-full px-3 py-1 text-base`}
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

            const color = statusColors[status] ?? '';

            return (
                <Badge className={`${color} rounded-full px-3 py-1 text-base`}>
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

const renderGroupSubComponent = (row: Row<CustomerGroupDatatableSchema>) => {
    const members = row.original.members;

    return (
        <div className="bg-muted/30 px-8 py-4">
            <h4 className="mb-3 text-base font-semibold">Group Members</h4>
            <div className="overflow-hidden rounded-md border">
                <table className="w-full text-base">
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
                                        className="text-sm"
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

interface ConfirmedCustomerProps {
    dataGroups: CustomerGroupDatatableSchema[];
    packageOptions?: OptionType[];
}

export default function ConfirmedCustomerIndex({
    dataGroups,
    packageOptions = [],
}: ConfirmedCustomerProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];
    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');

    // Standalone Customer Group creation dialog
    const [createGroupOpen, setCreateGroupOpen] = useState(false);

    // Group view/edit dialog
    const [groupDialogOpen, setGroupDialogOpen] = useState(false);
    const [groupDialogMode, setGroupDialogMode] = useState<
        'view' | 'edit' | 'create'
    >('view');
    const [groupDialogData, setGroupDialogData] =
        useState<CustomerGroupFormSchema | null>(null);
    const [isLoadingGroup, setIsLoadingGroup] = useState(false);

    const handleOpenGroupDialog = async (
        groupId: number,
        mode: 'view' | 'edit',
    ) => {
        setGroupDialogMode(mode);
        setGroupDialogOpen(true);
        setIsLoadingGroup(true);
        setGroupDialogData(null);

        try {
            const response = await fetch(showGroup(groupId).url);
            if (!response.ok) throw new Error('Failed to fetch group data');
            const data = await response.json();
            setGroupDialogData(data);
        } catch (error) {
            console.error('Failed to fetch group details:', error);
        } finally {
            setIsLoadingGroup(false);
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Confirmed Customers" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Confirmed Customers
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={groupColumns}
                            data={dataGroups}
                            actions={actions}
                            addButtonText="Create Customer Confirmation"
                            enableExpand
                            getRowActions={() => ['copy-public-edit-link']}
                            renderSubComponent={renderGroupSubComponent}
                            url={confirmedCustomerIndex().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    setCreateGroupOpen(true);
                                }

                                const groupId = row?.original.id;
                                if (groupId !== undefined) {
                                    if (action === 'view') {
                                        handleOpenGroupDialog(groupId, 'view');
                                    } else if (action === 'edit') {
                                        handleOpenGroupDialog(groupId, 'edit');
                                    } else if (
                                        action === 'copy-public-edit-link'
                                    ) {
                                        fetch(generateEditLink(groupId).url)
                                            .then((res) => res.json())
                                            .then((data: { url: string }) => {
                                                navigator.clipboard.writeText(
                                                    data.url,
                                                );
                                                alert(
                                                    'Public edit link copied to clipboard!',
                                                );
                                            })
                                            .catch(() => {
                                                alert(
                                                    'Failed to generate public link.',
                                                );
                                            });
                                    }
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                if (row.id) {
                                    handleOpenGroupDialog(row.id, 'view');
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
                </div>
            </AppLayout>

            {/* Standalone Customer Group Creation Dialog */}
            <Dialog open={createGroupOpen} onOpenChange={setCreateGroupOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <DialogTitle>Customer Confirmation Form</DialogTitle>
                        <DialogDescription className="hidden">
                            Create a new customer group with a leader and
                            participants.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto">
                        <CustomerConfirmationForm
                            packageOptions={packageOptions}
                            onSuccess={() => {
                                setCreateGroupOpen(false);
                                router.reload();
                            }}
                            onCancel={() => setCreateGroupOpen(false)}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            {/* Confirmed Customer View/Edit Dialog */}
            <Dialog open={groupDialogOpen} onOpenChange={setGroupDialogOpen}>
                <DialogContent className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <DialogTitle>
                            {groupDialogMode === 'view'
                                ? 'View Confirmed Customer'
                                : 'Edit Confirmed Customer'}
                        </DialogTitle>
                        <DialogDescription className="hidden">
                            {groupDialogMode === 'view'
                                ? 'View confirmed customer details.'
                                : 'Edit confirmed customer details.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto">
                        {isLoadingGroup && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading confirmed customer details...
                            </div>
                        )}
                        {!isLoadingGroup && groupDialogData && (
                            <CustomerConfirmationForm
                                mode={groupDialogMode}
                                packageOptions={packageOptions}
                                initialData={groupDialogData}
                                onSuccess={() => {
                                    setGroupDialogOpen(false);
                                    router.reload();
                                }}
                                onCancel={() => setGroupDialogOpen(false)}
                            />
                        )}
                        {!isLoadingGroup && !groupDialogData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Failed to load confirmed customer details.
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

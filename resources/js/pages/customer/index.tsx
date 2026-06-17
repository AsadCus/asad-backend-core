import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { CustomExport } from '@/components/data-table-export';
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
    index,
    show,
} from '@/routes/customer';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { useState } from 'react';
import CustomerHistoryDialog from '../customer-history/components/customer-history-dialog';
import { UserSchema } from '../masters/users/schema';
import {
    CustomerImportDialog,
    generateCustomerImportTemplate,
} from './import-dialog';

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
            const address = String(row.getValue('address') ?? '');

            return (
                <div className="max-w-xs break-words whitespace-pre-line">
                    {address}
                </div>
            );
        },
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
}

export default function Customer({ data }: CustomerProps) {
    const { auth, features } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const customerHistoryEnabled = Boolean(features?.customer_history);
    const actions: ActionType[] = [];

    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) {
        actions.push('view');
        if (customerHistoryEnabled) {
            actions.push('view-customer-history');
        }
    }
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const [importOpen, setImportOpen] = useState(false);
    const [historyCustomerId, setHistoryCustomerId] = useState<
        number | undefined
    >();
    const [historyCustomerName, setHistoryCustomerName] = useState('');
    const [historyCustomerEmail, setHistoryCustomerEmail] = useState('');
    const [historyCustomerContact, setHistoryCustomerContact] = useState('');
    const [historyCustomerAddress, setHistoryCustomerAddress] = useState('');
    const [historyOpen, setHistoryOpen] = useState(false);

    const customExports: CustomExport[] = [
        {
            label: 'Download Template',
            icon: Download,
            onClick: generateCustomerImportTemplate,
        },
    ];

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Customers" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex gap-2">
                            <h2 className="text-lg font-semibold">
                                List of Customers
                            </h2>
                            <Badge variant="default" className="py-0 text-base">
                                Total Customer: {data.length}
                            </Badge>
                        </div>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={data}
                            actions={actions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            addButtonText="Add New Customer"
                            showImport={userPermissions.includes(
                                'customer create',
                            )}
                            onImport={() => setImportOpen(true)}
                            customExports={customExports}
                            exportOptions={['excel', 'pdf']}
                            getRowActions={(q) => {
                                const rowActions: ActionType[] = [];

                                if (userPermissions.includes('customer edit')) {
                                    if (q.is_active === false) {
                                        rowActions.push('enable-customer');
                                    } else {
                                        // rowActions.push('disable-customer');
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
                                    } else if (
                                        action === 'view-customer-history'
                                    ) {
                                        setHistoryCustomerId(
                                            row?.original.customer_id ??
                                                undefined,
                                        );
                                        setHistoryCustomerName(
                                            row?.original.name ?? '',
                                        );
                                        setHistoryCustomerEmail(
                                            row?.original.email ?? '',
                                        );
                                        setHistoryCustomerContact(
                                            row?.original.contact ?? '',
                                        );
                                        setHistoryCustomerAddress(
                                            row?.original.address ?? '',
                                        );
                                        setHistoryOpen(true);
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
                            onRowDoubleClick={(row) => {
                                if (row.id) {
                                    router.get(edit(row.id).url);
                                }
                            }}
                            initialState={{
                                columnVisibility: {
                                    id: false,
                                    customer_number: false,
                                    nric_number: false,
                                    address: false,
                                    last_login: false,
                                    is_active: false,
                                },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
            <CustomerImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
            />
            <CustomerHistoryDialog
                isOpen={historyOpen}
                onClose={() => setHistoryOpen(false)}
                customerId={historyCustomerId}
                customerName={historyCustomerName}
                customerEmail={historyCustomerEmail}
                customerContact={historyCustomerContact}
                customerAddress={historyCustomerAddress}
            />
        </>
    );
}

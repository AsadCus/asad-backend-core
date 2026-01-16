import { ActionType } from '@/components/action-column';
import { ChartAreaInteractive } from '@/components/chart-area-interactive';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { SectionCards } from '@/components/section-cards';
import { createSelectColumn } from '@/components/select-column';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatUserTime } from '@/lib/timezone';
import { dashboard } from '@/routes';
import {
    create as customerCreate,
    destroy as customerDestroy,
    edit as customerEdit,
    handle as customerHandle,
    index as customerIndex,
    show as customerShow,
    recommendMaidEdit,
} from '@/routes/customer';
import {
    create as maidCreate,
    destroy as maidDestroy,
    edit as maidEdit,
    show as maidShow,
} from '@/routes/maid';
import {
    SharedData,
    ValueNumberOptionType,
    type BreadcrumbItem,
} from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { MaidCardList } from './maid/card-list';
import { MaidSchema } from './maid/schema';
import { UserSchema } from './masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface DashboardProps {
    data: {
        widgets?: { title: string; value: string | number }[];
        customers?: UserSchema[];
        maids?: MaidSchema[];
        nationality: [];
        religion: [];
        educationLevel: [];
        supplier: [];
        chartData?: {
            customers: {
                '90d': { date: string; count: number; label: string }[];
                '30d': { date: string; count: number; label: string }[];
                '7d': { date: string; count: number; label: string }[];
            };
            maids: {
                '90d': { date: string; count: number; label: string }[];
                '30d': { date: string; count: number; label: string }[];
                '7d': { date: string; count: number; label: string }[];
            };
        };
        misc?: {
            nationalities: ValueNumberOptionType[];
            religions: ValueNumberOptionType[];
            education_levels: ValueNumberOptionType[];
            suppliers: ValueNumberOptionType[];
        };
    };
}

export default function Dashboard({ data }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    // roles
    const isAdmin = auth.roles.includes('admin');
    const isSales = auth.roles.includes('sales');
    const isCustomer = auth.roles.includes('customer');

    // actions
    const actions: ActionType[] = ['handle-customer', 'recommend-maid'];
    if (userPermissions.includes('customer create')) actions.push('add');
    if (userPermissions.includes('customer view')) actions.push('view');
    if (userPermissions.includes('customer edit')) actions.push('edit');
    if (userPermissions.includes('customer delete')) actions.push('delete');

    const actionsForCustomer: ActionType[] = [];
    if (userPermissions.includes('maid create')) actionsForCustomer.push('add');
    if (userPermissions.includes('maid view'))
        actionsForCustomer.push('preview');
    if (userPermissions.includes('maid edit')) actionsForCustomer.push('edit');
    if (userPermissions.includes('maid delete'))
        actionsForCustomer.push('delete');

    // columns
    const customerColumns: ColumnDef<UserSchema>[] = [
        createSelectColumn<UserSchema>(),
        { accessorKey: 'name', header: 'Name' },
        { accessorKey: 'email', header: 'Email' },
        { accessorKey: 'contact', header: 'Contact' },
        {
            accessorKey: 'handler_name',
            header: 'Sales',
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

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Dashboard" />
                <div className="@container/main flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    {/* Widgets */}
                    {isAdmin && data.widgets && (
                        <>
                            <SectionCards widgets={data.widgets} />
                            <ChartAreaInteractive chartData={data.chartData} />
                        </>
                    )}

                    {/* Customer DataTable */}
                    {(isAdmin || isSales) && data.customers && (
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-lg font-semibold">
                                    Recent Customers
                                </h2>
                                <Button asChild variant="outline">
                                    <Link href={customerIndex().url}>
                                        View All
                                    </Link>
                                </Button>
                            </div>
                            <DataTable
                                columns={customerColumns}
                                data={data.customers}
                                actions={actions}
                                url={customerIndex().url}
                                onAction={(action, row) => {
                                    if (action === 'add') {
                                        router.get(customerCreate().url);
                                    }

                                    const userId = row?.original.id;

                                    if (userId !== undefined) {
                                        if (action === 'view') {
                                            router.get(
                                                customerShow(userId).url,
                                            );
                                        } else if (action === 'edit') {
                                            router.get(
                                                customerEdit(userId).url,
                                            );
                                        } else if (action === 'delete') {
                                            confirm({
                                                title: 'Delete User',
                                                message: `Are you sure you want to delete "${row?.original.name}"?`,
                                                confirmText: 'Delete',
                                                cancelText: 'Cancel',
                                                onConfirm: () => {
                                                    router.delete(
                                                        customerDestroy(userId)
                                                            .url,
                                                    );
                                                },
                                            });
                                        } else if (
                                            action == 'handle-customer'
                                        ) {
                                            router.put(
                                                customerHandle(userId).url,
                                            );
                                        } else if (action == 'recommend-maid') {
                                            router.get(
                                                recommendMaidEdit(userId).url,
                                            );
                                        }
                                    }
                                }}
                                initialState={{
                                    pagination: { pageIndex: 0, pageSize: 10 },
                                }}
                            />
                        </div>
                    )}

                    {/* Maid Card List */}
                    {isCustomer && data.maids && (
                        <div
                            className={`relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-1 py-3 md:min-h-min dark:border-sidebar-border`}
                        >
                            <div className="flex items-center justify-between px-2 pb-2">
                                <h2 className="text-lg font-semibold">
                                    Maid Profile
                                </h2>
                            </div>
                            <MaidCardList
                                data={data.maids}
                                dataNationality={data.nationality}
                                dataReligion={data.religion}
                                dataEducationLevel={data.educationLevel}
                                misc={data.misc}
                                actions={actionsForCustomer}
                                onAction={(action, row) => {
                                    if (action === 'add') {
                                        router.get(maidCreate().url);
                                    }

                                    const maidId = row?.id;

                                    if (maidId !== undefined) {
                                        if (action === 'view') {
                                            router.get(maidShow(maidId).url);
                                        } else if (action === 'edit') {
                                            router.get(maidEdit(maidId).url);
                                        } else if (action === 'delete') {
                                            confirm({
                                                title: 'Delete User',
                                                message: `Are you sure you want to delete maid "${row?.name}"?`,
                                                confirmText: 'Delete',
                                                cancelText: 'Cancel',
                                                onConfirm: () => {
                                                    router.delete(
                                                        maidDestroy(maidId).url,
                                                    );
                                                },
                                            });
                                        }
                                    }
                                }}
                            />
                        </div>
                    )}
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

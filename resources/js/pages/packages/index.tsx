import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { create, destroy, edit, index, show } from '@/routes/packages';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { packageStatusColors, packageStatusLabels } from './schema';

interface PackageDataTableSchema {
    id: number;
    group_number: string;
    name: string;
    status: string;
    launched: boolean;
    airline: string | null;
    departure_date: string | null;
    arrival_date: string | null;
    total_seats: number | null;
    seats_left: number | null;
    price_quad: number;
    manifests_count: number;
    created_at: string;
}

interface PackagesProps {
    data: {
        packagesForDatatable: PackageDataTableSchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Packages',
        href: index().url,
    },
];

const columns: ColumnDef<PackageDataTableSchema>[] = [
    createSelectColumn<PackageDataTableSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'group_number',
        header: 'Group No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Package Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original
                .status as keyof typeof packageStatusColors;
            const color = packageStatusColors[status];
            const label = packageStatusLabels[status];
            return (
                <span
                    className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold ${color}`}
                >
                    {label}
                </span>
            );
        },
    },
    {
        accessorKey: 'airline',
        header: 'Airline',
        meta: { exportable: true },
        cell: ({ row }) => row.original.airline || '-',
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure',
        meta: { exportable: true },
        cell: ({ row }) => row.original.departure_date,
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'arrival_date',
        header: 'Arrival',
        meta: { exportable: true },
        cell: ({ row }) => row.original.arrival_date,
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'total_seats',
        header: 'Seats',
        meta: { exportable: true },
        cell: ({ row }) => {
            const total = row.original.total_seats;
            const left = row.original.seats_left;
            if (total === null) return '-';
            return `${left ?? 0} / ${total}`;
        },
    },
    {
        accessorKey: 'price_quad',
        header: 'Quad Price',
        meta: { exportable: true },
        cell: ({ row }) => `$${Number(row.original.price_quad).toFixed(2)}`,
    },
    {
        accessorKey: 'manifests_count',
        header: 'Manifests',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
];

export default function PackagesIndex({ data }: PackagesProps) {
    const actions: ActionType[] = ['add', 'view', 'edit', 'delete'];
    const { packagesForDatatable } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Packages" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Packages</h2>
                    </div>

                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={packagesForDatatable}
                            actions={actions}
                            addButtonText="Create New Package"
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const packageId = row?.original.id;

                                if (packageId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(packageId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(packageId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Package',
                                            message: `Are you sure you want to delete package "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(packageId).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            initialState={{
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    arrival_date: false,
                                    created_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <DateRangeFilter
                                        table={table}
                                        columnId="departure_date"
                                        title="Departure Date"
                                        quickDate={true}
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

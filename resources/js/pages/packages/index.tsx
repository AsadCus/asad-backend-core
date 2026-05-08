import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy,
    download,
    edit,
    index,
    show,
} from '@/routes/packages';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { packageStatusColors, packageStatusLabels } from './schema';

interface PackageDataTableSchema {
    id: number;
    package_number: string;
    name: string;
    status: string;
    launched: boolean;
    departure_date: string | null;
    return_date: string | null;
    total_seats: number | null;
    occupied_seats: number;
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
        accessorKey: 'package_number',
        header: 'Package No.',
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
            const normalizedStatus = String(row.original.status ?? '')
                .trim()
                .toLowerCase();

            if (!normalizedStatus) {
                return <span className="text-muted-foreground">-</span>;
            }

            return (
                <Badge
                    className={`${packageStatusColors[normalizedStatus] ?? 'bg-gray-100 text-gray-800'} rounded-full px-3 py-1 text-base`}
                >
                    {packageStatusLabels[normalizedStatus] ?? normalizedStatus}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure Date',
        meta: { exportable: true },
        cell: ({ row }) => row.original.departure_date,
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'return_date',
        header: 'Return Date',
        meta: { exportable: true },
        cell: ({ row }) => row.original.return_date,
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'total_seats',
        header: 'Available Seats',
        meta: { exportable: true },
        cell: ({ row }) => {
            const total = row.original.total_seats;
            const occupied = Number(row.original.occupied_seats ?? 0);
            if (total === null) return '-';

            const left = Math.max(0, total - occupied);

            return `${left ?? 0} / ${total}`;
        },
    },
    {
        accessorKey: 'price_quad',
        header: 'Quad Price',
        meta: { exportable: true },
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
    const actions: ActionType[] = ['add', 'view', 'edit', 'download', 'delete'];
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

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={packagesForDatatable}
                            actions={actions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
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
                                    } else if (action === 'download') {
                                        window.open(
                                            download(packageId).url,
                                            '_blank',
                                        );
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
                            onRowDoubleClick={(row) => {
                                if (row.id) {
                                    router.get(edit(row.id).url);
                                }
                            }}
                            initialState={{
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    return_date: false,
                                    price_quad: false,
                                    manifests_count: false,
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

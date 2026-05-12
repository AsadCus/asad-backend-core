import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/ops-movements';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { packageStatusColors, packageStatusLabels } from '../packages/schema';

interface OpsMovementDataTableSchema {
    id: number;
    package_number: string;
    name: string;
    status: string;
    launched: boolean;
    departure_date: string | null;
    return_date: string | null;
    total_seats: number | null;
    seats_left: number | null;
    country_name: string | null;
    visa_type: string | null;
    vehicle_type: string | null;
    ticket_type: string | null;
    total_members: number;
    manifests_count: number;
    accommodations: {
        location: string;
        hotel_name: string;
        type_of_meal: string | null;
        check_in: string | null;
        check_out: string | null;
    }[];
}

interface OpsMovementsProps {
    data: {
        opsMovementsForDatatable: OpsMovementDataTableSchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Ops Movements',
        href: index().url,
    },
];

const columns: ColumnDef<OpsMovementDataTableSchema>[] = [
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
        header: 'Package',
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
    },
    {
        accessorKey: 'country_name',
        header: 'Country',
        meta: { exportable: true },
        filterFn: 'includesValue',
        cell: ({ row }) =>
            row.original.country_name ?? (
                <span className="text-muted-foreground">-</span>
            ),
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure Date',
        meta: { exportable: true },
        cell: ({ row }) => row.original.departure_date || '-',
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'return_date',
        header: 'Return Date',
        meta: { exportable: true },
        cell: ({ row }) => row.original.return_date || '-',
    },
    {
        accessorKey: 'total_seats',
        header: 'Available Seats',
        meta: { exportable: true },
        cell: ({ row }) => {
            const total = row.original.total_seats;
            const left = row.original.seats_left;
            if (total === null) return '-';
            return `${left ?? 0} / ${total}`;
        },
    },
    {
        accessorKey: 'visa_type',
        header: 'Visa',
        meta: { exportable: true },
        cell: ({ row }) => row.original.visa_type || '-',
    },
    {
        accessorKey: 'vehicle_type',
        header: 'Vehicle',
        meta: { exportable: true },
        cell: ({ row }) => row.original.vehicle_type || '-',
    },
    {
        accessorKey: 'total_members',
        header: 'Total Pax',
        meta: { exportable: true },
    },
    // {
    //     accessorKey: 'manifests_count',
    //     header: 'Manifests',
    //     meta: { exportable: true },
    // },
    {
        id: 'accommodations_summary',
        header: 'Hotels',
        meta: { exportable: true },
        cell: ({ row }) => {
            const accs = row.original.accommodations;
            if (!accs || accs.length === 0) return '-';
            return accs.map((a) => `${a.location}: ${a.hotel_name}`).join(', ');
        },
    },
];

export default function OpsMovementsIndex({ data }: OpsMovementsProps) {
    const actions: ActionType[] = ['view'];
    const { opsMovementsForDatatable } = data;

    const countryOptions = [
        ...new Set(
            opsMovementsForDatatable
                .map((r) => r.country_name)
                .filter((c): c is string => Boolean(c)),
        ),
    ].map((c) => ({ value: c, label: c }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ops Movements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Ops Movements</h2>
                </div>

                <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={opsMovementsForDatatable}
                        actions={actions}
                        searchFilterMode="outside"
                        columnFilterMode="outside"
                        url={index().url}
                        onAction={(action, row) => {
                            const movementId = row?.original.id;

                            if (movementId !== undefined) {
                                if (action === 'view') {
                                    router.get(show(movementId).url);
                                }
                            }
                        }}
                        onRowDoubleClick={(row) => {
                            if (row.id) {
                                router.get(show(row.id).url);
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
                                accommodations_summary: false,
                                visa_type: false,
                                vehicle_type: false,
                                ticket_type: false,
                            },
                        }}
                        renderFilter={(table) => (
                            <>
                                <ColumnFilter
                                    table={table}
                                    columnId="country_name"
                                    title="Country"
                                    options={countryOptions}
                                />
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
    );
}

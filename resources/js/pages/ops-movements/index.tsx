import { ActionType } from '@/components/action-column';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/ops-movements';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';

interface OpsMovementDataTableSchema {
    id: number;
    group_number: string;
    name: string;
    status: string;
    launched: boolean;
    airline: string | null;
    pnr: string | null;
    departure_date: string | null;
    arrival_date: string | null;
    total_seats: number | null;
    seats_left: number | null;
    visa_type: string | null;
    vehicle_type: string | null;
    ticket_type: string | null;
    total_travelers: number;
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
        accessorKey: 'group_number',
        header: 'Group No.',
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
        cell: ({ row }) => (
            <span
                className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold ${
                    row.original.status === 'open'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                }`}
            >
                {row.original.status === 'open' ? 'Open' : 'Closed'}
            </span>
        ),
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
        filterFn: 'dateRangeFilter',
        cell: ({ row }) => row.original.departure_date || '-',
    },
    {
        accessorKey: 'arrival_date',
        header: 'Arrival',
        meta: { exportable: true },
        cell: ({ row }) => row.original.arrival_date || '-',
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
        accessorKey: 'total_travelers',
        header: 'Total Pax',
        meta: { exportable: true },
    },
    {
        accessorKey: 'manifests_count',
        header: 'Manifests',
        meta: { exportable: true },
    },
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ops Movements" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Ops Movements</h2>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={opsMovementsForDatatable}
                        actions={actions}
                        url={index().url}
                        onAction={(action, row) => {
                            const movementId = row?.original.id;

                            if (movementId !== undefined) {
                                if (action === 'view') {
                                    router.get(show(movementId).url);
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
                                ticket_type: false,
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
    );
}

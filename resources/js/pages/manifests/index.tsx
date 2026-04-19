import { ActionType } from '@/components/action-column';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { edit, index, show } from '@/routes/manifests';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { packageStatusColors, packageStatusLabels } from '../packages/schema';
import { ManifestSchema } from './schema';

interface ManifestsProps {
    data: {
        manifestsForDatatable: ManifestSchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manifests',
        href: index().url,
    },
];

const columns: ColumnDef<ManifestSchema>[] = [
    createSelectColumn<ManifestSchema>(),
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
        accessorKey: 'package_name',
        header: 'Package',
        meta: { exportable: true },
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'return_date',
        header: 'Return Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'members_count',
        header: 'Members',
        meta: { exportable: true },
    },
    {
        accessorKey: 'total_seats',
        header: 'Available Seats',
        meta: { exportable: true },
        cell: ({ row }) => {
            const total = row.original.total_seats;
            const left = row.original.seats_left;

            if (total === null || total === undefined) {
                return '-';
            }

            return `${left ?? 0} / ${total}`;
        },
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
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
    },
];

export default function ManifestsIndex({ data }: ManifestsProps) {
    const { manifestsForDatatable } = data;

    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    // if (userPermissions.includes('manifest create')) actions.push('add');
    if (userPermissions.includes('manifest view')) actions.push('view');
    if (userPermissions.includes('manifest edit')) actions.push('edit');

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Manifests" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Manifests</h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={manifestsForDatatable}
                            actions={actions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            url={index().url}
                            onAction={(action, row) => {
                                const manifestId = row?.original.id;

                                if (manifestId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(manifestId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(manifestId).url);
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
                                    created_at: false,
                                    members_count: false,
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
        </>
    );
}

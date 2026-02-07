import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { create, destroy, edit, index, show } from '@/routes/manifests';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';

interface ManifestDataTableSchema {
    id: number;
    reference_number: string;
    package_name: string;
    departure_date: string;
    return_date: string;
    duration: string | null;
    makkah_hotel: string | null;
    madinah_hotel: string | null;
    travelers_count: number;
    status: string;
    created_at: string;
}

interface ManifestsProps {
    data: {
        manifestsForDatatable: ManifestDataTableSchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manifests',
        href: index().url,
    },
];

const columns: ColumnDef<ManifestDataTableSchema>[] = [
    createSelectColumn<ManifestDataTableSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'reference_number',
        header: 'Reference No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'package_name',
        header: 'Package',
        meta: { exportable: true },
    },
    {
        accessorKey: 'departure_date',
        header: 'Departure',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'return_date',
        header: 'Return',
        meta: { exportable: true },
    },
    {
        accessorKey: 'duration',
        header: 'Duration',
        meta: { exportable: true },
    },
    {
        accessorKey: 'makkah_hotel',
        header: 'Makkah Hotel',
        meta: { exportable: true },
    },
    {
        accessorKey: 'madinah_hotel',
        header: 'Madinah Hotel',
        meta: { exportable: true },
    },
    {
        accessorKey: 'travelers_count',
        header: 'Travelers',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.status;
            const colors: Record<string, string> = {
                draft: 'bg-gray-100 text-gray-800',
                confirmed: 'bg-blue-100 text-blue-800',
                completed: 'bg-green-100 text-green-800',
                cancelled: 'bg-red-100 text-red-800',
            };
            return (
                <span
                    className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold ${colors[status] ?? 'bg-gray-100 text-gray-800'}`}
                >
                    {status}
                </span>
            );
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
    },
];

export default function ManifestsIndex({ data }: ManifestsProps) {
    const actions: ActionType[] = ['add', 'view', 'edit', 'delete'];
    const { manifestsForDatatable } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Manifests" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Manifests</h2>
                    </div>

                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={manifestsForDatatable}
                            actions={actions}
                            addButtonText="Create New Manifest"
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const manifestId = row?.original.id;

                                if (manifestId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(manifestId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(manifestId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Manifest',
                                            message: `Are you sure you want to delete manifest "${row?.original.reference_number}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(manifestId).url,
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
                                    madinah_hotel: false,
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

import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import { DataTable } from '@/components/data-table';
import { CustomExport } from '@/components/data-table-export';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { edit, index, show } from '@/routes/manifests';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { useMemo, useState } from 'react';
import { packageStatusColors, packageStatusLabels } from '../packages/schema';
import {
    generateManifestImportTemplate,
    ManifestImportDialog,
    type ManifestImportOption,
} from './import-dialog';
import { type ManifestSchema } from './schema';

type ManifestDataTableSchema = ManifestSchema & {
    package_number?: string | null;
    package_name?: string | null;
    departure_date?: string | null;
    return_date?: string | null;
    country_name?: string | null;
    created_at?: string | null;
};

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

    const countryOptions = [
        ...new Set(
            manifestsForDatatable
                .map((r) => r.country_name)
                .filter((c): c is string => Boolean(c)),
        ),
    ].map((c) => ({ value: c, label: c }));

    // if (userPermissions.includes('manifest create')) actions.push('add');
    if (userPermissions.includes('manifest view')) actions.push('view');
    if (userPermissions.includes('manifest edit')) actions.push('edit');

    const [importOpen, setImportOpen] = useState(false);

    const manifestOptions: ManifestImportOption[] = useMemo(
        () =>
            manifestsForDatatable
                .filter((m) => m.id != null)
                .map((m) => {
                    const idValue = Number(m.id);
                    const parts = [
                        m.package_number,
                        m.package_name,
                        m.departure_date ? `(${m.departure_date})` : null,
                    ].filter(Boolean);
                    return {
                        id: idValue,
                        label:
                            parts.length > 0
                                ? `#${idValue} — ${parts.join(' ')}`
                                : `Manifest #${idValue}`,
                    };
                }),
        [manifestsForDatatable],
    );

    const customExports: CustomExport[] = [
        {
            label: 'Download Import Template',
            icon: Download,
            onClick: generateManifestImportTemplate,
        },
    ];

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
                            showImport={userPermissions.includes(
                                'manifest edit',
                            )}
                            onImport={() => setImportOpen(true)}
                            customExports={customExports}
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
            <ManifestImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                manifests={manifestOptions}
            />
        </>
    );
}

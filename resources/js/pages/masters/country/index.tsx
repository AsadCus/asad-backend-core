import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { index as masterIndex } from '@/routes/master';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { CountrySchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Country',
        href: '/master/country',
    },
];

const actions: ActionType[] = ['add', 'view', 'edit', 'delete'];

const columns: ColumnDef<CountrySchema>[] = [
    createSelectColumn<CountrySchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'adjective',
        header: 'Adjective',
        meta: { exportable: true },
    },
];

interface CountryIndexProps {
    dataCountry: CountrySchema[];
}

export default function CountryIndex({ dataCountry }: CountryIndexProps) {
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Country" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Country</h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={dataCountry}
                            actions={actions}
                            url="/master/country"
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get('/master/country/create');
                                }

                                const countryId = row?.original.id;

                                if (countryId !== undefined) {
                                    if (action === 'view') {
                                        router.get(
                                            `/master/country/${countryId}`,
                                        );
                                    } else if (action === 'edit') {
                                        router.get(
                                            `/master/country/${countryId}/edit`,
                                        );
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Country',
                                            message: `Are you sure you want to delete country "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    `/master/country/${countryId}`,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            initialState={{
                                columnVisibility: {
                                    id: false,
                                },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

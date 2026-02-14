import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy,
    edit,
    index,
    setDefault,
    show,
} from '@/routes/master/financial-year';
import { index as masterIndex } from '@/routes/master/user';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Check, X } from 'lucide-react';
import { FinancialYearSchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Master',
        href: masterIndex().url,
    },
    {
        title: 'Financial Year',
        href: index().url,
    },
];

const actions: ActionType[] = [
    'add',
    'view',
    'edit',
    'set-default-year',
    'delete',
];

const columns: ColumnDef<FinancialYearSchema>[] = [
    createSelectColumn<FinancialYearSchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'year',
        header: 'Year',
        meta: { exportable: true },
    },
    {
        accessorKey: 'start_date',
        header: 'Start Date',
        meta: { exportable: true },
        cell: ({ row }) => {
            const date = row.original.start_date;
            if (!date) return '-';
            return new Date(date).toLocaleDateString('en-GB', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
    {
        accessorKey: 'end_date',
        header: 'End Date',
        meta: { exportable: true },
        cell: ({ row }) => {
            const date = row.original.end_date;
            if (!date) return '-';
            return new Date(date).toLocaleDateString('en-GB', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
    {
        accessorKey: 'default',
        header: 'Default',
        meta: { exportable: false },
        cell: ({ row }) => {
            const isDefault = row.original.default;

            if (!isDefault)
                return (
                    <Badge variant="default" className="bg-red-300">
                        <X className="h-4 w-4" />
                    </Badge>
                );

            return (
                <Badge variant="default" className="bg-green-500">
                    <Check className="h-4 w-4" />
                </Badge>
            );
        },
    },
];

interface FinancialYearProps {
    data: {
        financialYears: FinancialYearSchema[];
    };
}

export default function FinancialYear({ data }: FinancialYearProps) {
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Financial Year" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Financial Year
                        </h2>
                    </div>
                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={data.financialYears}
                            actions={actions}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const financialYearId = row?.original.id;

                                if (financialYearId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(financialYearId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(financialYearId).url);
                                    } else if (action === 'set-default-year') {
                                        confirm({
                                            title: 'Set Financial Year',
                                            message: `Are you sure you want to set "${row?.original.year} as default year"?`,
                                            variant: 'primary',
                                            confirmText: 'Set',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.put(
                                                    setDefault(financialYearId)
                                                        .url,
                                                );
                                            },
                                        });
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Financial Year',
                                            message: `Are you sure you want to delete year "${row?.original.year}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(financialYearId)
                                                        .url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            initialState={{
                                columnVisibility: { id: false },
                            }}
                        />
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />
        </>
    );
}

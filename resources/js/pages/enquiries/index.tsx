import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { index } from '@/routes/enquiries';
import { show as generalEnquiryShow } from '@/routes/general-enquiries';
import { show as privateEnquiryShow } from '@/routes/private-enquiries';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';

interface EnquirySchema {
    id: number;
    type: 'General' | 'Private';
    full_name: string;
    contact: string;
    email: string;
    created_at: string;
}

interface EnquiriesProps {
    data: {
        enquiriesForDatatable: EnquirySchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'All Enquiries',
        href: index().url,
    },
];

const typeOptions = [
    { label: 'General', value: 'General' },
    { label: 'Private', value: 'Private' },
];

const typeColors: Record<string, string> = {
    General: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    Private:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

const columns: ColumnDef<EnquirySchema>[] = [
    createSelectColumn<EnquirySchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'type',
        header: 'Type',
        meta: { exportable: true },
        cell: ({ row }) => {
            const type = row.original.type;
            const color = typeColors[type] ?? '';
            return (
                <Badge className={`${color} rounded-full px-3 py-1 text-sm`}>
                    {type}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'full_name',
        header: 'Full Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'contact',
        header: 'Contact',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
];

export default function EnquiriesIndex({ data }: EnquiriesProps) {
    const { enquiriesForDatatable } = data;

    const actions: ActionType[] = ['view'];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All Enquiries" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">All Enquiries</h2>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                    <DataTable
                        columns={columns}
                        data={enquiriesForDatatable}
                        actions={actions}
                        url={index().url}
                        onAction={(action, row) => {
                            const enquiry = row?.original;
                            if (!enquiry) return;

                            if (action === 'view') {
                                if (enquiry.type === 'General') {
                                    router.get(
                                        generalEnquiryShow(enquiry.id).url,
                                    );
                                } else {
                                    router.get(
                                        privateEnquiryShow(enquiry.id).url,
                                    );
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
                            },
                        }}
                        renderFilter={(table) => (
                            <>
                                <ColumnFilter
                                    table={table}
                                    columnId="type"
                                    title="Type"
                                    options={typeOptions}
                                />
                                <DateRangeFilter
                                    table={table}
                                    columnId="created_at"
                                    title="Created At"
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

import { ActionType } from '@/components/action-column';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy,
    edit,
    index,
    show,
} from '@/routes/general-enquiries';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';

interface GeneralEnquirySchema {
    id: number;
    full_name: string;
    mobile: string;
    email: string;
    preferred_destinations: string;
    preferred_travelling_date: string;
    no_of_adults: number;
    no_of_children: number;
    requires_mobility_assistance: string | null;
    created_at: string;
    updated_at: string;
}

interface GeneralEnquiriesProps {
    data: {
        enquiriesForDatatable: GeneralEnquirySchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'General Enquiry',
        href: index().url,
    },
];

const columns: ColumnDef<GeneralEnquirySchema>[] = [
    createSelectColumn<GeneralEnquirySchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'full_name',
        header: 'Full Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'mobile',
        header: 'Mobile',
        meta: { exportable: true },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        meta: { exportable: true },
    },
    {
        accessorKey: 'preferred_destinations',
        header: 'Preferred Destinations',
        meta: { exportable: true },
    },
    {
        accessorKey: 'preferred_travelling_date',
        header: 'Preferred Travelling Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'no_of_adults',
        header: 'No. of Adults',
        meta: { exportable: true },
    },
    {
        accessorKey: 'no_of_children',
        header: 'No. of Children',
        meta: { exportable: true },
    },
    {
        accessorKey: 'requires_mobility_assistance',
        header: 'Mobility Assistance',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'updated_at',
        header: 'Updated At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
];

export default function GeneralEnquiriesIndex({ data }: GeneralEnquiriesProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('general-enquiry create')) actions.push('add');
    if (userPermissions.includes('general-enquiry view')) actions.push('view');
    if (userPermissions.includes('general-enquiry delete')) actions.push('delete');

    const hasEditPermission = userPermissions.includes('general-enquiry edit');
    const { enquiriesForDatatable } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="General Enquiry" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            General Enquiry
                        </h2>
                    </div>

                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={enquiriesForDatatable}
                            actions={actions}
                            addButtonText="Create New General Enquiry"
                            getRowActions={() => {
                                const rowActions: ActionType[] = [];

                                if (hasEditPermission) {
                                    rowActions.push('edit');
                                }

                                return rowActions;
                            }}
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const enquiryId = row?.original.id;

                                if (enquiryId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(enquiryId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(enquiryId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Enquiry',
                                            message: `Are you sure you want to delete enquiry from "${row?.original.full_name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(enquiryId).url,
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
                                    requires_mobility_assistance: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <DateRangeFilter
                                        table={table}
                                        columnId="preferred_travelling_date"
                                        title="Travelling Date"
                                        quickDate={true}
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

            <ConfirmDialog />
        </>
    );
}

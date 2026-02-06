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
} from '@/routes/private-enquiries';
import { SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';

interface PrivateEnquirySchema {
    id: number;
    full_name: string;
    contact_number: string;
    email: string;
    passport_expiry_date: string;
    departure_date: string;
    return_date: string;
    no_of_pax: number;
    no_of_children: number;
    airline: string;
    class: string;
    require_mutawif: boolean;
    require_umrah_course: boolean;
    require_umrah_official: boolean;
    makkah_or_madinah_first: string;
    no_of_nights_makkah: string;
    hotel_makkah: string;
    meals_makkah: string;
    no_of_nights_madinah: string;
    hotel_madinah: string;
    meals_madinah: string;
    land_transfer: string;
    add_on_speed_train: boolean;
    require_meet_greet: boolean;
    require_mutawiffah_ustazah_rawdah: boolean;
    madinah_tour_with_mutawif: boolean;
    makkah_tour_with_mutawif: boolean;
    has_chronic_disease: boolean;
    chronic_disease_details: string | null;
    need_wheelchair: string;
    other_remarks: string | null;
    created_at: string;
    updated_at: string;
}

interface PrivateEnquiriesProps {
    data: {
        enquiriesForDatatable: PrivateEnquirySchema[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Private Enquiry',
        href: index().url,
    },
];

const renderBoolean = (value: boolean) => (value ? 'Yes' : 'No');

const columns: ColumnDef<PrivateEnquirySchema>[] = [
    createSelectColumn<PrivateEnquirySchema>(),
    { accessorKey: 'id', header: 'ID', meta: { exportable: true } },
    { accessorKey: 'full_name', header: 'Full Name', meta: { exportable: true } },
    { accessorKey: 'contact_number', header: 'Contact Number', meta: { exportable: true } },
    { accessorKey: 'email', header: 'Email', meta: { exportable: true } },
    { accessorKey: 'passport_expiry_date', header: 'Passport Expiry Date', meta: { exportable: true }, filterFn: 'dateRangeFilter' },
    { accessorKey: 'departure_date', header: 'Departure Date', meta: { exportable: true }, filterFn: 'dateRangeFilter' },
    { accessorKey: 'return_date', header: 'Return Date', meta: { exportable: true }, filterFn: 'dateRangeFilter' },
    { accessorKey: 'no_of_pax', header: 'No. of Pax', meta: { exportable: true } },
    { accessorKey: 'no_of_children', header: 'No. of Children', meta: { exportable: true } },
    { accessorKey: 'airline', header: 'Airline', meta: { exportable: true } },
    { accessorKey: 'class', header: 'Class', meta: { exportable: true } },
    {
        accessorKey: 'require_mutawif',
        header: 'Require Mutawif',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_umrah_course',
        header: 'Require Umrah Course',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_umrah_official',
        header: 'Require Umrah Official',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    { accessorKey: 'makkah_or_madinah_first', header: 'Makkah/Madinah First', meta: { exportable: true } },
    { accessorKey: 'no_of_nights_makkah', header: 'Nights in Makkah', meta: { exportable: true } },
    { accessorKey: 'hotel_makkah', header: 'Hotel Makkah', meta: { exportable: true } },
    { accessorKey: 'meals_makkah', header: 'Meals Makkah', meta: { exportable: true } },
    { accessorKey: 'no_of_nights_madinah', header: 'Nights in Madinah', meta: { exportable: true } },
    { accessorKey: 'hotel_madinah', header: 'Hotel Madinah', meta: { exportable: true } },
    { accessorKey: 'meals_madinah', header: 'Meals Madinah', meta: { exportable: true } },
    { accessorKey: 'land_transfer', header: 'Land Transfer', meta: { exportable: true } },
    {
        accessorKey: 'add_on_speed_train',
        header: 'Add-on Speed Train',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_meet_greet',
        header: 'Require Meet & Greet',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_mutawiffah_ustazah_rawdah',
        header: 'Require Mutawiffah/Ustazah Rawdah',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'madinah_tour_with_mutawif',
        header: 'Madinah Tour w/ Mutawif',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'makkah_tour_with_mutawif',
        header: 'Makkah Tour w/ Mutawif',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'has_chronic_disease',
        header: 'Has Chronic Disease',
        meta: { exportable: true },
        cell: info => renderBoolean(info.getValue() as boolean),
    },
    { accessorKey: 'chronic_disease_details', header: 'Chronic Disease Details', meta: { exportable: true } },
    { accessorKey: 'need_wheelchair', header: 'Need Wheelchair', meta: { exportable: true } },
    { accessorKey: 'other_remarks', header: 'Other Remarks', meta: { exportable: true } },
    { accessorKey: 'created_at', header: 'Created At', meta: { exportable: true }, filterFn: 'dateRangeFilter' },
    { accessorKey: 'updated_at', header: 'Updated At', meta: { exportable: true }, filterFn: 'dateRangeFilter' },
];

export default function Index({ data }: PrivateEnquiriesProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('private-enquiry create')) actions.push('add');
    if (userPermissions.includes('private-enquiry view')) actions.push('view');
    if (userPermissions.includes('private-enquiry delete'))
        actions.push('delete');

    const hasEditPermission = userPermissions.includes('private-enquiry edit');
    const { enquiriesForDatatable } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Private Enquiry" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Private Enquiry
                        </h2>
                    </div>

                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={enquiriesForDatatable}
                            actions={actions}
                            addButtonText="Create New Private Enquiry"
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
                                        columnId="departure_date"
                                        title="Departure Date"
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

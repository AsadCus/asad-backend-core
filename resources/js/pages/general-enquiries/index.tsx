import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import {
    create,
    destroy,
    edit,
    getForShow,
    index,
} from '@/routes/general-enquiries';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import CustomerConfirmationForm from '../confirmed-customer/form';
import EnquiryRemarksDialog from '../enquiries/components/enquiry-remarks-dialog';
import {
    EnquiryStatusAction,
    EnquiryStatusActionType,
    getAvailableEnquiryActions,
} from '../enquiries/components/enquiry-status-action';
import EnquiryViewDialog from '../enquiries/components/enquiry-view-dialog';
import {
    statusColors,
    statusOptions,
    type GeneralEnquiryDatatableSchema,
} from '../enquiries/schema';
import { GeneralEnquirySchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'General Enquiry',
        href: index().url,
    },
];

export interface GeneralEnquiriesProps {
    data: {
        enquiriesForDatatable: GeneralEnquiryDatatableSchema[];
        packageOptions: OptionType[];
    };
}

const columns: ColumnDef<GeneralEnquiryDatatableSchema>[] = [
    createSelectColumn<GeneralEnquiryDatatableSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.status;
            const label = row.original.status_label;
            const color = statusColors[status] ?? '';
            return (
                <Badge className={`${color} rounded-full px-3 py-1 text-base`}>
                    {label}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'name',
        header: 'Full Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'contact_number',
        header: 'Contact Number',
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
        accessorKey: 'last_remark',
        header: 'Last Remark',
        meta: { exportable: true },
    },
    {
        accessorKey: 'handled_by_name',
        header: 'Handled By',
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
    if (userPermissions.includes('general-enquiry delete'))
        actions.push('delete');

    const hasEditPermission = userPermissions.includes('general-enquiry edit');
    const { enquiriesForDatatable, packageOptions } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [isLoadingData, setIsLoadingData] = useState(false);
    const [selectedData, setSelectedData] =
        useState<GeneralEnquirySchema | null>(null);

    // Enquiry Status Action state
    const [statusAction, setStatusAction] =
        useState<EnquiryStatusActionType | null>(null);
    const [statusActionEnquiryId, setStatusActionEnquiryId] = useState<
        number | undefined
    >();
    const [statusActionEnquiryType, setStatusActionEnquiryType] = useState<
        string | undefined
    >();
    const [statusDialogOpen, setStatusDialogOpen] = useState(false);

    // Customer Confirmation Form state
    const [confirmFormOpen, setConfirmFormOpen] = useState(false);
    const [confirmFormEnquiryId, setConfirmFormEnquiryId] = useState<
        number | undefined
    >();
    const [confirmFormPrefill, setConfirmFormPrefill] = useState({
        name: '',
        email: '',
        contact: '',
    });
    const [prefillPackageId, setPrefillPackageId] = useState<number | null>(
        null,
    );

    // Enquiry Remarks state
    const [remarksDialogOpen, setRemarksDialogOpen] = useState(false);
    const [remarksEnquiryId, setRemarksEnquiryId] = useState<
        number | undefined
    >();
    const [remarksEnquiryName, setRemarksEnquiryName] = useState('');

    const handleOpenViewDialog = async (enquiryId: number) => {
        setViewDialogOpen(true);
        setIsLoadingData(true);
        setSelectedData(null);

        try {
            const response = await fetch(getForShow(enquiryId).url);
            if (!response.ok) throw new Error('Failed to fetch enquiry data');
            const enquiryData = await response.json();
            setSelectedData(enquiryData);
        } catch (error) {
            console.error('Failed to fetch enquiry details:', error);
        } finally {
            setIsLoadingData(false);
        }
    };

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

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={enquiriesForDatatable}
                            actions={actions}
                            addButtonText="Create New General Enquiry"
                            getRowActions={(row) => {
                                const rowActions: ActionType[] = [];

                                if (hasEditPermission) {
                                    rowActions.push('edit');
                                }

                                if (row.enquiry_id) {
                                    rowActions.push('add-remark');
                                    const available =
                                        getAvailableEnquiryActions(row.status);
                                    available.forEach((a) =>
                                        rowActions.push(
                                            `enquiry-status-${a}` as ActionType,
                                        ),
                                    );
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
                                        handleOpenViewDialog(enquiryId);
                                    } else if (action === 'edit') {
                                        router.get(edit(enquiryId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Enquiry',
                                            message: `Are you sure you want to delete enquiry from "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(enquiryId).url,
                                                );
                                            },
                                        });
                                    }

                                    if (
                                        action === 'enquiry-status-contacted' ||
                                        action === 'enquiry-status-confirmed'
                                    ) {
                                        const actionType = action.replace(
                                            'enquiry-status-',
                                            '',
                                        ) as EnquiryStatusActionType;
                                        setStatusAction(actionType);
                                        setStatusActionEnquiryId(
                                            row?.original.enquiry_id ??
                                                undefined,
                                        );
                                        setStatusActionEnquiryType('general');
                                        setStatusDialogOpen(true);
                                    }

                                    if (action === 'add-remark') {
                                        setRemarksEnquiryId(
                                            row?.original.enquiry_id ??
                                                undefined,
                                        );
                                        setRemarksEnquiryName(
                                            row?.original.name ?? '',
                                        );
                                        setRemarksDialogOpen(true);
                                    }
                                }
                            }}
                            onRowDoubleClick={(row) => {
                                if (row.id) {
                                    handleOpenViewDialog(row.id);
                                }
                            }}
                            initialState={{
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    preferred_destinations: false,
                                    preferred_travelling_date: false,
                                    no_of_adults: false,
                                    no_of_children: false,
                                    requires_mobility_assistance: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="status"
                                        title="Status"
                                        options={statusOptions}
                                    />
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

            {/* View Dialog */}
            <EnquiryViewDialog
                open={viewDialogOpen}
                onOpenChange={setViewDialogOpen}
                enquiryId={selectedData?.enquiry_id ?? undefined}
                enquiryType="general"
                statusLabel={selectedData?.status_label}
                statusValue={selectedData?.status}
                childData={selectedData as Record<string, unknown> | null}
                isLoadingChild={isLoadingData}
                packageOptions={packageOptions}
                showStatusActions={true}
                onStatusActionConfirmed={(enquiryId) => {
                    const enquiry = enquiriesForDatatable.find(
                        (e) => e.enquiry_id === enquiryId,
                    );
                    setConfirmFormEnquiryId(enquiryId);
                    setConfirmFormPrefill({
                        name: enquiry?.name ?? '',
                        email: enquiry?.email ?? '',
                        contact: enquiry?.contact_number ?? '',
                    });
                    setPrefillPackageId(enquiry?.package_id ?? null);
                    setConfirmFormOpen(true);
                }}
            />

            {/* Enquiry Status Action Dialog */}
            <EnquiryStatusAction
                enquiryId={statusActionEnquiryId}
                enquiryType={statusActionEnquiryType}
                action={statusAction}
                isOpen={statusDialogOpen}
                onClose={() => {
                    setStatusDialogOpen(false);
                    setStatusAction(null);
                    setStatusActionEnquiryType(undefined);
                }}
                onConfirmed={(enquiryId) => {
                    const enquiry = enquiriesForDatatable.find(
                        (e) => e.enquiry_id === enquiryId,
                    );
                    setConfirmFormEnquiryId(enquiryId);
                    setConfirmFormPrefill({
                        name: enquiry?.name ?? '',
                        email: enquiry?.email ?? '',
                        contact: enquiry?.contact_number ?? '',
                    });
                    setPrefillPackageId(enquiry?.package_id ?? null);
                    setConfirmFormOpen(true);
                }}
            />

            {/* Customer Confirmation Form Dialog */}
            <Dialog open={confirmFormOpen} onOpenChange={setConfirmFormOpen}>
                <DialogContent
                    className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onOpenAutoFocus={(event) => event.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle>Customer Confirmation Form</DialogTitle>
                        <DialogDescription>
                            Fill in the details of the customer group and its
                            members.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        className="h-full w-full flex-1 overflow-y-auto"
                        style={{
                            scrollbarWidth: 'none',
                            msOverflowStyle: 'none',
                        }}
                    >
                        <CustomerConfirmationForm
                            enquiryId={confirmFormEnquiryId}
                            prefillName={confirmFormPrefill.name}
                            prefillEmail={confirmFormPrefill.email}
                            prefillContact={confirmFormPrefill.contact}
                            prefillPackageId={prefillPackageId}
                            packageOptions={packageOptions}
                            onSuccess={() => {
                                setConfirmFormOpen(false);
                                router.reload();
                            }}
                            onCancel={() => setConfirmFormOpen(false)}
                        />
                    </div>
                </DialogContent>
            </Dialog>

            {/* Enquiry Remarks Dialog */}
            <EnquiryRemarksDialog
                isOpen={remarksDialogOpen}
                onClose={() => setRemarksDialogOpen(false)}
                enquiryId={remarksEnquiryId}
                enquiryName={remarksEnquiryName}
            />
        </>
    );
}

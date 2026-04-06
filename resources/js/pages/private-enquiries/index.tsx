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
    focusFirstDialogFormField,
    handleDialogTabKey,
} from '@/lib/dialog-focus';
import { packagePrefill } from '@/routes/enquiries';
import {
    create,
    destroy,
    edit,
    getForShow,
    index,
} from '@/routes/private-enquiries';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useCallback, useState } from 'react';
import CustomerConfirmationForm from '../confirmed-customer/form';
import EnquiryRemarksDialog from '../enquiries/components/enquiry-remarks-dialog';
import {
    EnquiryStatusAction,
    EnquiryStatusActionType,
    getAvailableEnquiryActions,
} from '../enquiries/components/enquiry-status-action';
import EnquiryViewDialog from '../enquiries/components/enquiry-view-dialog';
import {
    EnquiryDetails,
    statusColors,
    statusOptions,
    type PrivateEnquiryDatatableSchema,
} from '../enquiries/schema';
import PackageForm from '../packages/form';
import type { PackageSchema } from '../packages/schema';
import { PrivateEnquirySchema } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Private Enquiry',
        href: index().url,
    },
];

export interface PrivateEnquiriesProps {
    data: {
        enquiriesForDatatable: PrivateEnquiryDatatableSchema[];
        packageOptions: OptionType[];
    };
}

const renderBoolean = (value: boolean) => (value ? 'Yes' : 'No');

const columns: ColumnDef<PrivateEnquiryDatatableSchema>[] = [
    createSelectColumn<PrivateEnquiryDatatableSchema>(),
    { accessorKey: 'id', header: 'ID', meta: { exportable: true } },
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
    { accessorKey: 'email', header: 'Email', meta: { exportable: true } },
    {
        accessorKey: 'passport_expiry_date',
        header: 'Passport Expiry Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
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
        accessorKey: 'no_of_pax',
        header: 'No. of Pax',
        meta: { exportable: true },
    },
    {
        accessorKey: 'no_of_children',
        header: 'No. of Children',
        meta: { exportable: true },
    },
    { accessorKey: 'airline', header: 'Airline', meta: { exportable: true } },
    { accessorKey: 'class', header: 'Class', meta: { exportable: true } },
    {
        accessorKey: 'require_mutawif',
        header: 'Require Mutawif',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_umrah_course',
        header: 'Require Umrah Course',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_umrah_official',
        header: 'Require Umrah Official',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'makkah_or_madinah_first',
        header: 'Makkah/Madinah First',
        meta: { exportable: true },
    },
    {
        accessorKey: 'no_of_nights_makkah',
        header: 'Nights in Makkah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'hotel_makkah',
        header: 'Hotel Makkah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'meals_makkah',
        header: 'Meals Makkah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'no_of_nights_madinah',
        header: 'Nights in Madinah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'hotel_madinah',
        header: 'Hotel Madinah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'meals_madinah',
        header: 'Meals Madinah',
        meta: { exportable: true },
    },
    {
        accessorKey: 'land_transfer',
        header: 'Land Transfer',
        meta: { exportable: true },
    },
    {
        accessorKey: 'add_on_speed_train',
        header: 'Add-on Speed Train',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_meet_greet',
        header: 'Require Meet & Greet',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'require_mutawiffah_ustazah_rawdah',
        header: 'Require Mutawiffah/Ustazah Rawdah',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'madinah_tour_with_mutawif',
        header: 'Madinah Tour w/ Mutawif',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'makkah_tour_with_mutawif',
        header: 'Makkah Tour w/ Mutawif',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'has_chronic_disease',
        header: 'Has Chronic Disease',
        meta: { exportable: true },
        cell: (info) => renderBoolean(info.getValue() as boolean),
    },
    {
        accessorKey: 'chronic_disease_details',
        header: 'Chronic Disease Details',
        meta: { exportable: true },
    },
    {
        accessorKey: 'need_wheelchair',
        header: 'Need Wheelchair',
        meta: { exportable: true },
    },
    {
        accessorKey: 'other_remarks',
        header: 'Other Remarks',
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

export default function Index({ data }: PrivateEnquiriesProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('private-enquiry create')) actions.push('add');
    if (userPermissions.includes('private-enquiry view')) actions.push('view');
    if (userPermissions.includes('private-enquiry delete'))
        actions.push('delete');

    const hasEditPermission = userPermissions.includes('private-enquiry edit');
    const { enquiriesForDatatable, packageOptions } = data;
    const { confirm, ConfirmDialog } = useConfirmDialog();

    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [isLoadingData, setIsLoadingData] = useState(false);
    const [selectedData, setSelectedData] =
        useState<PrivateEnquirySchema | null>(null);

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

    // Private enquiry step-by-step confirmation flow
    const [privateFlowOpen, setPrivateFlowOpen] = useState(false);
    const [privateFlowEnquiryId, setPrivateFlowEnquiryId] = useState<
        number | undefined
    >();
    const [privateFlowStep, setPrivateFlowStep] = useState<
        'package' | 'customer'
    >('package');
    const [privateFlowPackageData, setPrivateFlowPackageData] =
        useState<PackageSchema | null>(null);
    const [privateFlowPrefill, setPrivateFlowPrefill] = useState({
        name: '',
        email: '',
        contact: '',
    });
    const [privateFlowPkgPrefill, setPrivateFlowPkgPrefill] = useState<
        Partial<PackageSchema> | undefined
    >(undefined);
    const [privateFlowEnquiryDetails, setPrivateFlowEnquiryDetails] = useState<
        EnquiryDetails | undefined
    >(undefined);

    // Enquiry Remarks state
    const [remarksDialogOpen, setRemarksDialogOpen] = useState(false);
    const [remarksEnquiryId, setRemarksEnquiryId] = useState<
        number | undefined
    >();
    const [remarksEnquiryName, setRemarksEnquiryName] = useState('');

    /** Start the private enquiry step-by-step confirmation flow. */
    const startPrivateFlow = useCallback(
        async (enquiryId: number) => {
            const enquiry = enquiriesForDatatable.find(
                (e) => e.enquiry_id === enquiryId,
            );
            setPrivateFlowEnquiryId(enquiryId);
            setPrivateFlowStep('package');
            setPrivateFlowPackageData(null);
            setPrivateFlowPrefill({
                name: enquiry?.name ?? '',
                email: enquiry?.email ?? '',
                contact: enquiry?.contact_number ?? '',
            });
            setPrivateFlowEnquiryDetails(
                enquiry
                    ? {
                          id: enquiry.enquiry_id ?? 0,
                          type: 'Private',
                          name: enquiry.name ?? '',
                          email: enquiry.email ?? '',
                          contact: enquiry.contact_number ?? '',
                          status: enquiry.status_label ?? '',
                          created_at: enquiry.created_at ?? null,
                      }
                    : undefined,
            );
            setPrivateFlowOpen(true);

            // Fetch prefill data for the package form from the private enquiry
            try {
                const res = await fetch(packagePrefill(enquiryId).url);
                if (res.ok) {
                    const json = await res.json();
                    setPrivateFlowPkgPrefill(json);
                }
            } catch {
                // Ignore — user can fill manually
            }
        },
        [enquiriesForDatatable],
    );

    /** Called when the package form is completed (step 1 → step 2). */
    const handlePrivatePackageComplete = useCallback(
        (pkgData: PackageSchema) => {
            setPrivateFlowPackageData(pkgData);
            setPrivateFlowStep('customer');
        },
        [],
    );

    /** Cancel the entire private flow. */
    const cancelPrivateFlow = useCallback(() => {
        setPrivateFlowOpen(false);
        setPrivateFlowPackageData(null);
        setPrivateFlowPkgPrefill(undefined);
    }, []);

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
                <Head title="Private Enquiry" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Private Enquiry
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={enquiriesForDatatable}
                            actions={actions}
                            addButtonText="Create New Private Enquiry"
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
                                        setStatusActionEnquiryType('private');
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
                                    passport_expiry_date: false,
                                    departure_date: false,
                                    return_date: false,
                                    no_of_pax: false,
                                    no_of_children: false,
                                    airline: false,
                                    class: false,
                                    require_mutawif: false,
                                    require_umrah_course: false,
                                    require_umrah_official: false,
                                    makkah_or_madinah_first: false,
                                    no_of_nights_makkah: false,
                                    hotel_makkah: false,
                                    meals_makkah: false,
                                    no_of_nights_madinah: false,
                                    hotel_madinah: false,
                                    meals_madinah: false,
                                    land_transfer: false,
                                    add_on_speed_train: false,
                                    require_meet_greet: false,
                                    require_mutawiffah_ustazah_rawdah: false,
                                    madinah_tour_with_mutawif: false,
                                    makkah_tour_with_mutawif: false,
                                    has_chronic_disease: false,
                                    chronic_disease_details: false,
                                    need_wheelchair: false,
                                    other_remarks: false,
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

            {/* View Dialog */}
            <EnquiryViewDialog
                open={viewDialogOpen}
                onOpenChange={setViewDialogOpen}
                enquiryId={selectedData?.enquiry_id ?? undefined}
                enquiryType="private"
                statusLabel={selectedData?.status_label}
                statusValue={selectedData?.status}
                childData={selectedData as Record<string, unknown> | null}
                isLoadingChild={isLoadingData}
                showStatusActions={true}
                onStatusActionConfirmed={(enquiryId) => {
                    startPrivateFlow(enquiryId);
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
                    startPrivateFlow(enquiryId);
                }}
            />

            {/* Private Enquiry Step-by-Step Confirmation Flow */}
            <Dialog open={privateFlowOpen} onOpenChange={cancelPrivateFlow}>
                <DialogContent
                    className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onOpenAutoFocus={focusFirstDialogFormField}
                    onKeyDown={handleDialogTabKey}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {privateFlowStep === 'package'
                                ? 'Step 1: Create Package'
                                : 'Step 2: Customer Confirmation'}
                        </DialogTitle>
                        <DialogDescription>
                            {privateFlowStep === 'package'
                                ? 'Fill in the package details for this private enquiry. This data is pre-filled from the enquiry.'
                                : 'Fill in the customer group details. The package created in step 1 will be linked automatically.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto pb-2">
                        {privateFlowStep === 'package' && (
                            <PackageForm
                                mode="create"
                                prefillData={privateFlowPkgPrefill}
                                onCancel={cancelPrivateFlow}
                                onSuccess={handlePrivatePackageComplete}
                            />
                        )}
                        {privateFlowStep === 'customer' && (
                            <CustomerConfirmationForm
                                enquiryId={privateFlowEnquiryId}
                                enquiryType="private"
                                enquiryDetails={privateFlowEnquiryDetails}
                                prefillName={privateFlowPrefill.name}
                                prefillEmail={privateFlowPrefill.email}
                                prefillContact={privateFlowPrefill.contact}
                                packageData={
                                    privateFlowPackageData ?? undefined
                                }
                                packageOptions={packageOptions}
                                onSuccess={() => {
                                    cancelPrivateFlow();
                                    router.reload();
                                }}
                                onCancel={() => {
                                    // Go back to package step instead of closing
                                    setPrivateFlowStep('package');
                                }}
                            />
                        )}
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

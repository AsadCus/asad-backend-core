import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
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
import { getForShow, index, packagePrefill } from '@/routes/enquiries';
import { edit as generalEnquiryEdit } from '@/routes/general-enquiries';
import { edit as privateEnquiryEdit } from '@/routes/private-enquiries';
import { OptionType, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useCallback, useState } from 'react';
import CustomerConfirmationForm from '../confirmed-customer/form';
import PackageForm from '../packages/form';
import type { PackageSchema } from '../packages/schema';
import EnquiryRemarksDialog from './components/enquiry-remarks-dialog';
import {
    EnquiryStatusAction,
    EnquiryStatusActionType,
    getAvailableEnquiryActions,
} from './components/enquiry-status-action';
import EnquiryViewDialog from './components/enquiry-view-dialog';
import {
    EnquiryDetails,
    statusColors,
    statusOptions,
    typeColors,
    typeOptions,
    type EnquirySchema,
} from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Enquiry Dashboard',
        href: index().url,
    },
];

export interface EnquiriesProps {
    data: {
        enquiriesForDatatable: EnquirySchema[];
        statusOptions: OptionType[];
        packageOptions: OptionType[];
    };
}

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
                <Badge className={`${color} rounded-full px-3 py-1 text-base`}>
                    {type}
                </Badge>
            );
        },
        filterFn: 'includesValue',
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
        accessorKey: 'package_name',
        header: 'Package',
        meta: { exportable: true },
        cell: ({ row }) => {
            const name = row.original.package_name;
            if (!name) return <span className="text-muted-foreground">-</span>;
            return (
                <Badge
                    variant="outline"
                    className="rounded-full px-3 py-1 text-base"
                >
                    {name}
                </Badge>
            );
        },
    },
    {
        accessorKey: 'latest_remark',
        header: 'Latest Remark',
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
];

export default function EnquiriesIndex({ data }: EnquiriesProps) {
    const { enquiriesForDatatable, packageOptions } = data;

    const actions: ActionType[] = ['view', 'edit'];

    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [isLoadingData, setIsLoadingData] = useState(false);
    const [selectedEnquiryData, setSelectedEnquiryData] = useState<{
        enquiry: {
            id: number;
            type: string;
            status: string;
            status_label: string;
        };
        child: Record<string, unknown>;
        customerConfirmation: unknown;
    } | null>(null);

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
    const [confirmFormEnquiryDetails, setConfirmFormEnquiryDetails] = useState<
        EnquiryDetails | undefined
    >(undefined);

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
                (e) => e.id === enquiryId,
            );
            setPrivateFlowEnquiryId(enquiryId);
            setPrivateFlowStep('package');
            setPrivateFlowPackageData(null);
            setPrivateFlowPrefill({
                name: enquiry?.name ?? '',
                email: enquiry?.email ?? '',
                contact: enquiry?.contact ?? '',
            });
            setPrivateFlowEnquiryDetails(
                enquiry
                    ? {
                          id: enquiry.id,
                          type: enquiry.type,
                          name: enquiry.name,
                          email: enquiry.email,
                          contact: enquiry.contact,
                          status: enquiry.status_label,
                          package_name: enquiry.package_name,
                          created_at: enquiry.created_at,
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
        setSelectedEnquiryData(null);

        try {
            const response = await fetch(getForShow(enquiryId).url);
            if (!response.ok) throw new Error('Failed to fetch enquiry data');
            const enquiryData = await response.json();
            setSelectedEnquiryData(enquiryData);
        } catch (error) {
            console.error('Failed to fetch enquiry details:', error);
        } finally {
            setIsLoadingData(false);
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Enquiry Dashboard" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Enquiry Dashboard
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={enquiriesForDatatable}
                            actions={actions}
                            url={index().url}
                            getRowActions={(row) => {
                                const rowActions: ActionType[] = [];
                                const available = getAvailableEnquiryActions(
                                    row.status,
                                );

                                available.forEach((a) =>
                                    rowActions.push(
                                        `enquiry-status-${a}` as ActionType,
                                    ),
                                );

                                if (row.id) {
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
                            onAction={(action, row) => {
                                const enquiry = row?.original;
                                if (!enquiry) return;

                                if (action === 'view') {
                                    handleOpenViewDialog(enquiry.id);
                                }

                                if (action === 'edit') {
                                    if (
                                        enquiry.type === 'General' &&
                                        enquiry.child_id
                                    ) {
                                        router.get(
                                            generalEnquiryEdit(enquiry.child_id)
                                                .url,
                                        );
                                    } else if (
                                        enquiry.type === 'Private' &&
                                        enquiry.child_id
                                    ) {
                                        router.get(
                                            privateEnquiryEdit(enquiry.child_id)
                                                .url,
                                        );
                                    }
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
                                    setStatusActionEnquiryId(enquiry.id);
                                    setStatusActionEnquiryType(enquiry.type);
                                    setStatusDialogOpen(true);
                                }

                                if (action === 'add-remark') {
                                    setRemarksEnquiryId(
                                        row?.original.id ?? undefined,
                                    );
                                    setRemarksEnquiryName(
                                        row?.original.name ?? '',
                                    );
                                    setRemarksDialogOpen(true);
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
                                    package_name: false,
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
                                    <ColumnFilter
                                        table={table}
                                        columnId="status"
                                        title="Status"
                                        options={statusOptions}
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

            {/* View Dialog */}
            <EnquiryViewDialog
                open={viewDialogOpen}
                onOpenChange={setViewDialogOpen}
                enquiryId={selectedEnquiryData?.enquiry.id}
                enquiryType={selectedEnquiryData?.enquiry.type}
                statusLabel={selectedEnquiryData?.enquiry.status_label}
                statusValue={selectedEnquiryData?.enquiry.status}
                childData={
                    selectedEnquiryData?.child as Record<string, unknown> | null
                }
                isLoadingChild={isLoadingData}
                packageOptions={packageOptions}
                showStatusActions={true}
                onStatusActionConfirmed={(enquiryId) => {
                    const enquiry = enquiriesForDatatable.find(
                        (e) => e.id === enquiryId,
                    );

                    if (enquiry?.type === 'Private') {
                        startPrivateFlow(enquiryId);
                    } else {
                        setConfirmFormEnquiryId(enquiryId);
                        setConfirmFormPrefill({
                            name: enquiry?.name ?? '',
                            email: enquiry?.email ?? '',
                            contact: enquiry?.contact ?? '',
                        });
                        setPrefillPackageId(enquiry?.package_id ?? null);
                        setConfirmFormEnquiryDetails(
                            enquiry
                                ? {
                                      id: enquiry.id,
                                      type: enquiry.type,
                                      name: enquiry.name,
                                      email: enquiry.email,
                                      contact: enquiry.contact,
                                      status: enquiry.status_label,
                                      package_name: enquiry.package_name,
                                      created_at: enquiry.created_at,
                                  }
                                : undefined,
                        );
                        setConfirmFormOpen(true);
                    }
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
                        (e) => e.id === enquiryId,
                    );

                    if (enquiry?.type === 'Private') {
                        // Private enquiry → step-by-step: package form first
                        startPrivateFlow(enquiryId);
                    } else {
                        // General enquiry → direct customer confirmation
                        setConfirmFormEnquiryId(enquiryId);
                        setConfirmFormPrefill({
                            name: enquiry?.name ?? '',
                            email: enquiry?.email ?? '',
                            contact: enquiry?.contact ?? '',
                        });
                        setPrefillPackageId(enquiry?.package_id ?? null);
                        setConfirmFormEnquiryDetails(
                            enquiry
                                ? {
                                      id: enquiry.id,
                                      type: enquiry.type,
                                      name: enquiry.name,
                                      email: enquiry.email,
                                      contact: enquiry.contact,
                                      status: enquiry.status_label,
                                      package_name: enquiry.package_name,
                                      created_at: enquiry.created_at,
                                  }
                                : undefined,
                        );
                        setConfirmFormOpen(true);
                    }
                }}
            />

            {/* Customer Confirmation Form Dialog */}
            <Dialog open={confirmFormOpen} onOpenChange={setConfirmFormOpen}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onOpenAutoFocus={(event) => event.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle>Customer Confirmation</DialogTitle>
                        <DialogDescription>
                            Fill in the customer group details for this enquiry.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        className="h-full w-full flex-1 overflow-y-auto pb-2"
                        style={{
                            scrollbarWidth: 'none',
                            msOverflowStyle: 'none',
                        }}
                    >
                        <CustomerConfirmationForm
                            enquiryId={confirmFormEnquiryId}
                            enquiryDetails={confirmFormEnquiryDetails}
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

            {/* Private Enquiry Step-by-Step Confirmation Flow */}
            <Dialog open={privateFlowOpen} onOpenChange={cancelPrivateFlow}>
                <DialogContent
                    className="flex max-h-[95%] max-w-[95%] min-w-[95%] flex-col"
                    onOpenAutoFocus={(event) => event.preventDefault()}
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

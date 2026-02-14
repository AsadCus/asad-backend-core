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
import { getForShow, index } from '@/routes/enquiries';
import { edit as generalEnquiryEdit } from '@/routes/general-enquiries';
import { edit as privateEnquiryEdit } from '@/routes/private-enquiries';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import GeneralEnquiryForm from '../general-enquiries/form';
import PrivateEnquiryForm from '../private-enquiries/form';
import {
    EnquiryStatusAction,
    EnquiryStatusActionType,
    getAvailableEnquiryActions,
} from './components/enquiry-status-action';
import CustomerConfirmationForm from './customer-confirmation-form';
import {
    type EnquirySchema,
    statusColors,
    StatusOption,
    statusOptions,
    typeColors,
    typeOptions,
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
        statusOptions: StatusOption[];
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
        customerGroup: unknown;
    } | null>(null);

    // Enquiry Status Action state
    const [statusAction, setStatusAction] =
        useState<EnquiryStatusActionType | null>(null);
    const [statusActionEnquiryId, setStatusActionEnquiryId] = useState<
        number | undefined
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
                                    action === 'enquiry-status-negotiating' ||
                                    action === 'enquiry-status-confirmed'
                                ) {
                                    const actionType = action.replace(
                                        'enquiry-status-',
                                        '',
                                    ) as EnquiryStatusActionType;
                                    setStatusAction(actionType);
                                    setStatusActionEnquiryId(enquiry.id);
                                    setStatusDialogOpen(true);
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
            <Dialog open={viewDialogOpen} onOpenChange={setViewDialogOpen}>
                <DialogContent className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <DialogTitle>View Enquiry Details</DialogTitle>
                                <DialogDescription className="sr-only">
                                    Displays detailed information about the
                                    selected enquiry.
                                </DialogDescription>
                            </div>
                            {selectedEnquiryData && (
                                <div className="mr-8 flex items-center gap-2">
                                    <Badge
                                        className={`${typeColors[selectedEnquiryData.enquiry.type === 'general' ? 'General' : 'Private'] ?? ''} rounded-full px-3 py-1 text-base`}
                                    >
                                        {selectedEnquiryData.enquiry.type ===
                                        'general'
                                            ? 'General'
                                            : 'Private'}
                                    </Badge>
                                    <Badge
                                        className={`${statusColors[selectedEnquiryData.enquiry.status] ?? ''} rounded-full px-3 py-1 text-base`}
                                    >
                                        {
                                            selectedEnquiryData.enquiry
                                                .status_label
                                        }
                                    </Badge>
                                </div>
                            )}
                        </div>
                    </DialogHeader>

                    <div className="h-full w-full flex-1 overflow-y-auto">
                        {isLoadingData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading enquiry details...
                            </div>
                        )}
                        {!isLoadingData && selectedEnquiryData && (
                            <>
                                {selectedEnquiryData.enquiry.type ===
                                'general' ? (
                                    <GeneralEnquiryForm
                                        mode="view"
                                        initialData={
                                            selectedEnquiryData.child as Record<
                                                string,
                                                unknown
                                            > as import('../general-enquiries/schema').GeneralEnquirySchema
                                        }
                                        onCancel={() =>
                                            setViewDialogOpen(false)
                                        }
                                    />
                                ) : (
                                    <PrivateEnquiryForm
                                        mode="view"
                                        initialData={
                                            selectedEnquiryData.child as Record<
                                                string,
                                                unknown
                                            > as import('../private-enquiries/schema').PrivateEnquirySchema
                                        }
                                        onCancel={() =>
                                            setViewDialogOpen(false)
                                        }
                                    />
                                )}
                            </>
                        )}
                        {!isLoadingData && !selectedEnquiryData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Failed to load enquiry details
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Enquiry Status Action Dialog */}
            <EnquiryStatusAction
                enquiryId={statusActionEnquiryId}
                action={statusAction}
                isOpen={statusDialogOpen}
                onClose={() => {
                    setStatusDialogOpen(false);
                    setStatusAction(null);
                }}
                onConfirmed={(enquiryId) => {
                    const enquiry = enquiriesForDatatable.find(
                        (e) => e.id === enquiryId,
                    );
                    setConfirmFormEnquiryId(enquiryId);
                    setConfirmFormPrefill({
                        name: enquiry?.full_name ?? '',
                        email: enquiry?.email ?? '',
                        contact: enquiry?.contact ?? '',
                    });
                    setConfirmFormOpen(true);
                }}
            />

            {/* Customer Confirmation Form Dialog */}
            <Dialog open={confirmFormOpen} onOpenChange={setConfirmFormOpen}>
                <DialogContent className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <DialogTitle>Customer Confirmation</DialogTitle>
                        <DialogDescription>
                            Fill in the customer group details for this enquiry.
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
                            onSuccess={() => {
                                setConfirmFormOpen(false);
                                router.reload();
                            }}
                            onCancel={() => setConfirmFormOpen(false)}
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

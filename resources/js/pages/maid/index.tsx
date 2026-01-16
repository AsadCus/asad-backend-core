import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ZoomableImage } from '@/components/zoomable-image';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { create, destroy, edit, getForShow, index, show } from '@/routes/maid';
import {
    OptionType,
    SharedData,
    ValueNumberOptionType,
    type BreadcrumbItem,
} from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { MaidCardList } from './card-list';
import {
    MaidStatusActions,
    getAvailableMaidActions,
} from './components/MaidStatusActions';
import { DocumentGenerator } from './components/document-generator';
import { MaidBiodataPreview } from './components/maid-biodata-preview';
import { MaidForm } from './form';
import { MaidSchema, maritalStatus, status } from './schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Maid',
        href: index().url,
    },
];

export const columns: ColumnDef<MaidSchema>[] = [
    createSelectColumn<MaidSchema>(),

    { accessorKey: 'id', header: 'ID', meta: { exportable: true } },
    {
        accessorKey: 'maid_number',
        header: 'Maid No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'photo_url',
        header: 'Photo',
        cell: ({ row }) => {
            const photo = row.original.photo_url;
            const name = row.original.name;
            let photoUrl: string | undefined;
            if (photo instanceof File) {
                photoUrl = URL.createObjectURL(photo);
            } else if (typeof photo === 'string') {
                photoUrl = photo;
            }
            if (!photoUrl)
                return (
                    <span className="text-xs text-gray-400 italic">
                        No photo
                    </span>
                );
            return (
                <ZoomableImage src={photoUrl} alt={name} thumbnailSize={50} />
            );
        },
        meta: { exportable: false },
    },
    { accessorKey: 'name', header: 'Name', meta: { exportable: true } },
    { accessorKey: 'age', header: 'Age', meta: { exportable: true } },
    {
        accessorKey: 'nationality',
        header: 'Nationality',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            const colors: Record<string, string> = {
                available:
                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                interviewing:
                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                pending:
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                assigned:
                    'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
            };
            return (
                <span
                    className={`rounded-full px-2 py-1 text-xs font-medium capitalize ${colors[status] || ''}`}
                >
                    {status}
                </span>
            );
        },
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'interview_date_formatted',
        header: 'Interview Date',
        cell: ({ row }) => {
            const formattedDate = row.original.interview_date_formatted;
            if (!formattedDate) {
                return <span className="text-xs text-gray-400">-</span>;
            }
            return (
                <span className="text-xs whitespace-nowrap">
                    {formattedDate}
                </span>
            );
        },
        meta: { exportable: true },
    },
    {
        accessorKey: 'pending_until',
        header: 'Pending Until',
        cell: ({ row }) => {
            const pendingUntil = row.original.pending_until;
            if (!pendingUntil || pendingUntil === null) {
                return <span className="text-xs text-gray-400">-</span>;
            }
            try {
                const date = new Date(pendingUntil);
                if (isNaN(date.getTime())) {
                    return <span className="text-xs text-gray-400">-</span>;
                }
                return (
                    <span className="text-xs font-medium whitespace-nowrap text-orange-600">
                        {date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                        })}
                    </span>
                );
            } catch {
                return <span className="text-xs text-gray-400">-</span>;
            }
        },
        meta: { exportable: true },
    },
    {
        accessorKey: 'pending_reason',
        header: 'Pending Reason',
        cell: ({ row }) => {
            const reason = row.original.pending_reason;
            if (!reason) {
                return <span className="text-xs text-gray-400">-</span>;
            }
            return (
                <span
                    className="block max-w-[200px] truncate text-xs text-muted-foreground"
                    title={reason}
                >
                    {reason}
                </span>
            );
        },
        meta: { exportable: true },
    },

    // Personal Details
    {
        accessorKey: 'date_of_birth',
        header: 'Date of Birth',
        meta: { exportable: true },
    },
    {
        accessorKey: 'place_of_birth',
        header: 'Place of Birth',
        meta: { exportable: true },
    },
    {
        accessorKey: 'height',
        header: 'Height (cm)',
        meta: { exportable: true },
        cell: ({ row }) => {
            const height = row.original.height;
            return height ? parseFloat(String(height)) : '-';
        },
    },
    {
        accessorKey: 'weight',
        header: 'Weight (kg)',
        meta: { exportable: true },
        cell: ({ row }) => {
            const weight = row.original.weight;
            return weight ? parseFloat(String(weight)) : '-';
        },
    },
    {
        accessorKey: 'passport_number',
        header: 'Passport Number',
        meta: { exportable: true },
    },
    {
        accessorKey: 'religion',
        header: 'Religion',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'education_level',
        header: 'Education',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'marital_status',
        header: 'Marital Status',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },

    // Family
    {
        accessorKey: 'number_of_siblings',
        header: 'Siblings',
        meta: { exportable: true },
    },
    {
        accessorKey: 'number_of_children',
        header: 'Children',
        meta: { exportable: true },
    },
    {
        accessorKey: 'children_ages',
        header: 'Children Ages',
        meta: { exportable: true },
    },

    // Contact
    { accessorKey: 'address', header: 'Address', meta: { exportable: true } },
    {
        accessorKey: 'contact_number_home_country',
        header: 'Contact Number',
        meta: { exportable: true },
    },
    {
        accessorKey: 'repatriation_port_airport',
        header: 'Repatriation Port',
        meta: { exportable: true },
    },

    // Employment
    {
        accessorKey: 'singapore_experience',
        header: 'SG Experience',
        meta: { exportable: true },
        cell: ({ row }) => (row.original.singapore_experience ? 'Yes' : 'No'),
    },
    {
        accessorKey: 'experience_years',
        header: 'Experience (Years)',
        meta: { exportable: true },
        cell: ({ row }) => row.original.experience_years || '-',
    },
    {
        accessorKey: 'employment_feedback',
        header: 'Employment Feedback',
        meta: { exportable: true },
        cell: ({ row }) => row.original.employment_feedback || '-',
    },

    // Preferences
    {
        accessorKey: 'rest_days_per_month',
        header: 'Rest Days/Month',
        meta: { exportable: true },
    },
    {
        accessorKey: 'availability_remarks',
        header: 'Interview Availability',
        meta: { exportable: true },
        cell: ({ row }) => row.original.availability_remarks || '-',
    },
    {
        accessorKey: 'other_remarks',
        header: 'Other Remarks',
        meta: { exportable: true },
    },

    // Financial
    {
        accessorKey: 'supplier',
        header: 'Supplier',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'remaining_loan',
        header: 'Remaining Loan',
        meta: { exportable: true },
        cell: ({ row }) => {
            const loan = row.original.remaining_loan;
            return loan ? `${parseFloat(String(loan))} months` : '-';
        },
    },
    {
        accessorKey: 'cost_of_maid',
        header: 'Cost',
        meta: { exportable: true },
        cell: ({ row }) => {
            return formatCurrency(row.original.cost_of_maid);
        },
    },

    // Attributes (Grouped)
    {
        accessorKey: 'illness_attributes',
        header: 'Illness',
        meta: { exportable: true },
    },
    {
        accessorKey: 'allergy_attributes',
        header: 'Allergy',
        meta: { exportable: true },
    },
    {
        accessorKey: 'physical_disability_attributes',
        header: 'Physical Disability',
        meta: { exportable: true },
    },
    {
        accessorKey: 'diet_restriction_attributes',
        header: 'Diet Restriction',
        meta: { exportable: true },
    },
    {
        accessorKey: 'food_preference_attributes',
        header: 'Food Preference',
        meta: { exportable: true },
    },
];

interface MaidProps {
    data: MaidSchema[];
    dataNationality: OptionType[];
    dataReligion: OptionType[];
    dataEducationLevel: OptionType[];
    dataSupplier: OptionType[];
    misc?: {
        nationalities: ValueNumberOptionType[];
        religions: ValueNumberOptionType[];
        education_levels: ValueNumberOptionType[];
        suppliers: ValueNumberOptionType[];
    };
}

export default function Maid({
    data,
    dataNationality,
    dataReligion,
    dataEducationLevel,
    dataSupplier,
    misc,
}: MaidProps) {
    const { auth } = usePage<SharedData>().props;
    const initialViewMode = auth.roles?.includes('customer')
        ? 'cards'
        : 'table';
    const userPermissions = auth.permissions || [];
    const actions: ActionType[] = [];

    if (userPermissions.includes('maid create')) actions.push('add');
    if (userPermissions.includes('maid view')) actions.push('view', 'preview');
    if (userPermissions.includes('maid edit')) actions.push('edit');
    if (userPermissions.includes('maid delete')) actions.push('delete');

    const hasEditPermission = userPermissions.includes('maid edit');

    const [viewMode, setViewMode] = useState<'table' | 'cards'>(
        initialViewMode,
    );

    const { confirm, ConfirmDialog } = useConfirmDialog();

    const [statusActionDialogOpen, setStatusActionDialogOpen] = useState(false);
    const [statusActionType, setStatusActionType] = useState<
        'schedule' | 'complete' | 'cancel' | 'update' | null
    >(null);
    const [selectedMaidForStatus, setSelectedMaidForStatus] =
        useState<MaidSchema | null>(null);
    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [selectedMaidData, setSelectedMaidData] = useState<MaidSchema | null>(
        null,
    );
    const [isLoadingMaidData, setIsLoadingMaidData] = useState(false);
    const [previewDialogOpen, setPreviewDialogOpen] = useState(false);
    const [selectedMaidForPreview, setSelectedMaidForPreview] =
        useState<MaidSchema | null>(null);

    const handleStatusAction = (action: ActionType, maidData: MaidSchema) => {
        let type: 'schedule' | 'complete' | 'cancel' | 'update' | null = null;

        switch (action) {
            case 'maid-status-schedule':
                type = 'schedule';
                break;
            case 'maid-status-complete':
                type = 'complete';
                break;
            case 'maid-status-cancel':
                type = 'cancel';
                break;
            case 'maid-status-update':
                type = 'update';
                break;
        }

        setStatusActionType(type);
        setSelectedMaidForStatus(maidData);
        setStatusActionDialogOpen(true);
    };

    const handleOpenViewDialog = async (maidId: string) => {
        setViewDialogOpen(true);
        setIsLoadingMaidData(true);
        setSelectedMaidData(null);

        try {
            const response = await fetch(getForShow(maidId).url);
            if (!response.ok) throw new Error('Failed to fetch maid data');
            const maidData = await response.json();
            setSelectedMaidData(maidData);
        } catch (error) {
            console.error('Failed to fetch maid details:', error);
        } finally {
            setIsLoadingMaidData(false);
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Maid" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Maid Profile</h2>
                        <div className="flex gap-2">
                            {!auth.roles?.includes('customer') && (
                                <>
                                    <Button
                                        variant={
                                            viewMode === 'table'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setViewMode('table')}
                                    >
                                        Table View
                                    </Button>
                                    <Button
                                        variant={
                                            viewMode === 'cards'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => setViewMode('cards')}
                                    >
                                        Card View
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                    <div
                        className={`relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 ${viewMode === 'table' ? 'px-3' : 'px-1'} py-3 md:min-h-min dark:border-sidebar-border`}
                    >
                        {viewMode === 'table' &&
                        !auth.roles?.includes('customer') ? (
                            <DataTable
                                columns={columns}
                                data={data}
                                actions={actions}
                                getRowActions={(maid) =>
                                    hasEditPermission
                                        ? getAvailableMaidActions(
                                              maid.status,
                                          ).map(
                                              (statusAction) =>
                                                  `maid-status-${statusAction}` as ActionType,
                                          )
                                        : []
                                }
                                url={index().url}
                                exportFilename="maid"
                                onAction={(action, row) => {
                                    if (action === 'add') {
                                        router.get(create().url);
                                        return;
                                    }

                                    const maidData = row?.original;
                                    if (!maidData) return;

                                    if (action.startsWith('maid-status-')) {
                                        handleStatusAction(action, maidData);
                                        return;
                                    }

                                    const maidId = maidData.id;
                                    if (!maidId) return;

                                    if (action === 'view') {
                                        router.get(show(maidId).url);
                                    } else if (action === 'preview') {
                                        setSelectedMaidForPreview(maidData);
                                        setPreviewDialogOpen(true);
                                    } else if (action === 'edit') {
                                        router.get(edit(maidId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            onConfirm: () =>
                                                router.delete(
                                                    destroy(maidId).url,
                                                ),
                                        });
                                    }
                                }}
                                onRowDoubleClick={(row) => {
                                    if (row.id) {
                                        handleOpenViewDialog(row.id);
                                    }
                                }}
                                initialState={{
                                    pagination: {
                                        pageSize: data.length,
                                        pageIndex: 0,
                                    },
                                    columnVisibility: {
                                        id: false,
                                        date_of_birth: false,
                                        place_of_birth: false,
                                        height: false,
                                        weight: false,
                                        address: false,
                                        passport_number: false,
                                        contact_number_home_country: false,
                                        repatriation_port_airport: false,
                                        number_of_siblings: false,
                                        number_of_children: false,
                                        children_ages: false,
                                        employment_feedback: false,
                                        availability_remarks: false,
                                        other_remarks: false,
                                        rest_days_per_month: false,
                                        pending_reason: false,
                                        pending_until: false,
                                        interview_date_formatted: false,
                                        illness_attributes: false,
                                        allergy_attributes: false,
                                        physical_disability_attributes: false,
                                        diet_restriction_attributes: false,
                                        food_preference_attributes: false,
                                    },
                                }}
                                renderFilter={(table) => (
                                    <>
                                        <ColumnFilter
                                            table={table}
                                            columnId="nationality"
                                            title="Nationality"
                                            options={dataNationality}
                                        />
                                        <ColumnFilter
                                            table={table}
                                            columnId="religion"
                                            title="Religion"
                                            options={dataReligion}
                                        />
                                        <ColumnFilter
                                            table={table}
                                            columnId="education_level"
                                            title="Education Level"
                                            options={dataEducationLevel}
                                        />
                                        <ColumnFilter
                                            table={table}
                                            columnId="marital_status"
                                            title="Marital Status"
                                            options={maritalStatus}
                                        />
                                        <ColumnFilter
                                            table={table}
                                            columnId="supplier"
                                            title="Supplier"
                                            options={dataSupplier}
                                        />
                                        <ColumnFilter
                                            table={table}
                                            columnId="status"
                                            title="Status"
                                            options={status}
                                        />
                                    </>
                                )}
                            />
                        ) : (
                            <MaidCardList
                                data={data}
                                dataNationality={dataNationality}
                                dataReligion={dataReligion}
                                dataEducationLevel={dataEducationLevel}
                                misc={misc}
                                actions={actions}
                                hasEditPermission={hasEditPermission}
                                onAction={(action, row) => {
                                    if (action === 'add') {
                                        router.get(create().url);
                                    }

                                    const maidId = row?.id;

                                    // Handle status actions
                                    if (
                                        action.startsWith('maid-status-') &&
                                        row
                                    ) {
                                        handleStatusAction(action, row);
                                        return;
                                    }

                                    // Handle regular actions
                                    if (maidId !== undefined) {
                                        if (action === 'view') {
                                            router.get(show(maidId).url);
                                        } else if (action === 'edit') {
                                            router.get(edit(maidId).url);
                                        } else if (action === 'delete') {
                                            confirm({
                                                title: 'Delete User',
                                                message: `Are you sure you want to delete maid "${row?.name}"?`,
                                                confirmText: 'Delete',
                                                cancelText: 'Cancel',
                                                onConfirm: () => {
                                                    router.delete(
                                                        destroy(maidId).url,
                                                    );
                                                },
                                            });
                                        }
                                    }
                                }}
                            />
                        )}
                    </div>
                </div>
            </AppLayout>
            <ConfirmDialog />

            {/* Status Actions Dialog for Table View */}
            {selectedMaidForStatus && (
                <MaidStatusActions
                    maid={selectedMaidForStatus}
                    isOpen={statusActionDialogOpen}
                    onClose={() => {
                        setStatusActionDialogOpen(false);
                        setStatusActionType(null);
                        setSelectedMaidForStatus(null);
                    }}
                    action={statusActionType}
                />
            )}

            {/* View Dialog for Table View */}
            <Dialog open={viewDialogOpen} onOpenChange={setViewDialogOpen}>
                <DialogContent className="flex max-h-[95%] min-h-[95%] max-w-[95%] min-w-[95%] flex-col overflow-y-hidden">
                    <DialogHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <DialogTitle>View Maid Details</DialogTitle>
                                <DialogDescription className="sr-only">
                                    Displays detailed information about the
                                    selected maid.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div
                        className="h-full w-full flex-1 overflow-y-auto"
                        style={{
                            scrollbarWidth: 'none',
                            msOverflowStyle: 'none',
                        }}
                    >
                        {isLoadingMaidData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading maid details...
                            </div>
                        )}
                        {!isLoadingMaidData && selectedMaidData && (
                            <>
                                <div className="mb-4 flex justify-end">
                                    <DocumentGenerator
                                        maidId={Number(selectedMaidData.id)}
                                        maidName={selectedMaidData.name}
                                    />
                                </div>
                                <MaidForm
                                    mode="view"
                                    initialData={selectedMaidData}
                                    nationalities={misc?.nationalities}
                                    religions={misc?.religions}
                                    educationLevels={misc?.education_levels}
                                    suppliers={misc?.suppliers}
                                    onCancel={() => setViewDialogOpen(false)}
                                />
                            </>
                        )}
                        {!isLoadingMaidData && !selectedMaidData && (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Failed to load maid details
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Preview Dialog */}
            {selectedMaidForPreview && (
                <MaidBiodataPreview
                    maidId={Number(selectedMaidForPreview.id)}
                    maidName={selectedMaidForPreview.name}
                    isOpen={previewDialogOpen}
                    onOpenChange={setPreviewDialogOpen}
                />
            )}
        </>
    );
}

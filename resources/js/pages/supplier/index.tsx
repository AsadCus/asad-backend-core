import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { createSelectColumn } from '@/components/select-column';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import {
    create as createMaid,
    destroy as destroyMaid,
    edit as editMaid,
    getForShow,
    index as maidIndex,
    show as showMaid,
} from '@/routes/maid';
import {
    create,
    destroy,
    edit,
    show,
    index as supplierIndex,
} from '@/routes/supplier';
import {
    SharedData,
    ValueNumberOptionType,
    type BreadcrumbItem,
} from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { columns as maidColumns } from '../maid';
import { DocumentGenerator } from '../maid/components/document-generator';
import {
    getAvailableMaidActions,
    MaidStatusActions,
} from '../maid/components/MaidStatusActions';
import { MaidForm } from '../maid/form';
import { MaidSchema, maritalStatus, status } from '../maid/schema';
import { UserSchema } from '../masters/users/schema';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier',
        href: supplierIndex().url,
    },
];

const columns: ColumnDef<UserSchema>[] = [
    createSelectColumn<UserSchema>(),
    {
        accessorKey: 'id',
        header: 'Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'company_name',
        header: 'Company Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'name',
        header: 'Name',
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
        accessorKey: 'address',
        header: 'Address',
        meta: { exportable: true },
        cell: ({ row }) => {
            const address = row.getValue('address');
            return (
                <div
                    dangerouslySetInnerHTML={{ __html: String(address) }}
                    className="max-w-xs break-words whitespace-pre-wrap"
                />
            );
        },
    },
    {
        accessorKey: 'commission',
        header: 'Commission',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.commission),
    },
    {
        accessorKey: 'total_cost_of_maid',
        header: 'Total Cost of Maid',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.total_cost_of_maid),
    },
];

interface SupplierProps {
    data: {
        suppliers: UserSchema[];
        maidsBySupplier: Record<number, MaidSchema[]>;
        misc?: {
            nationalities: ValueNumberOptionType[];
            religions: ValueNumberOptionType[];
            education_levels: ValueNumberOptionType[];
            suppliers: ValueNumberOptionType[];
        };
    };
}

export default function Supplier({ data }: SupplierProps) {
    const { auth } = usePage<SharedData>().props;
    const suppliers = data.suppliers.map((supplier) => ({
        ...supplier,
        subRows:
            data.maidsBySupplier[(supplier.supplier_id as number) ?? 0] ?? [],
    }));

    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    if (userPermissions.includes('supplier create')) actions.push('add');
    if (userPermissions.includes('supplier view')) actions.push('view');
    if (userPermissions.includes('supplier edit')) actions.push('edit');
    if (userPermissions.includes('supplier delete')) actions.push('delete');

    const actionsMaid: ActionType[] = [];

    if (userPermissions.includes('maid create')) actionsMaid.push('add');
    if (userPermissions.includes('maid view')) actionsMaid.push('view');
    if (userPermissions.includes('maid edit')) actionsMaid.push('edit');
    if (userPermissions.includes('maid delete')) actionsMaid.push('delete');

    const hasEditMaidPermission = userPermissions.includes('maid edit');

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
                <Head title="Supplier" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Supplier</h2>
                    </div>
                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={suppliers}
                            actions={actions}
                            url={supplierIndex().url}
                            enableExpand={true}
                            renderSubComponent={(supplierRow) => {
                                const maids =
                                    (
                                        supplierRow.original as {
                                            subRows?: MaidSchema[];
                                        }
                                    ).subRows ?? [];

                                return (
                                    <div className="space-y-2 pr-2">
                                        <div className="relative flex-1 space-y-2 overflow-hidden rounded-xl border border-sidebar-border/70 bg-white px-3 py-3 md:min-h-min dark:border-sidebar-border">
                                            <h4 className="font-semibold">
                                                Maids From{' '}
                                                {
                                                    supplierRow.original
                                                        .company_name
                                                }
                                            </h4>
                                            <DataTable
                                                enableExpand={false}
                                                columns={maidColumns}
                                                data={maids}
                                                actions={actionsMaid}
                                                getRowActions={(maid) =>
                                                    hasEditMaidPermission
                                                        ? getAvailableMaidActions(
                                                              maid.status,
                                                          ).map(
                                                              (statusAction) =>
                                                                  `maid-status-${statusAction}` as ActionType,
                                                          )
                                                        : []
                                                }
                                                url={maidIndex().url}
                                                exportFilename="maid"
                                                onAction={(
                                                    action: ActionType,
                                                    maidRow,
                                                ) => {
                                                    if (action === 'add') {
                                                        router.get(
                                                            createMaid().url,
                                                            {
                                                                supplier_id:
                                                                    supplierRow
                                                                        .original
                                                                        .supplier_id,
                                                            },
                                                            {
                                                                preserveState: false,
                                                                preserveScroll: false,
                                                            },
                                                        );
                                                        return;
                                                    }

                                                    const maidData =
                                                        maidRow?.original;
                                                    if (!maidData) return;

                                                    if (
                                                        action.startsWith(
                                                            'maid-status-',
                                                        )
                                                    ) {
                                                        handleStatusAction(
                                                            action,
                                                            maidData,
                                                        );
                                                        return;
                                                    }

                                                    const maidId = maidData.id;
                                                    if (!maidId) return;

                                                    if (action === 'view') {
                                                        router.get(
                                                            showMaid(maidId)
                                                                .url,
                                                        );
                                                    } else if (
                                                        action === 'edit'
                                                    ) {
                                                        router.get(
                                                            editMaid(maidId)
                                                                .url,
                                                        );
                                                    } else if (
                                                        action === 'delete'
                                                    ) {
                                                        confirm({
                                                            title: 'Delete Maid',
                                                            message: `Are you sure you want to delete "${maidData.name}"?`,
                                                            confirmText:
                                                                'Delete',
                                                            cancelText:
                                                                'Cancel',
                                                            onConfirm: () => {
                                                                router.delete(
                                                                    destroyMaid(
                                                                        maidId,
                                                                    ).url,
                                                                );
                                                            },
                                                        });
                                                    }
                                                }}
                                                onRowDoubleClick={(maidRow) => {
                                                    if (maidRow.id) {
                                                        handleOpenViewDialog(
                                                            maidRow.id,
                                                        );
                                                    }
                                                }}
                                                initialState={{
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
                                                        allergies: false,
                                                        physical_disabilities: false,
                                                        dietary_restrictions: false,
                                                        food_preferences: false,
                                                        singapore_experience: false,
                                                        employment_feedback: false,
                                                        availability_remarks: false,
                                                        rest_days_per_month: false,
                                                        supplier: false,
                                                        other_remarks: false,
                                                        interview_date: false,
                                                        pending_until: false,
                                                        pending_reason: false,
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
                                                            columnId="marital_status"
                                                            title="Marital Status"
                                                            options={
                                                                maritalStatus
                                                            }
                                                        />
                                                        <ColumnFilter
                                                            table={table}
                                                            columnId="status"
                                                            title="Status"
                                                            options={status}
                                                        />
                                                    </>
                                                )}
                                                renderEmptyState={() => (
                                                    <div className="rounded-md border border-muted/70 bg-muted/5 p-3">
                                                        <div className="text-center text-sm text-muted-foreground">
                                                            No maids found.
                                                        </div>
                                                        {actionsMaid.includes(
                                                            'add',
                                                        ) && (
                                                            <button
                                                                className="mt-2 cursor-pointer rounded-md border px-2 py-1 text-xs hover:bg-muted/10"
                                                                onClick={() =>
                                                                    router.get(
                                                                        createMaid()
                                                                            .url,
                                                                        {
                                                                            supplier_id:
                                                                                supplierRow
                                                                                    .original
                                                                                    .supplier_id,
                                                                        },
                                                                        {
                                                                            preserveState: false,
                                                                            preserveScroll: false,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                Add Maid
                                                            </button>
                                                        )}
                                                    </div>
                                                )}
                                            />
                                        </div>
                                    </div>
                                );
                            }}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const userId = row?.original.id;

                                if (userId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(userId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(userId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete User',
                                            message: `Are you sure you want to delete "${row?.original.name}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(userId).url,
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
                        {isLoadingMaidData ? (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Loading maid details...
                            </div>
                        ) : selectedMaidData ? (
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
                                    nationalities={data.misc?.nationalities}
                                    religions={data.misc?.religions}
                                    educationLevels={
                                        data.misc?.education_levels
                                    }
                                    suppliers={data.misc?.suppliers}
                                    onCancel={() => setViewDialogOpen(false)}
                                />
                            </>
                        ) : (
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                Failed to load maid details
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

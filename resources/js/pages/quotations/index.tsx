import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    getForShow,
    index,
    show,
} from '@/routes/quotation';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    QuotationHandleDialog,
    SalespersonOption,
} from './components/quotation-handle-dialog';
import QuotationPreviewModal from './components/quotation-preview-modal';
import {
    getAvailableQuotationActions,
    QuotationStatusAction,
} from './components/quotation-status-action';
import { QuotationItemSchema } from './items/schema';
import {
    indexStatusValues,
    paymentPlans,
    QuotationSchema,
    statusColors,
    statuses,
} from './schema';

interface QuotationsProps {
    data: {
        quotationsForDatatable: QuotationSchema[];
        customers: OptionType[];
        salespersons: SalespersonOption[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'List of Quotations',
        href: index().url,
    },
];

const defaultQuotationIndexStatusFilters = [...indexStatusValues];

const getColumns = (): ColumnDef<QuotationSchema>[] => [
    createSelectColumn<QuotationSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_number',
        header: 'Quotation No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.status ?? 'draft';
            const label =
                statuses.find((s) => s.value === status)?.label || status;
            const color = statusColors[status as keyof typeof statusColors];

            return (
                <Badge className={`${color} rounded-full px-3 py-1 text-base`}>
                    {label}
                </Badge>
            );
        },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_id',
        header: 'Customer ID',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'package_number',
        header: 'Package Number',
        meta: { exportable: true },
    },
    {
        accessorKey: 'package_name',
        header: 'Package Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'sales_id',
        header: 'Sales ID',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'sales_name',
        header: 'Salesperson',
        meta: { exportable: true },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_date',
        header: 'Quotation Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'expiry_date',
        header: 'Expiry Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'items_count',
        header: 'Items Count',
        meta: { exportable: true },
    },
    {
        accessorKey: 'total_amount',
        header: 'Amount',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.total_amount),
    },
    {
        accessorKey: 'payment_plan',
        header: 'Payment Plan',
        meta: { exportable: true },
        cell: ({ row }) => {
            const paymentPlan = row.original.payment_plan ?? 'full';
            const label =
                paymentPlans.find((s) => s.value === paymentPlan)?.label ||
                paymentPlan;

            return label;
        },
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

export default function QuotationsIndex({ data }: QuotationsProps) {
    const { quotationsForDatatable, customers, salespersons } = data;
    const { auth } = usePage<SharedData>().props;
    const isSuperadmin = auth.roles.includes('superadmin');
    const isSalesOrAdmin =
        auth.roles.includes('sales') || auth.roles.includes('admin');
    const userPermissions = auth.permissions || [];
    const scopeMode = (auth.scope_mode === 'branch' ? 'branch' : 'country') as
        | 'country'
        | 'branch';
    const scopeCountryIds = auth.scope_selected_country_ids ?? [];
    const scopeBranchIds = auth.scope_selected_branch_ids ?? [];
    const columns = useMemo(() => getColumns(), []);
    const actions: ActionType[] = [];

    const salespersonsForFilter = useMemo(
        () =>
            salespersons.map((option) => ({
                value: String(option.value),
                label: option.label,
            })),
        [salespersons],
    );

    const quotationIsInUserScope = (q: QuotationSchema): boolean => {
        if (scopeMode === 'branch') {
            const branchId = Number(q.branch_id ?? 0);
            return branchId > 0 && scopeBranchIds.includes(branchId);
        }
        const countryId = Number(q.country_id ?? 0);
        return countryId > 0 && scopeCountryIds.includes(countryId);
    };

    if (userPermissions.includes('quotation create')) actions.push('add');
    if (userPermissions.includes('quotation view'))
        actions.push('preview', 'download');
    // if (userPermissions.includes('quotation delete')) actions.push('delete');

    const hasEditPermission = userPermissions.includes('quotation edit');
    const hasDeletePermission = userPermissions.includes('quotation delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();
    const [
        quotationStatusActionDialogOpen,
        setQuotationStatusActionDialogOpen,
    ] = useState(false);

    const [quotationStatusActionType, setQuotationStatusActionType] = useState<
        | 'draft'
        | 'ready'
        | 'accept'
        | 'convert'
        | 'reject'
        | 'expire'
        | 'cancel'
        | null
    >(null);
    const [selectedQuotationForStatus, setSelectedQuotationForStatus] =
        useState<QuotationSchema | null>(null);
    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [selectedQuotationForPreview, setSelectedQuotationForPreview] =
        useState<QuotationSchema | null>(null);
    const [previewItems, setPreviewItems] = useState<QuotationItemSchema[]>([]);
    const [handleDialogOpen, setHandleDialogOpen] = useState(false);
    const [selectedQuotationForHandle, setSelectedQuotationForHandle] =
        useState<QuotationSchema | null>(null);
    const handleQuotationStatusAction = (
        action: ActionType,
        quotation: QuotationSchema,
    ) => {
        if (!hasEditPermission) {
            return;
        }

        const determineStatusActionType = ():
            | 'draft'
            | 'ready'
            | 'accept'
            | 'convert'
            | 'reject'
            | 'expire'
            | 'cancel' => {
            if (action === 'quotation-status-draft') return 'draft';
            if (action === 'quotation-status-ready') return 'ready';
            if (action === 'quotation-status-accept') return 'accept';
            if (action === 'quotation-status-convert') return 'convert';
            if (action === 'quotation-status-reject') return 'reject';
            if (action === 'quotation-status-expire') return 'expire';
            if (action === 'quotation-status-cancel') return 'cancel';
            return 'ready';
        };

        setQuotationStatusActionType(determineStatusActionType());
        setSelectedQuotationForStatus(quotation);
        setQuotationStatusActionDialogOpen(true);
    };

    const handlePreview = async (quotation: QuotationSchema) => {
        try {
            if (!quotation.id) return;

            const response = await fetch(getForShow(quotation.id).url);
            if (response.ok) {
                const quotation = await response.json();
                setSelectedQuotationForPreview(quotation);
                setPreviewItems(quotation.items);
            } else {
                setSelectedQuotationForPreview(quotation);
                setPreviewItems([]);
            }

            setPreviewModalOpen(true);
        } catch (error) {
            console.error('Error fetching quotation items:', error);
            setPreviewItems([]);
            setSelectedQuotationForPreview(quotation);
            setPreviewModalOpen(true);
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Quotations" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            List of Quotations
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={quotationsForDatatable}
                            actions={actions}
                            addButtonText="Create New Quotation"
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            getRowActions={(q) => {
                                const rowActions: ActionType[] = [];

                                if (
                                    hasEditPermission &&
                                    (!q.have_invoices ||
                                        q.status === 'converted')
                                ) {
                                    rowActions.push('edit');
                                }

                                if (hasEditPermission) {
                                    const statusActions =
                                        getAvailableQuotationActions(
                                            q.status,
                                        ).map(
                                            (s) =>
                                                `quotation-status-${s}` as ActionType,
                                        );
                                    rowActions.push(
                                        ...statusActions.filter(
                                            (action) =>
                                                action !==
                                                    'quotation-status-ready' &&
                                                action !==
                                                    'quotation-status-expire',
                                        ),
                                    );
                                }

                                const isUnassigned =
                                    !q.sales_id && !q.created_by;
                                if (hasEditPermission && isUnassigned) {
                                    if (isSuperadmin) {
                                        rowActions.push('quotation-handle');
                                    } else if (
                                        isSalesOrAdmin &&
                                        quotationIsInUserScope(q)
                                    ) {
                                        rowActions.push('quotation-handle');
                                    }
                                }

                                if (
                                    hasDeletePermission &&
                                    !['converted', 'cancelled'].includes(
                                        q.status ?? '',
                                    )
                                ) {
                                    rowActions.push('delete');
                                }

                                return rowActions;
                            }}
                            url={index().url}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    router.get(create().url);
                                }

                                const quotationId = row?.original.id;

                                if (quotationId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(quotationId).url);
                                    } else if (action === 'preview') {
                                        handlePreview(row!.original);
                                    } else if (action === 'download') {
                                        (async () => {
                                            try {
                                                const response = await fetch(
                                                    `/quotation/${quotationId}/generate-pdf`,
                                                );
                                                if (!response.ok)
                                                    throw new Error(
                                                        'Failed to generate PDF',
                                                    );
                                                const blob =
                                                    await response.blob();
                                                const url =
                                                    globalThis.URL.createObjectURL(
                                                        blob,
                                                    );

                                                globalThis.open(url, '_blank');

                                                const link =
                                                    document.createElement('a');
                                                link.href = url;
                                                link.download = `quotation-${row?.original.quotation_number}.pdf`;
                                                document.body.appendChild(link);
                                                link.click();
                                                link.remove();
                                            } catch (error) {
                                                console.error(
                                                    'Error opening PDF:',
                                                    error,
                                                );
                                            }
                                        })();
                                    } else if (action === 'edit') {
                                        router.get(edit(quotationId).url);
                                    } else if (
                                        action.startsWith('quotation-status-')
                                    ) {
                                        handleQuotationStatusAction(
                                            action,
                                            row!.original,
                                        );
                                        return;
                                    } else if (action === 'quotation-handle') {
                                        const target = row!.original;
                                        if (isSuperadmin) {
                                            setSelectedQuotationForHandle(
                                                target,
                                            );
                                            setHandleDialogOpen(true);
                                        } else {
                                            confirm({
                                                title: 'Handle Quotation',
                                                message: `Assign quotation "${target.quotation_number}" to yourself?`,
                                                confirmText: 'Assign',
                                                cancelText: 'Cancel',
                                                onConfirm: () => {
                                                    router.post(
                                                        `/quotation/${quotationId}/handle`,
                                                    );
                                                },
                                            });
                                        }
                                        return;
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Quotation',
                                            message: `Are you sure you want to delete quotation "${row?.original.quotation_number}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(quotationId).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            onRowDoubleClick={(quotation) => {
                                if (
                                    hasEditPermission &&
                                    quotation.id &&
                                    (!quotation.have_invoices ||
                                        quotation.status === 'converted')
                                ) {
                                    router.get(edit(quotation.id).url);
                                }
                            }}
                            initialState={{
                                columnFilters: [
                                    {
                                        id: 'status',
                                        value: defaultQuotationIndexStatusFilters,
                                    },
                                ],
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    customer_id: false,
                                    customer_number: false,
                                    package_number: false,
                                    package_name: false,
                                    sales_id: false,
                                    description: false,
                                    expiry_date: false,
                                    items_count: false,
                                    payment_plan: false,
                                    created_at: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="status"
                                        title="Status"
                                        options={statuses}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="customer_id"
                                        title="Customer"
                                        options={customers}
                                    />
                                    {isSuperadmin && (
                                        <ColumnFilter
                                            table={table}
                                            columnId="sales_id"
                                            title="Salesperson"
                                            options={salespersonsForFilter}
                                        />
                                    )}
                                    <DateRangeFilter
                                        table={table}
                                        columnId="quotation_date"
                                        title="Quotation Date"
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

            <QuotationStatusAction
                quotationId={selectedQuotationForStatus?.id}
                action={quotationStatusActionType ?? null}
                isOpen={quotationStatusActionDialogOpen}
                onClose={() => {
                    setQuotationStatusActionDialogOpen(false);
                    setQuotationStatusActionType(null);
                }}
            />

            {/* Preview Modal */}
            {previewModalOpen && selectedQuotationForPreview && (
                <QuotationPreviewModal
                    data={selectedQuotationForPreview}
                    items={previewItems}
                    open={previewModalOpen}
                    onOpenChange={setPreviewModalOpen}
                />
            )}

            <QuotationHandleDialog
                quotationId={selectedQuotationForHandle?.id}
                quotationNumber={selectedQuotationForHandle?.quotation_number ?? undefined}
                quotationCountryId={selectedQuotationForHandle?.country_id ?? null}
                quotationBranchId={selectedQuotationForHandle?.branch_id ?? null}
                scopeMode={scopeMode}
                salespersons={salespersons}
                isOpen={handleDialogOpen}
                onClose={() => {
                    setHandleDialogOpen(false);
                    setSelectedQuotationForHandle(null);
                }}
            />
        </>
    );
}

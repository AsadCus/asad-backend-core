import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import {
    create as createInvoice,
    destroy as destroyInvoice,
    edit as editInvoice,
    getForShow as getInvoiceForShow,
    index as invoiceIndex,
    show as showInvoice,
} from '@/routes/invoice';
import { destroy, edit, index, show } from '@/routes/order';
import {
    create as createReceipt,
    getForShow as getReceiptForShow,
} from '@/routes/receipt';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { invoiceColumns } from '../invoices';
import InvoicePreviewModal from '../invoices/components/invoice-preview-modal';
import {
    indexStatusValues as invoiceIndexStatusValues,
    InvoiceItemSchema,
    InvoiceSchema,
    statuses as invoiceStatuses,
} from '../invoices/schema';
import {
    paymentPlans,
    indexStatusValues as quotationIndexStatusValues,
    statusColors as quotationStatusColors,
    statuses as quotationStatuses,
} from '../quotations/schema';
import ReceiptPreviewModal from '../receipts/components/receipt-preview-modal';
import { ReceiptSchema } from '../receipts/schema';
import OrderCreateDialog from './components/order-create-dialog';
import { OrderSchema } from './schema';

interface QuotationsProps {
    data: {
        ordersForDatatable: OrderSchema[];
        customers: OptionType[];
        salespersons: OptionType[];
        convertableQuotations: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'List of Orders',
        href: index().url,
    },
];

const defaultOrderIndexQuotationStatusFilters = [...quotationIndexStatusValues];
const defaultOrderExpandedInvoiceStatusFilters = [...invoiceIndexStatusValues];

const columns: ColumnDef<OrderSchema>[] = [
    createSelectColumn<OrderSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_id',
        header: 'Customer ID',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
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
        accessorKey: 'order_number',
        header: 'Order No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_id',
        header: 'Quotation Id',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_number',
        header: 'Quotation No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_status',
        header: 'Quotation Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = row.original.quotation_status ?? 'draft';
            const label =
                quotationStatuses.find((option) => option.value === status)
                    ?.label ?? status;
            const color =
                quotationStatusColors[
                    status as keyof typeof quotationStatusColors
                ] ?? 'bg-gray-100 text-gray-800';

            return (
                <Badge
                    className={`${color} rounded-full px-3 py-1 text-base font-medium`}
                >
                    {label}
                </Badge>
            );
        },
        filterFn: 'includesValue',
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

export default function OrderIndex({ data }: QuotationsProps) {
    const {
        ordersForDatatable,
        customers,
        salespersons,
        convertableQuotations,
    } = data;
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const [openCreateDialog, setOpenCreateDialog] = useState(false);
    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [selectedInvoiceForPreview, setSelectedInvoiceForPreview] =
        useState<InvoiceSchema | null>(null);
    const [previewItems, setPreviewItems] = useState([]);
    const [receiptPreviewOpen, setReceiptPreviewOpen] = useState(false);
    const [selectedReceiptForPreview, setSelectedReceiptForPreview] =
        useState<ReceiptSchema | null>(null);
    const [receiptPreviewItems, setReceiptPreviewItems] = useState<
        InvoiceItemSchema[]
    >([]);

    const handlePreview = async (invoice: InvoiceSchema) => {
        try {
            if (!invoice.id) return;

            const response = await fetch(getInvoiceForShow(invoice.id).url);
            if (response.ok) {
                const invoiceData = await response.json();
                setSelectedInvoiceForPreview(invoiceData);
                setPreviewItems(invoiceData.items ?? []);
            } else {
                setSelectedInvoiceForPreview(invoice);
                setPreviewItems([]);
            }

            setPreviewModalOpen(true);
        } catch (error) {
            console.error('Error fetching invoice items:', error);
            setPreviewItems([]);
            setSelectedInvoiceForPreview(invoice);
            setPreviewModalOpen(true);
        }
    };

    const handleReceiptPreview = async (invoice: InvoiceSchema) => {
        try {
            if (!invoice.receipt_id) {
                return;
            }

            const response = await fetch(
                getReceiptForShow(invoice.receipt_id).url,
            );

            if (!response.ok) {
                return;
            }

            const receipt = await response.json();
            setSelectedReceiptForPreview(receipt);
            setReceiptPreviewItems(receipt.items ?? []);
            setReceiptPreviewOpen(true);
        } catch (error) {
            console.error('Error fetching receipt items:', error);
        }
    };

    const getRowActions = (): ActionType[] => {
        const rowActions: ActionType[] = [];

        if (userPermissions.includes('order view')) rowActions.push('view');
        if (userPermissions.includes('order edit')) {
            rowActions.push('edit');
        }
        // if (userPermissions.includes('order delete')) rowActions.push('delete');

        return rowActions;
    };

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Orders" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            List of Orders
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={ordersForDatatable}
                            actions={[]}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            getRowActions={getRowActions}
                            url={index().url}
                            enableExpand={true}
                            onAction={(action, row) => {
                                if (action === 'add') {
                                    setOpenCreateDialog(true);
                                }

                                const orderId = row?.original.id;

                                if (orderId !== undefined) {
                                    if (action === 'view') {
                                        router.get(show(orderId).url);
                                    } else if (action === 'edit') {
                                        router.get(edit(orderId).url);
                                    } else if (action === 'delete') {
                                        confirm({
                                            title: 'Delete Order',
                                            message: `Are you sure you want to delete order "${row?.original.order_number}"?`,
                                            confirmText: 'Delete',
                                            cancelText: 'Cancel',
                                            onConfirm: () => {
                                                router.delete(
                                                    destroy(orderId).url,
                                                );
                                            },
                                        });
                                    }
                                }
                            }}
                            onRowDoubleClick={(order) => {
                                if (
                                    userPermissions.includes('order edit') &&
                                    order.id
                                ) {
                                    router.get(edit(order.id).url);
                                }
                            }}
                            initialState={{
                                columnFilters: [
                                    {
                                        id: 'quotation_status',
                                        value: defaultOrderIndexQuotationStatusFilters,
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
                                    sales_id: false,
                                    quotation_id: false,
                                    created_at: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="quotation_status"
                                        title="Status"
                                        options={quotationStatuses}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="customer_id"
                                        title="Customer"
                                        options={customers}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="sales_id"
                                        title="Salesperson"
                                        options={salespersons}
                                    />
                                    <DateRangeFilter
                                        table={table}
                                        columnId="created_at"
                                        title="Created At"
                                        quickDate={true}
                                    />
                                </>
                            )}
                            renderSubComponent={(orderRow) => {
                                const invoices =
                                    (
                                        orderRow.original as {
                                            invoices?: InvoiceSchema[];
                                        }
                                    ).invoices ?? [];

                                return (
                                    <div className="space-y-2 pr-2">
                                        <div className="relative flex-1 space-y-2 overflow-hidden rounded-xl border border-sidebar-border/70 bg-white px-3 py-3 md:min-h-min dark:border-sidebar-border">
                                            <h4 className="font-semibold">
                                                Invoices From{' '}
                                                {orderRow.original.order_number}
                                            </h4>
                                            <DataTable
                                                enableExpand={false}
                                                columns={invoiceColumns}
                                                data={invoices}
                                                actions={[]}
                                                getRowActions={(
                                                    invoice: InvoiceSchema,
                                                ) => {
                                                    const rowActions: ActionType[] =
                                                        [];

                                                    if (
                                                        userPermissions.includes(
                                                            'invoice view',
                                                        )
                                                    ) {
                                                        rowActions.push('view');
                                                        rowActions.push(
                                                            'preview',
                                                        );
                                                        rowActions.push(
                                                            'download',
                                                        );
                                                    }

                                                    // if (
                                                    //     userPermissions.includes(
                                                    //         'invoice edit',
                                                    //     )
                                                    // ) {
                                                    //     rowActions.push('edit');
                                                    // }

                                                    if (
                                                        userPermissions.includes(
                                                            'receipt view',
                                                        ) &&
                                                        invoice.status ===
                                                            'paid' &&
                                                        invoice.has_receipt
                                                    ) {
                                                        rowActions.push(
                                                            'receipt-preview',
                                                        );
                                                    }

                                                    if (
                                                        !invoice.is_refund &&
                                                        invoice.status !==
                                                            'refund' &&
                                                        invoice.status !==
                                                            'paid' &&
                                                        invoice.status !==
                                                            'cancelled' &&
                                                        !invoice.has_receipt
                                                    ) {
                                                        rowActions.push(
                                                            'create-receipt',
                                                        );
                                                    }

                                                    return rowActions;
                                                }}
                                                url={invoiceIndex().url}
                                                exportFilename="order"
                                                onAction={(
                                                    action: ActionType,
                                                    invoiceRow,
                                                ) => {
                                                    if (action === 'add') {
                                                        router.get(
                                                            createInvoice().url,
                                                        );
                                                        return;
                                                    }

                                                    const invoice =
                                                        invoiceRow?.original;
                                                    if (!invoice) return;

                                                    const invoiceId =
                                                        invoice.id;
                                                    if (!invoiceId) return;

                                                    if (action === 'view') {
                                                        router.get(
                                                            showInvoice(
                                                                invoiceId,
                                                            ).url,
                                                        );
                                                    } else if (
                                                        action === 'preview'
                                                    ) {
                                                        handlePreview(invoice);
                                                    } else if (
                                                        action ===
                                                        'receipt-preview'
                                                    ) {
                                                        handleReceiptPreview(
                                                            invoice,
                                                        );
                                                    } else if (
                                                        action ===
                                                        'create-receipt'
                                                    ) {
                                                        router.get(
                                                            createReceipt.url(),
                                                            {
                                                                invoice_id:
                                                                    invoice.id,
                                                            },
                                                        );
                                                    } else if (
                                                        action === 'download'
                                                    ) {
                                                        (async () => {
                                                            try {
                                                                const response =
                                                                    await fetch(
                                                                        `/invoice/${invoiceId}/generate-pdf`,
                                                                    );
                                                                if (
                                                                    !response.ok
                                                                ) {
                                                                    throw new Error(
                                                                        'Failed to generate PDF',
                                                                    );
                                                                }

                                                                const blob =
                                                                    await response.blob();
                                                                const url =
                                                                    window.URL.createObjectURL(
                                                                        blob,
                                                                    );

                                                                window.open(
                                                                    url,
                                                                    '_blank',
                                                                );

                                                                const link =
                                                                    document.createElement(
                                                                        'a',
                                                                    );
                                                                link.href = url;
                                                                link.download = `invoice-${invoice.invoice_number}.pdf`;
                                                                document.body.appendChild(
                                                                    link,
                                                                );
                                                                link.click();
                                                                document.body.removeChild(
                                                                    link,
                                                                );
                                                            } catch (error) {
                                                                console.error(
                                                                    'Error opening PDF:',
                                                                    error,
                                                                );
                                                            }
                                                        })();
                                                    } else if (
                                                        action === 'edit'
                                                    ) {
                                                        router.get(
                                                            editInvoice(
                                                                invoiceId,
                                                            ).url,
                                                        );
                                                    } else if (
                                                        action === 'delete'
                                                    ) {
                                                        confirm({
                                                            title: 'Delete Invoice',
                                                            message: `Are you sure you want to delete "${invoice.invoice_number}"?`,
                                                            confirmText:
                                                                'Delete',
                                                            cancelText:
                                                                'Cancel',
                                                            onConfirm: () => {
                                                                router.delete(
                                                                    destroyInvoice(
                                                                        invoiceId,
                                                                    ).url,
                                                                );
                                                            },
                                                        });
                                                    }
                                                }}
                                                initialState={{
                                                    columnFilters: [
                                                        {
                                                            id: 'status',
                                                            value: defaultOrderExpandedInvoiceStatusFilters,
                                                        },
                                                    ],
                                                    pagination: {
                                                        pageSize: 50,
                                                        pageIndex: 0,
                                                    },
                                                    columnVisibility: {
                                                        id: false,
                                                        order_id: false,
                                                        order_number: false,
                                                        quotation_id: false,
                                                        customer_id: false,
                                                        customer_number: false,
                                                        sales_id: false,
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
                                                            options={
                                                                invoiceStatuses
                                                            }
                                                        />
                                                        <DateRangeFilter
                                                            table={table}
                                                            columnId="invoice_date"
                                                            title="Invoice Date"
                                                            quickDate={true}
                                                        />
                                                        <DateRangeFilter
                                                            table={table}
                                                            columnId="due_date"
                                                            title="Due Date"
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
                                );
                            }}
                        />
                    </div>
                </div>
            </AppLayout>

            <ConfirmDialog />

            <OrderCreateDialog
                open={openCreateDialog}
                onOpenChange={setOpenCreateDialog}
                quotationOptions={convertableQuotations}
            />

            {previewModalOpen && selectedInvoiceForPreview && (
                <InvoicePreviewModal
                    data={selectedInvoiceForPreview}
                    items={previewItems}
                    open={previewModalOpen}
                    onOpenChange={setPreviewModalOpen}
                />
            )}

            {receiptPreviewOpen && selectedReceiptForPreview && (
                <ReceiptPreviewModal
                    receipt={selectedReceiptForPreview}
                    items={receiptPreviewItems}
                    open={receiptPreviewOpen}
                    onOpenChange={setReceiptPreviewOpen}
                />
            )}
        </>
    );
}

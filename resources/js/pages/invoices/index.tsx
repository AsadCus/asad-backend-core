import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import SendEmailModal from '@/components/send-email-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, parseDisplayDate } from '@/lib/utils';
import {
    create as createInvoice,
    destroy as destroyInvoice,
    edit as editInvoice,
    getForShow as getInvoiceForShow,
    index as invoiceIndex,
    recreateReceipt as recreateInvoiceReceipt,
    show as showInvoice,
} from '@/routes/invoice';
import {
    create as createReceipt,
    getForShow as getReceiptForShow,
} from '@/routes/receipt';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import {
    InvoiceItemSchema,
    InvoiceSchema,
    statusColors,
    statuses,
} from '../invoices/schema';
import ReceiptPreviewModal from '../receipts/components/receipt-preview-modal';
import { ReceiptSchema } from '../receipts/schema';
import InvoicePreviewModal from './components/invoice-preview-modal';

interface InvoicesProps {
    data: {
        invoicesForDatatable: InvoiceSchema[];
        quotations: OptionType[];
        customers: OptionType[];
        salespersons: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'List of Invoices',
        href: invoiceIndex().url,
    },
];

const invoiceIndexFilterStatuses = statuses.filter(
    (status) => status.value !== 'refund',
);
const defaultInvoiceIndexStatusFilters = invoiceIndexFilterStatuses.map(
    (status) => status.value,
);

const compareNaturalText = (left: unknown, right: unknown): number => {
    return String(left ?? '').localeCompare(String(right ?? ''), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
};

const compareFormattedDate = (left: unknown, right: unknown): number => {
    const leftDate = parseDisplayDate(String(left ?? ''));
    const rightDate = parseDisplayDate(String(right ?? ''));

    if (leftDate && rightDate) {
        return leftDate.getTime() - rightDate.getTime();
    }

    if (leftDate) {
        return 1;
    }

    if (rightDate) {
        return -1;
    }

    return compareNaturalText(left, right);
};

const shouldProceedWithNegativeReceipt = (invoice: InvoiceSchema): boolean => {
    const invoiceAmount = Number(invoice.amount ?? 0);

    if (!Number.isFinite(invoiceAmount) || invoiceAmount >= 0) {
        return true;
    }

    return window.confirm(
        'This invoice has a negative amount. Creating this receipt will update the invoice status to Refund. Continue?',
    );
};

const canCreateReceipt = (invoice: InvoiceSchema): boolean => {
    return (
        Boolean(invoice.id) &&
        !invoice.is_refund &&
        invoice.status !== 'refund' &&
        invoice.status !== 'paid' &&
        invoice.status !== 'cancelled' &&
        !invoice.has_receipt &&
        !invoice.is_package_receipt_locked
    );
};

const canRecreateReceipt = (invoice: InvoiceSchema): boolean => {
    return (
        Boolean(invoice.id) &&
        !invoice.is_refund &&
        invoice.status !== 'refund' &&
        invoice.status !== 'cancelled' &&
        Boolean(invoice.has_receipt)
    );
};

const getCreateReceiptStatusLabel = (invoice: InvoiceSchema): string => {
    if (invoice.is_refund || invoice.status === 'refund') {
        return 'Locked (Refund)';
    }

    if (invoice.status === 'cancelled') {
        return 'Locked (Cancelled)';
    }

    if (invoice.has_receipt) {
        return 'Receipt Created';
    }

    if (invoice.status === 'paid') {
        return 'Locked (Paid)';
    }

    if (invoice.is_package_receipt_locked) {
        const packageStatus = String(
            invoice.package_status ?? '',
        ).toLowerCase();

        return `Locked (${packageStatus || 'package'}: no paid history)`;
    }

    return 'Not Available';
};

export const getInvoiceColumns = (
    openEmailModal?: (id: number, number: string) => void,
): ColumnDef<InvoiceSchema>[] => [
    createSelectColumn<InvoiceSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'package_number',
        header: 'Package Number',
        meta: { exportable: true },
        sortingFn: (rowA, rowB, columnId) =>
            compareNaturalText(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'package_name',
        header: 'Package Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_id',
        header: 'Customer Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer Number',
        meta: { exportable: true },
        sortingFn: (rowA, rowB, columnId) =>
            compareNaturalText(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'quotation_id',
        header: 'Quotation Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'quotation_number',
        header: 'Quotation No.',
        meta: { exportable: true },
        sortingFn: (rowA, rowB, columnId) =>
            compareNaturalText(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'order_id',
        header: 'Order Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'order_number',
        header: 'Order No.',
        meta: { exportable: true },
        sortingFn: (rowA, rowB, columnId) =>
            compareNaturalText(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'invoice_number',
        header: 'Invoice No.',
        meta: { exportable: true },
        sortingFn: (rowA, rowB, columnId) =>
            compareNaturalText(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'invoice_date',
        header: 'Invoice Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        sortingFn: (rowA, rowB, columnId) =>
            compareFormattedDate(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'due_date',
        header: 'Due Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        sortingFn: (rowA, rowB, columnId) =>
            compareFormattedDate(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        id: 'email_sent_at_formatted',
        accessorKey: 'email_sent_at_formatted',
        header: 'Email',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        sortingFn: (rowA, rowB, columnId) =>
            compareFormattedDate(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
        cell: ({ row }) => {
            const invoice = row.original;
            const sentAt = invoice.email_sent_at_formatted;
            const isSent = !!invoice.email_sent_at;
            const canSend = !['cancelled'].includes(invoice.status ?? '');

            if (!canSend) {
                return <span className="text-xs text-muted-foreground">—</span>;
            }

            return (
                <div className="flex flex-col gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant={isSent ? 'outline' : 'default'}
                        className="h-7 px-2.5 text-xs"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (!invoice.id) return;
                            if (openEmailModal) {
                                openEmailModal(
                                    invoice.id,
                                    invoice.invoice_number ?? '',
                                );
                            }
                        }}
                    >
                        {isSent ? 'Resend Email' : 'Send Email'}
                    </Button>
                    {sentAt && (
                        <span className="text-xs text-muted-foreground">
                            {sentAt}
                        </span>
                    )}
                </div>
            );
        },
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
        accessorKey: 'amount',
        header: 'Amount',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.amount),
    },
    {
        id: 'create_receipt',
        header: 'Create Receipt',
        meta: { exportable: false },
        cell: ({ row }) => {
            const invoice = row.original;

            if (!canCreateReceipt(invoice)) {
                return (
                    <span className="text-muted-foreground">
                        {getCreateReceiptStatusLabel(invoice)}
                    </span>
                );
            }

            return (
                <Button
                    type="button"
                    size="sm"
                    variant="default"
                    onClick={(event) => {
                        event.stopPropagation();

                        if (!invoice.id) {
                            return;
                        }

                        if (!shouldProceedWithNegativeReceipt(invoice)) {
                            return;
                        }

                        router.get(createReceipt.url(), {
                            invoice_id: invoice.id,
                        });
                    }}
                >
                    Create
                </Button>
            );
        },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        meta: { exportable: true },
    },
    {
        accessorKey: 'sales_id',
        header: 'Sales Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'sales_name',
        header: 'Salesperson',
        meta: { exportable: true },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        sortingFn: (rowA, rowB, columnId) =>
            compareFormattedDate(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
    {
        accessorKey: 'updated_at',
        header: 'Updated At',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        sortingFn: (rowA, rowB, columnId) =>
            compareFormattedDate(
                rowA.getValue(columnId),
                rowB.getValue(columnId),
            ),
    },
];

export const invoiceColumns: ColumnDef<InvoiceSchema>[] = getInvoiceColumns();

export default function InvoicesIndex({ data }: InvoicesProps) {
    const { invoicesForDatatable, customers, salespersons } = data;
    const { auth, features } = usePage<SharedData>().props;
    const sendEmailEnabled = Boolean(features?.send_email);
    const isSuperadmin = auth.roles.includes('superadmin');
    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    const { confirm, ConfirmDialog } = useConfirmDialog();

    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [selectedInvoiceForPreview, setSelectedInvoiceForPreview] =
        useState<InvoiceSchema | null>(null);
    const [previewItems, setPreviewItems] = useState<InvoiceItemSchema[]>([]);
    const [receiptPreviewOpen, setReceiptPreviewOpen] = useState(false);
    const [selectedReceiptForPreview, setSelectedReceiptForPreview] =
        useState<ReceiptSchema | null>(null);
    const [receiptPreviewItems, setReceiptPreviewItems] = useState<
        InvoiceItemSchema[]
    >([]);

    const [emailModalOpen, setEmailModalOpen] = useState(false);
    const [emailModalData, setEmailModalData] = useState<{
        ids: number[];
        number: string | null;
    }>({ ids: [], number: null });

    const handleOpenEmailModal = (id: number, number: string) => {
        setEmailModalData({ ids: [id], number });
        setEmailModalOpen(true);
    };

    const handleBulkEmailModal = (selectedInvoices: InvoiceSchema[]) => {
        const ids = selectedInvoices
            .map((invoice) => invoice.id)
            .filter((id): id is number => id !== undefined);
        setEmailModalData({ ids, number: null });
        setEmailModalOpen(true);
    };

    const handlePreview = async (invoice: InvoiceSchema) => {
        try {
            if (!invoice.id) return;

            const response = await fetch(getInvoiceForShow(invoice.id).url);
            if (response.ok) {
                const invoice = await response.json();
                setSelectedInvoiceForPreview(invoice);
                setPreviewItems(invoice.items);
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

    const getRowActions = (invoice: InvoiceSchema): ActionType[] => {
        const rowActions: ActionType[] = [];

        if (userPermissions.includes('invoice view')) {
            rowActions.push('view');
            rowActions.push('preview');
            rowActions.push('download');
        }

        // if (userPermissions.includes('invoice edit')) {
        //     rowActions.push('edit');
        // }

        if (
            userPermissions.includes('invoice view') &&
            !['cancelled'].includes(invoice.status ?? '')
        ) {
            if (sendEmailEnabled) {
                rowActions.push('send-email');
            }
            rowActions.push('copy-link');
        }

        if (
            userPermissions.includes('receipt view') &&
            invoice.status === 'paid' &&
            invoice.has_receipt
        ) {
            rowActions.push('receipt-preview');
        }

        if (canCreateReceipt(invoice)) {
            rowActions.push('create-receipt');
        }

        if (canRecreateReceipt(invoice)) {
            rowActions.push('recreate-receipt');
        }

        return rowActions;
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Invoices" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            List of Invoices
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            enableExpand={false}
                            columns={getInvoiceColumns(handleOpenEmailModal)}
                            data={invoicesForDatatable}
                            actions={actions}
                            getRowActions={getRowActions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            onBulkSendEmail={
                                sendEmailEnabled
                                    ? handleBulkEmailModal
                                    : undefined
                            }
                            // groupByRowColorKey="package_number"
                            url={invoiceIndex().url}
                            exportFilename="invoice"
                            onAction={(action: ActionType, invoiceRow) => {
                                if (action === 'add') {
                                    router.get(createInvoice().url);
                                    return;
                                }

                                const invoice = invoiceRow?.original;
                                if (!invoice) return;

                                const invoiceId = invoice.id;
                                if (!invoiceId) return;

                                if (action === 'view') {
                                    router.get(showInvoice(invoiceId).url);
                                } else if (action === 'preview') {
                                    handlePreview(invoice);
                                } else if (action === 'create-receipt') {
                                    if (!canCreateReceipt(invoice)) {
                                        return;
                                    }

                                    if (
                                        !shouldProceedWithNegativeReceipt(
                                            invoice,
                                        )
                                    ) {
                                        return;
                                    }

                                    router.get(createReceipt.url(), {
                                        invoice_id: invoice.id,
                                    });
                                } else if (action === 'recreate-receipt') {
                                    if (!canRecreateReceipt(invoice)) {
                                        return;
                                    }

                                    confirm({
                                        title: 'Recreate Receipt',
                                        message:
                                            'This will remove the current receipt, roll back related financial records, and recalculate payment status. You can create a new receipt after this. Continue?',
                                        confirmText: 'Recreate Receipt',
                                        cancelText: 'Cancel',
                                        variant: 'warning',
                                        onConfirm: () => {
                                            router.post(
                                                recreateInvoiceReceipt(
                                                    invoiceId,
                                                ).url,
                                            );
                                        },
                                    });
                                } else if (action === 'receipt-preview') {
                                    handleReceiptPreview(invoice);
                                } else if (action === 'download') {
                                    (async () => {
                                        try {
                                            const response = await fetch(
                                                `/invoice/${invoiceId}/generate-pdf`,
                                            );
                                            if (!response.ok)
                                                throw new Error(
                                                    'Failed to generate PDF',
                                                );
                                            const blob = await response.blob();
                                            const url =
                                                window.URL.createObjectURL(
                                                    blob,
                                                );

                                            // Open in new tab
                                            window.open(url, '_blank');

                                            // Trigger download
                                            const link =
                                                document.createElement('a');
                                            link.href = url;
                                            link.download = `invoice-${invoice.invoice_number}.pdf`;
                                            document.body.appendChild(link);
                                            link.click();
                                            document.body.removeChild(link);
                                        } catch (error) {
                                            console.error(
                                                'Error opening PDF:',
                                                error,
                                            );
                                        }
                                    })();
                                } else if (action === 'send-email') {
                                    handleOpenEmailModal(
                                        invoiceId,
                                        invoice.invoice_number ?? '',
                                    );
                                } else if (action === 'copy-link') {
                                    // Let modal handle public link generation via single view or we can do it directly.
                                    // Wait, the copy link action will just open the modal, then user clicks "Get Public Link"
                                    // Alternatively, we can just open the modal. For now, open the modal for copy-link too.
                                    handleOpenEmailModal(
                                        invoiceId,
                                        invoice.invoice_number ?? '',
                                    );
                                } else if (action === 'edit') {
                                    router.get(editInvoice(invoiceId).url);
                                } else if (action === 'delete') {
                                    confirm({
                                        title: 'Delete Invoice',
                                        message: `Are you sure you want to delete "${invoice.invoice_number}"?`,
                                        confirmText: 'Delete',
                                        cancelText: 'Cancel',
                                        onConfirm: () => {
                                            router.delete(
                                                destroyInvoice(invoiceId).url,
                                            );
                                        },
                                    });
                                }
                            }}
                            onRowDoubleClick={(invoice) => {
                                if (userPermissions.includes('invoice view')) {
                                    handlePreview(invoice);
                                }
                            }}
                            initialState={{
                                columnFilters: [
                                    {
                                        id: 'status',
                                        value: defaultInvoiceIndexStatusFilters,
                                    },
                                ],
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    package_number: true,
                                    package_name: false,
                                    customer_id: false,
                                    customer_number: false,
                                    customer_name: true,
                                    quotation_id: false,
                                    quotation_number: false,
                                    order_id: false,
                                    order_number: false,
                                    invoice_id: false,
                                    invoice_number: true,
                                    invoice_date: true,
                                    due_date: false,
                                    email_sent_at_formatted: true,
                                    status: true,
                                    amount: true,
                                    create_receipt: true,
                                    description: true,
                                    sales_id: false,
                                    sales_name: true,
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
                                        options={invoiceIndexFilterStatuses}
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
                                            options={salespersons}
                                        />
                                    )}
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
            </AppLayout>

            <ConfirmDialog />

            {/* Preview Modal */}
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

            <SendEmailModal
                open={emailModalOpen}
                onOpenChange={setEmailModalOpen}
                documentType="invoice"
                documentIds={emailModalData.ids}
                documentNumber={emailModalData.number}
            />
        </>
    );
}

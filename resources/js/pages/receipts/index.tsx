import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import {
    destroy as destroyReceipt,
    edit as editReceipt,
    getForShow as getReceiptForShow,
    index as receiptIndex,
} from '@/routes/receipt';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    indexStatusValues as invoiceIndexStatusValues,
    InvoiceItemSchema,
    statusColors as invoiceStatusColors,
    statuses as invoiceStatuses,
} from '../invoices/schema';
import ReceiptPreviewModal from './components/receipt-preview-modal';
import { ReceiptSchema } from './schema';

interface ReceiptsProps {
    data: {
        receiptsForDatatable: ReceiptSchema[];
        invoices: OptionType[];
        customers: OptionType[];
        salespersons: OptionType[];
        paymentMethods: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'List of Receipts',
        href: receiptIndex().url,
    },
];

const defaultReceiptIndexInvoiceStatusFilters = [...invoiceIndexStatusValues];

const formatReceiptAmount = (receipt: ReceiptSchema): string => {
    const amount = Number(receipt.amount ?? 0);

    if (
        Number.isFinite(amount) &&
        amount === 0 &&
        ['refund', 'overpayment_refund'].includes(
            String(receipt.payment_method ?? '').toLowerCase(),
        )
    ) {
        return `-${formatCurrency(0)}`;
    }

    return formatCurrency(receipt.amount);
};

const getColumns = (
    paymentMethods: OptionType[],
): ColumnDef<ReceiptSchema>[] => [
    createSelectColumn<ReceiptSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
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
        accessorKey: 'customer_id',
        header: 'Customer ID',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer Number',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'invoice_id',
        header: 'Invoice Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'invoice_number',
        header: 'Invoice No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'invoice_status',
        header: 'Invoice Status',
        meta: { exportable: true },
        cell: ({ row }) => {
            const status = String(row.original.invoice_status ?? 'draft');
            const label =
                invoiceStatuses.find((option) => option.value === status)
                    ?.label ?? status;
            const color =
                invoiceStatusColors[
                    status as keyof typeof invoiceStatusColors
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
        accessorKey: 'receipt_number',
        header: 'Receipt No.',
        meta: { exportable: true },
    },
    {
        accessorKey: 'receipt_date',
        header: 'Receipt Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        id: 'email_sent_at_formatted',
        accessorKey: 'email_sent_at_formatted',
        header: 'Email',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
        cell: ({ row }) => {
            const receipt = row.original;
            const sentAt = receipt.email_sent_at_formatted;
            const isSent = !!receipt.email_sent_at;

            return (
                <div className="flex flex-col gap-1">
                    <Button
                        type="button"
                        size="sm"
                        variant={isSent ? 'outline' : 'default'}
                        className="h-7 px-2.5 text-xs"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (!receipt.id) return;
                            router.post(`/receipt/${receipt.id}/send-email`);
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
        accessorKey: 'amount',
        header: 'Amount',
        meta: { exportable: true },
        cell: ({ row }) => formatReceiptAmount(row.original),
    },
    {
        accessorKey: 'invoice_description',
        header: 'Description',
        meta: { exportable: true },
    },
    {
        accessorKey: 'payment_method',
        header: 'Payment Method',
        meta: { exportable: true },
        cell: ({ row }) => {
            const paymentMethod = row.original.payment_method ?? '';
            const label =
                paymentMethods.find((s) => s.value === paymentMethod)?.label ||
                paymentMethod;

            return label;
        },
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
        accessorKey: 'reference',
        header: 'Reference',
        meta: { exportable: true },
    },
];

export default function ReceiptsIndex({ data }: ReceiptsProps) {
    const { receiptsForDatatable, customers, salespersons } = data;
    const { auth } = usePage<SharedData>().props;
    const isSuperadmin = auth.roles.includes('superadmin');
    const userPermissions = auth.permissions || [];
    const columns = useMemo(
        () => getColumns(data.paymentMethods ?? []),
        [data.paymentMethods],
    );

    const actions: ActionType[] = [];

    if (userPermissions.includes('receipt edit')) {
        actions.push('edit');
    }

    if (userPermissions.includes('receipt view')) {
        actions.push('preview');
        actions.push('download');
        actions.push('send-email');
    }
    // if (userPermissions.includes('receipt delete')) actions.push('delete');

    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [selectedReceipt, setSelectedReceipt] =
        useState<ReceiptSchema | null>(null);
    const [items, setItems] = useState<InvoiceItemSchema[]>([]);

    const { confirm, ConfirmDialog } = useConfirmDialog();

    const handlePreview = async (receipt: ReceiptSchema) => {
        try {
            if (!receipt.id) return;

            const response = await fetch(getReceiptForShow(receipt.id).url);
            if (response.ok) {
                const receipt = await response.json();
                setSelectedReceipt(receipt);
                setItems(receipt.items);
            } else {
                setSelectedReceipt(receipt);
                setItems([]);
            }

            setPreviewModalOpen(true);
        } catch (error) {
            console.error('Error fetching receipt items:', error);
            setItems([]);
            setSelectedReceipt(receipt);
            setPreviewModalOpen(true);
        }
    };

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="List of Receipts" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            List of Receipts
                        </h2>
                    </div>

                    <div className="relative overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 not-dark:bg-white md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            enableExpand={false}
                            columns={columns}
                            data={receiptsForDatatable}
                            actions={actions}
                            searchFilterMode="outside"
                            columnFilterMode="outside"
                            // groupByRowColorKey="package_number"
                            url={receiptIndex().url}
                            exportFilename="receipts"
                            onAction={(action: ActionType, receiptRow) => {
                                const receipt = receiptRow?.original;
                                if (!receipt) return;

                                const receiptId = receipt.id;
                                if (!receiptId) return;

                                if (action === 'preview') {
                                    handlePreview(receipt);
                                } else if (action === 'edit') {
                                    router.get(editReceipt(receiptId).url);
                                } else if (action === 'download') {
                                    (async () => {
                                        try {
                                            const response = await fetch(
                                                `/receipt/${receiptId}/generate-pdf`,
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
                                            link.download = `receipt-${receipt.receipt_number}.pdf`;
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
                                    const isResend = !!receipt.email_sent_at;
                                    confirm({
                                        title: isResend ? 'Resend Receipt Email' : 'Send Receipt Email',
                                        message: `Are you sure you want to ${isResend ? 'resend' : 'send'} the receipt PDF to the customer's email?`,
                                        confirmText: isResend ? 'Resend Email' : 'Send Email',
                                        cancelText: 'Cancel',
                                        onConfirm: () => {
                                            router.post(
                                                `/receipt/${receiptId}/send-email`,
                                            );
                                        },
                                    });
                                } else if (action === 'delete') {
                                    confirm({
                                        title: 'Delete Receipt',
                                        message: `Are you sure you want to delete "${receipt.receipt_number}"?`,
                                        confirmText: 'Delete',
                                        cancelText: 'Cancel',
                                        onConfirm: () => {
                                            router.delete(
                                                destroyReceipt(receiptId).url,
                                            );
                                        },
                                    });
                                }
                            }}
                            onRowDoubleClick={(receipt) => {
                                if (
                                    userPermissions.includes('receipt edit') &&
                                    receipt.id
                                ) {
                                    router.get(editReceipt(receipt.id).url);

                                    return;
                                }

                                if (userPermissions.includes('receipt view')) {
                                    handlePreview(receipt);
                                }
                            }}
                            initialState={{
                                columnFilters: [
                                    {
                                        id: 'invoice_status',
                                        value: defaultReceiptIndexInvoiceStatusFilters,
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
                                    invoice_id: false,
                                    invoice_number: true,
                                    invoice_description: true,
                                    invoice_status: true,
                                    receipt_number: false,
                                    receipt_date: true,
                                    email_sent_at_formatted: true,
                                    amount: true,
                                    payment_method: true,
                                    sales_id: false,
                                    sales_name: true,
                                    reference: false,
                                    created_at: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="invoice_status"
                                        title="Status"
                                        options={invoiceStatuses}
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
                                        columnId="receipt_date"
                                        title="Receipt Date"
                                        quickDate={true}
                                    />
                                </>
                            )}
                        />
                    </div>
                </div>
            </AppLayout>

            <ConfirmDialog />

            {previewModalOpen && selectedReceipt && (
                <ReceiptPreviewModal
                    receipt={selectedReceipt}
                    items={items}
                    open={previewModalOpen}
                    onOpenChange={setPreviewModalOpen}
                />
            )}
        </>
    );
}

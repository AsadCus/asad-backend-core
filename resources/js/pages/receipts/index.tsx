import { ActionType } from '@/components/action-column';
import { ColumnFilter } from '@/components/column-filter';
import useConfirmDialog from '@/components/confirm-popup';
import { DataTable } from '@/components/data-table';
import { DateRangeFilter } from '@/components/date-range-filter';
import { createSelectColumn } from '@/components/select-column';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import {
    destroy as destroyReceipt,
    getForShow as getReceiptForShow,
    index as receiptIndex,
} from '@/routes/receipt';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { InvoiceItemSchema } from '../invoices/schema';
import { paymentMethods } from '../quotations/schema';
import ReceiptPreviewModal from './components/receipt-preview-modal';
import { ReceiptSchema } from './schema';

interface ReceiptsProps {
    data: {
        data: ReceiptSchema[];
        invoiceOptions: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Receipt',
        href: receiptIndex().url,
    },
];

const columns: ColumnDef<ReceiptSchema>[] = [
    createSelectColumn<ReceiptSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
        meta: { exportable: true },
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
        meta: { exportable: true },
    },
    {
        accessorKey: 'invoice_description',
        header: 'Invoice Description',
        meta: { exportable: true },
    },
    {
        accessorKey: 'receipt_number',
        header: 'Receipt No.',
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
        accessorKey: 'amount',
        header: 'Amount',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.amount),
    },
    {
        accessorKey: 'receipt_date',
        header: 'Receipt Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'payment_method',
        header: 'Payment Method',
        meta: { exportable: true },
        cell: ({ row }) => {
            const paymentMethod = row.original.payment_method ?? 'transfer';
            const label =
                paymentMethods.find((s) => s.value === paymentMethod)?.label ||
                paymentMethod;

            return label;
        },
    },
    {
        accessorKey: 'reference',
        header: 'Reference',
        meta: { exportable: true },
    },
];

export default function ReceiptsIndex({ data }: ReceiptsProps) {
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    if (userPermissions.includes('receipt view')) {
        actions.push('preview');
        actions.push('download');
    }
    if (userPermissions.includes('receipt delete')) actions.push('delete');

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
                <Head title="Receipt" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Receipt</h2>
                    </div>

                    <DataTable
                        enableExpand={false}
                        columns={columns}
                        data={data.data}
                        actions={actions}
                        url={receiptIndex().url}
                        exportFilename="receipts"
                        onAction={(action: ActionType, receiptRow) => {
                            const receipt = receiptRow?.original;
                            if (!receipt) return;

                            const receiptId = receipt.id;
                            if (!receiptId) return;

                            if (action === 'preview') {
                                handlePreview(receipt);
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
                                            window.URL.createObjectURL(blob);

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
                        initialState={{
                            pagination: {
                                pageSize: 50,
                                pageIndex: 0,
                            },
                            columnVisibility: {
                                id: false,
                                invoice_id: false,
                            },
                        }}
                        renderFilter={(table) => (
                            <>
                                <ColumnFilter
                                    table={table}
                                    columnId="invoice_id"
                                    title="Invoice"
                                    options={data.invoiceOptions}
                                />
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

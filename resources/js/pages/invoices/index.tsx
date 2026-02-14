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
    create as createInvoice,
    destroy as destroyInvoice,
    edit as editInvoice,
    getForShow as getInvoiceForShow,
    index as invoiceIndex,
    show as showInvoice,
} from '@/routes/invoice';
import receipt from '@/routes/receipt';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import {
    InvoiceItemSchema,
    InvoiceSchema,
    statusColors,
    statuses,
} from '../invoices/schema';
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

export const invoiceColumns: ColumnDef<InvoiceSchema>[] = [
    createSelectColumn<InvoiceSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
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
    },
    {
        accessorKey: 'customer_name',
        header: 'Customer Name',
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
        accessorKey: 'invoice_number',
        header: 'Invoice No.',
        meta: { exportable: true },
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
        accessorKey: 'description',
        header: 'Description',
        meta: { exportable: true },
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
        meta: { exportable: true },
        cell: ({ row }) => formatCurrency(row.original.amount),
    },
    {
        accessorKey: 'invoice_date',
        header: 'Invoice Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
    },
    {
        accessorKey: 'due_date',
        header: 'Due Date',
        meta: { exportable: true },
        filterFn: 'dateRangeFilter',
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
    {
        id: 'receipt',
        header: 'Receipt',
        meta: { exportable: false },
        cell: ({ row }) => {
            const invoice = row.original;
            if (invoice.status !== 'paid' && invoice.status !== 'cancelled') {
                return (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.get(receipt.create.url(), {
                                invoice_id: invoice.id,
                            });
                        }}
                        className="gap-1 bg-transparent"
                    >
                        <Plus className="h-4 w-4" />
                        Create
                    </Button>
                );
            }
        },
    },
];

export default function InvoicesIndex({ data }: InvoicesProps) {
    const { invoicesForDatatable, quotations, customers, salespersons } = data;
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];

    const actions: ActionType[] = [];

    if (userPermissions.includes('invoice view')) {
        actions.push('preview');
        actions.push('download');
    }
    // if (userPermissions.includes('invoice delete')) actions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    const [previewModalOpen, setPreviewModalOpen] = useState(false);
    const [selectedInvoiceForPreview, setSelectedInvoiceForPreview] =
        useState<InvoiceSchema | null>(null);
    const [previewItems, setPreviewItems] = useState<InvoiceItemSchema[]>([]);

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
                            columns={invoiceColumns}
                            data={invoicesForDatatable}
                            actions={actions}
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
                            initialState={{
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
                                        options={statuses}
                                    />
                                    <ColumnFilter
                                        table={table}
                                        columnId="quotation_id"
                                        title="Quotation"
                                        options={quotations}
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
        </>
    );
}

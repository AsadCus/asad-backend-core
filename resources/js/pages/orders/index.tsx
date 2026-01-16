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
    create as createInvoice,
    destroy as destroyInvoice,
    edit as editInvoice,
    getForShow as getInvoiceForShow,
    index as invoiceIndex,
    show as showInvoice,
} from '@/routes/invoice';
import { destroy, edit, index, show } from '@/routes/order';
import { OptionType, SharedData, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { InvoiceSchema, statuses, statusVariantMap } from '../invoices/schema';
import { paymentPlans } from '../quotations/schema';
import OrderCreateDialog from './components/order-create-dialog';
import { OrderSchema } from './schema';

interface QuotationsProps {
    data: {
        ordersForDatatable: OrderSchema[];
        quotationOptions: OptionType[];
        customerOptions: OptionType[];
        quotationCanOrderOptions: OptionType[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Order',
        href: index().url,
    },
];

const columns: ColumnDef<OrderSchema>[] = [
    createSelectColumn<OrderSchema>(),
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
        accessorKey: 'handover_date',
        header: 'Handover Date',
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
];

const invoiceColumns: ColumnDef<InvoiceSchema>[] = [
    createSelectColumn<InvoiceSchema>(),
    {
        accessorKey: 'id',
        header: 'ID',
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
        accessorKey: 'customer_id',
        header: 'Customer Id',
        meta: { exportable: true },
        filterFn: 'includesValue',
    },
    {
        accessorKey: 'customer_number',
        header: 'Customer Name',
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

            const variant = statusVariantMap[status] ?? 'draft';

            return (
                <Badge variant={variant} className="capitalize">
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
];

export default function OrderIndex({ data }: QuotationsProps) {
    const { ordersForDatatable, quotationOptions, quotationCanOrderOptions } =
        data;
    const { auth } = usePage<SharedData>().props;
    const userPermissions = auth.permissions || [];
    const [openCreateDialog, setOpenCreateDialog] = useState(false);

    const actions: ActionType[] = [];

    if (userPermissions.includes('order create')) actions.push('add');
    if (userPermissions.includes('order view')) actions.push('view');
    if (userPermissions.includes('order edit')) actions.push('edit');
    if (userPermissions.includes('order delete')) actions.push('delete');

    const invoiceActions: ActionType[] = [];

    if (userPermissions.includes('invoice view'))
        invoiceActions.push('preview');
    if (userPermissions.includes('invoice delete'))
        invoiceActions.push('delete');

    const { confirm, ConfirmDialog } = useConfirmDialog();

    return (
        <>
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Quotation" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Order</h2>
                    </div>

                    <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 px-3 py-3 md:min-h-min dark:border-sidebar-border">
                        <DataTable
                            columns={columns}
                            data={ordersForDatatable}
                            actions={actions}
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
                            initialState={{
                                pagination: {
                                    pageSize: 50,
                                    pageIndex: 0,
                                },
                                columnVisibility: {
                                    id: false,
                                    quotation_id: false,
                                    created_at: false,
                                    updated_at: false,
                                },
                            }}
                            renderFilter={(table) => (
                                <>
                                    <ColumnFilter
                                        table={table}
                                        columnId="quotation_id"
                                        title="Quotation"
                                        options={quotationOptions}
                                    />
                                    <DateRangeFilter
                                        table={table}
                                        columnId="handover_date"
                                        title="Handover Date"
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
                                                actions={invoiceActions}
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
                                                        router.get(
                                                            getInvoiceForShow(
                                                                invoiceId,
                                                            ).url,
                                                        );
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
                                                            options={
                                                                data.quotationOptions
                                                            }
                                                        />
                                                        <ColumnFilter
                                                            table={table}
                                                            columnId="customer_id"
                                                            title="Customer"
                                                            options={
                                                                data.customerOptions
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
                quotationOptions={quotationCanOrderOptions}
            />
        </>
    );
}

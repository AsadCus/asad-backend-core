import { z } from 'zod';

export const invoiceItemTaxSchema = z.object({
    _key: z.string().optional(),
    id: z.number().optional(),
    quotation_item_id: z.number().nullable().optional(),
    quotation_extension_master_id: z.number().nullable().optional(),
    name: z.string().nullable().optional(),
    calculation_mode: z.string().nullable().optional(),
    calculation_value: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export const invoiceItemSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    parent_key: z.string().nullable().optional(),
    parent_id: z.number().nullable().optional(),
    quotation_id: z.number().nullable().optional(),
    quotation_number: z.number().nullable().optional(),
    invoice_key: z.string().nullable().optional(),
    invoice_id: z.number().optional(),
    status: z.string().nullable().optional(),
    customer_confirmation_member_id: z.number().nullable().optional(),
    sharing_plan: z.string().nullable().optional(),
    member_name: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    is_header: z.boolean().nullable().optional(),
    quantity: z.union([z.string(), z.number()]).nullable().optional(),
    rate: z.union([z.string(), z.number()]).nullable().optional(),
    taxes: z.array(invoiceItemTaxSchema).optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export type InvoiceItemSchema = z.infer<typeof invoiceItemSchema>;

export const invoiceExtensionSchema = z.object({
    _key: z.string().optional(),
    id: z.number().nullable().optional(),
    quotation_extension_master_id: z.number().nullable().optional(),
    name: z.string().nullable().optional(),
    type: z.string().nullable().optional(),
    calculation_mode: z.string().nullable().optional(),
    calculation_value: z.union([z.string(), z.number()]).nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export const invoiceSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    invoice_number: z.string().nullable().optional(),
    number_format_id: z.number().nullable().optional(),
    customer_id: z.string().nullable().optional(),
    customer_number: z.string().nullable().optional(),
    customer_name: z.string().nullable().optional(),
    package_name: z.string().nullable().optional(),
    package_number: z.string().nullable().optional(),
    package_status: z.string().nullable().optional(),
    is_package_receipt_locked: z.boolean().optional(),
    has_linked_member_paid_history_for_receipt: z.boolean().optional(),
    customer_email: z.email().nullable().optional(),
    customer_contact: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    order_id: z.number().optional(),
    order_number: z.string().nullable().optional(),
    sales_registration_number: z.string().nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    payment_plan: z.string().nullable().optional(),
    payment_method: z.string().nullable().optional(),
    invoice_date: z.string().nullable().optional(),
    due_date: z.string().nullable().optional(),
    status: z.string().nullable().optional(),
    is_refund: z.boolean().optional(),
    has_receipt: z.boolean().optional(),
    receipt_id: z.number().nullable().optional(),
    description: z.string().nullable().optional(),
    extensions: z.array(invoiceExtensionSchema).optional(),
    items: z.array(invoiceItemSchema),
});

export type InvoiceSchema = z.infer<typeof invoiceSchema>;

export const statuses = [
    { label: 'Draft', value: 'draft' },
    { label: 'Outstanding', value: 'outstanding' },
    { label: 'Paid', value: 'paid' },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Void', value: 'cancelled' },
    { label: 'Refund', value: 'refund' },
];

export const indexStatuses = statuses.filter(
    (status) => status.value !== 'cancelled',
);

export const indexStatusValues = indexStatuses.map((status) => status.value);

export const statusColors = {
    draft: 'bg-gray-100 text-gray-800',
    outstanding: 'bg-cyan-100 text-cyan-800',
    issued: 'bg-cyan-100 text-cyan-800',
    paid: 'bg-green-100 text-green-800',
    overdue: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800',
    refund: 'bg-rose-100 text-rose-800',
    expired: 'bg-purple-100 text-purple-800',
};

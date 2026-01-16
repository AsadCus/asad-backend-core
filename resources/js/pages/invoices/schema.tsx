import { z } from 'zod';

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
    description: z.string().nullable().optional(),
    is_header: z.boolean().nullable().optional(),
    is_placement_fee: z.boolean().nullable().optional(),
    quantity: z.union([z.string(), z.number()]).nullable().optional(),
    rate: z.union([z.string(), z.number()]).nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export type InvoiceItemSchema = z.infer<typeof invoiceItemSchema>;

export const invoiceSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    invoice_number: z.string().nullable().optional(),
    customer_id: z.string().nullable().optional(),
    customer_number: z.string().nullable().optional(),
    customer_name: z.string().nullable().optional(),
    customer_email: z.email().nullable().optional(),
    customer_contact: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    order_id: z.number().optional(),
    order_number: z.string().nullable().optional(),
    maid_id: z.string().nullable().optional(),
    maid_name: z.string().nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    payment_plan: z.string().nullable().optional(),
    payment_method: z.string().nullable().optional(),
    invoice_date: z.string().nullable().optional(),
    due_date: z.string().nullable().optional(),
    status: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    items: z.array(invoiceItemSchema),
});

export type InvoiceSchema = z.infer<typeof invoiceSchema>;

export const type = [
    { label: 'Deposit', value: 'deposit' },
    { label: 'Handover', value: 'handover' },
    { label: 'Installment', value: 'installment' },
];

export const statuses = [
    { label: 'Draft', value: 'draft' },
    { label: 'Issued', value: 'issued' },
    { label: 'Paid', value: 'paid' },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Cancelled', value: 'cancelled' },
];

export const statusVariantMap: Record<string, string> = {
    draft: 'draft',
    issued: 'issued',
    paid: 'paid',
    overdue: 'overdue',
    cancelled: 'cancelled',
};

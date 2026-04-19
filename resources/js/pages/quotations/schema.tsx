import { z } from 'zod';
import { noteSchema } from '../notes/schema';
import { quotationItemSchema } from './items/schema';

export const quotationExtensionSchema = z.object({
    _key: z.string().optional(),
    id: z.number().nullable().optional(),
    quotation_extension_master_id: z.number().nullable().optional(),
    name: z.string(),
    type: z.string(),
    calculation_mode: z.string().nullable().optional(),
    calculation_value: z.union([z.string(), z.number()]).nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export const quotationSchema = z.object({
    id: z.number().optional(),
    quotation_number: z.string().nullable().optional(),
    number_format_id: z.number().nullable().optional(),
    quotation_date: z.string().nullable().optional(),
    expiry_date: z.string().nullable().optional(),

    order_id: z.number().nullable().optional(),
    order_number: z.string().nullable().optional(),

    customer_id: z.number().nullable().optional(),
    customer_confirmation_id: z.number().nullable().optional(),
    nric_number: z.string().nullable().optional(),
    customer_number: z.string().nullable().optional(),
    customer_name: z.string().nullable().optional(),
    package_number: z.string().nullable().optional(),
    customer_contact: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    customer_email: z.email().nullable().optional(),

    sales_registration_number: z.string().nullable().optional(),

    description: z.string().nullable().optional(),
    payment_plan: z.string().nullable().optional(),
    package_name: z.string().nullable().optional(),
    package_departure_date: z.string().nullable().optional(),
    package_price_single: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    package_price_double: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    package_price_triple: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    package_price_quad: z.union([z.string(), z.number()]).nullable().optional(),
    package_price_child_with_bed: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    package_price_child_no_bed: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    package_price_infant: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    status: z.string().optional(),
    items_count: z.number().optional(),
    subtotal_amount: z.union([z.string(), z.number()]).nullable().optional(),
    extension_total_amount: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    total_amount: z.union([z.string(), z.number()]).nullable().optional(),
    order_invoices_total_amount: z
        .union([z.string(), z.number()])
        .nullable()
        .optional(),
    reason: z.string().nullable().optional(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),

    items: z.array(quotationItemSchema),
    extensions: z.array(quotationExtensionSchema).optional(),
    invoice_extensions: z.array(quotationExtensionSchema).optional(),

    model: z.string().optional(),
    notes: z.array(noteSchema),

    have_invoices: z.boolean().optional(),
});

export type QuotationSchema = z.infer<typeof quotationSchema>;

export type SetDataFn = <K extends keyof QuotationSchema>(
    key: K,
    value: QuotationSchema[K],
) => void;

export const paymentPlans = [
    // { label: 'Direct', value: 'direct' },
    { label: 'Full Payment', value: 'full' },
    { label: 'Installment', value: 'installment' },
];

export const statuses = [
    { label: 'Draft', value: 'draft' },
    { label: 'Ready', value: 'ready' },
    { label: 'Revised', value: 'revised' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Converted', value: 'converted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Deleted', value: 'expired' },
    { label: 'Void', value: 'cancelled' },
];

export const indexStatuses = statuses.filter(
    (status) => !['rejected', 'expired', 'cancelled'].includes(status.value),
);

export const indexStatusValues = indexStatuses.map((status) => status.value);

export const statusColors = {
    draft: 'bg-gray-100 text-gray-800',
    ready: 'bg-cyan-100 text-cyan-800',
    revised: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    expired: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800',
};

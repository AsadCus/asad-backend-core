import { z } from 'zod';
import { noteSchema } from '../notes/schema';
import { quotationItemSchema } from './items/schema';

export const quotationSchema = z.object({
    id: z.number().optional(),
    quotation_number: z.string().nullable().optional(),
    quotation_date: z.string().nullable().optional(),
    expiry_date: z.string().nullable().optional(),

    order_id: z.number().nullable().optional(),
    order_number: z.string().nullable().optional(),

    customer_id: z.number().nullable().optional(),
    nric_number: z.string().nullable().optional(),
    customer_number: z.string().nullable().optional(),
    customer_name: z.string().nullable().optional(),
    customer_contact: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    customer_email: z.email().nullable().optional(),

    sales_registration_number: z.string().nullable().optional(),

    description: z.string().nullable().optional(),
    payment_plan: z.string().nullable().optional(),
    deposit_type: z.string().nullable().optional(),
    deposit_value: z.union([z.string(), z.number()]).nullable().optional(),
    payment_method: z.string().nullable().optional(),
    status: z.string().optional(),
    items_count: z.number().optional(),
    total_amount: z.union([z.string(), z.number()]).nullable().optional(),
    reason: z.string().nullable().optional(),
    created_at: z.string().optional(),
    updated_at: z.string().optional(),

    items: z.array(quotationItemSchema),

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
    { label: 'Direct', value: 'direct' },
    { label: 'Full Payment', value: 'full' },
    { label: 'Installment', value: 'installment' },
];

export const depositTypes = [
    { label: 'Percentage (%)', value: 'percentage' },
    { label: 'Fixed Amount ($)', value: 'fixed' },
];

export const paymentMethods = [
    { label: 'Cash', value: 'cash' },
    { label: 'Bank Transfer', value: 'transfer' },
    { label: 'Paynow', value: 'paynow' },
];

export const statuses = [
    { label: 'Draft', value: 'draft' },
    { label: 'Ready', value: 'sent' },
    { label: 'Revised', value: 'revised' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Converted', value: 'converted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Deleted', value: 'expired' },
    { label: 'Void', value: 'cancelled' },
];

export const statusColors = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-cyan-100 text-cyan-800',
    revised: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    expired: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800',
};

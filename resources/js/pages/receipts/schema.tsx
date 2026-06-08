import { z } from 'zod';

export const receiptSchema = z.object({
    id: z.number().optional(),
    receipt_number: z.string().nullable().optional(),
    number_format_id: z.number().nullable().optional(),
    order_id: z.number().optional(),
    order_number: z.string().nullable().optional(),
    invoice_id: z.number().optional(),
    invoice_number: z.string().nullable().optional(),
    invoice_status: z.string().nullable().optional(),
    customer_id: z.number().optional(),
    customer_name: z.string().nullable().optional(),
    package_name: z.string().nullable().optional(),
    package_number: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    sales_registration_number: z.string().nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    receipt_date: z.string().nullable().optional(),
    payment_method: z.string().nullable().optional(),
    reference: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    refund_to: z.string().nullable().optional(),
    is_refund_receipt_report: z.boolean().optional(),
    email_sent_at_formatted: z.string().nullable().optional(),
    email_sent_at: z.string().nullable().optional(),
});

export type ReceiptSchema = z.infer<typeof receiptSchema>;

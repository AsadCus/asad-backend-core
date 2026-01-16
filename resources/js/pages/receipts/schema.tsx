import { z } from 'zod';

export const receiptSchema = z.object({
    id: z.number().optional(),
    receipt_number: z.string().nullable().optional(),
    order_id: z.number().optional(),
    order_number: z.string().nullable().optional(),
    invoice_id: z.number().optional(),
    invoice_number: z.string().nullable().optional(),
    customer_id: z.number().optional(),
    customer_name: z.string().nullable().optional(),
    customer_address: z.string().nullable().optional(),
    maid_id: z.number().optional(),
    maid_name: z.string().nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    receipt_date: z.string().nullable().optional(),
    payment_method: z.string().nullable().optional(),
    reference: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
});

export type ReceiptSchema = z.infer<typeof receiptSchema>;

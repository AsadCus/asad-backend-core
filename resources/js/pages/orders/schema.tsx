import { z } from 'zod';
import { invoiceSchema } from '../invoices/schema';
import { quotationItemSchema } from '../quotations/items/schema';

export const orderSchema = z.object({
    id: z.number().optional(),
    order_number: z.string().nullable().optional(),
    number_format_id: z.number().nullable().optional(),
    payment_plan: z.string().optional(),
    deposit_type: z.string().nullable().optional(),
    deposit_value: z.union([z.string(), z.number()]).nullable().optional(),
    invoices: z.array(invoiceSchema),
    total_amount: z.union([z.string(), z.number()]).nullable().optional(),
    items: z.array(quotationItemSchema).optional(),

    quotation_id: z.number().optional(),
    quotation_number: z.string().nullable().optional(),
    has_receipts: z.boolean().optional(),
});

export type OrderSchema = z.infer<typeof orderSchema>;

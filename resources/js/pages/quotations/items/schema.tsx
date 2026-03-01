import { z } from 'zod';

export const quotationItemSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    parent_key: z.string().nullable().optional(),
    parent_id: z.number().nullable().optional(),
    quotation_id: z.number().nullable().optional(),
    quotation_number: z.number().nullable().optional(),
    invoice_id: z.number().nullable().optional(),
    description: z.string().nullable().optional(),
    is_header: z.boolean().nullable().optional(),
    is_optional: z.boolean().nullable().optional(),
    quantity: z.union([z.string(), z.number()]).nullable().optional(),
    rate: z.union([z.string(), z.number()]).nullable().optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export type QuotationItemSchema = z.infer<typeof quotationItemSchema>;

export const quotationItemsSchema = z.object({
    items: z.array(quotationItemSchema),
});

export type QuotationItemsSchema = z.infer<typeof quotationItemsSchema>;

export const type = [
    { label: 'Service', value: 'service' },
    { label: 'Installment', value: 'installment' },
    { label: 'Insurance', value: 'insurance' },
];

import { z } from 'zod';

export const quotationItemTaxSchema = z.object({
    _key: z.string().optional(),
    id: z.number().optional(),
    quotation_item_id: z.number().nullable().optional(),
    quotation_extension_master_id: z.number().nullable().optional(),
    name: z.string().nullable().optional(),
    calculation_mode: z.string().nullable().optional(),
    calculation_value: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export const quotationItemSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    parent_key: z.string().nullable().optional(),
    parent_id: z.number().nullable().optional(),
    quotation_id: z.number().nullable().optional(),
    quotation_number: z.number().nullable().optional(),
    invoice_id: z.number().nullable().optional(),
    customer_confirmation_member_id: z.number().nullable().optional(),
    sharing_plan: z.string().nullable().optional(),
    member_name: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    is_header: z.boolean().nullable().optional(),
    is_optional: z.boolean().nullable().optional(),
    quantity: z.union([z.string(), z.number()]).nullable().optional(),
    rate: z.union([z.string(), z.number()]).nullable().optional(),
    taxes: z.array(quotationItemTaxSchema).optional(),
    amount: z.union([z.string(), z.number()]).nullable().optional(),
    sort_order: z.number().optional(),
});

export type QuotationItemSchema = z.infer<typeof quotationItemSchema>;

export const quotationItemsSchema = z
    .object({
        items: z.array(quotationItemSchema),
    })
    .superRefine((data, ctx) => {
        const rows = data.items ?? [];

        const byKey = new Map<
            string,
            { item: QuotationItemSchema; index: number }
        >();
        const byId = new Map<
            number,
            { item: QuotationItemSchema; index: number }
        >();

        rows.forEach((item, index) => {
            if (item._key) {
                byKey.set(item._key, { item, index });
            }

            if (typeof item.id === 'number') {
                byId.set(item.id, { item, index });
            }
        });

        const hasChild = (target: QuotationItemSchema) =>
            rows.some(
                (row) =>
                    row.parent_key === target._key ||
                    (target.id != null && row.parent_id === target.id),
            );

        rows.forEach((item, index) => {
            if (!item.is_header && hasChild(item)) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['items', index, 'description'],
                    message: 'Only header items can be parent items.',
                });
            }

            const parentMatch =
                (item.parent_key ? byKey.get(item.parent_key) : undefined) ??
                (item.parent_id != null ? byId.get(item.parent_id) : undefined);

            if (parentMatch && !parentMatch.item.is_header) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['items', index, 'parent_key'],
                    message: 'Parent item must be a header item.',
                });
            }
        });
    });

export type QuotationItemsSchema = z.infer<typeof quotationItemsSchema>;

export const type = [
    { label: 'Service', value: 'service' },
    { label: 'Instalment', value: 'installment' },
    { label: 'Insurance', value: 'insurance' },
];

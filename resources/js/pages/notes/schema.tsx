import { z } from 'zod';

export const noteSchema = z.object({
    _key: z.string(),
    id: z.number().optional(),
    model: z.string().nullable().optional(),
    quotation_id: z.number().optional(),
    invoice_id: z.number().optional(),
    receipt_id: z.number().optional(),
    description: z.string().nullable().optional(),
    sort_order: z.number(),
});

export type NoteSchema = z.infer<typeof noteSchema>;

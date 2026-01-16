import { z } from 'zod';
import { invoiceSchema } from './schema';

export const invoiceValidationSchema = invoiceSchema.superRefine((inv, ctx) => {
    if (!inv.description) {
        ctx.addIssue({
            path: ['description'],
            message: 'Description is required',
            code: z.ZodIssueCode.custom,
        });
    }

    if (inv.amount == null || Number(inv.amount) === 0) {
        ctx.addIssue({
            path: ['amount'],
            message: 'Amount is required',
            code: z.ZodIssueCode.custom,
        });
    }

    if (!inv.invoice_date) {
        ctx.addIssue({
            path: ['invoice_date'],
            message: 'Invoice date is required',
            code: z.ZodIssueCode.custom,
        });
    }

    if (!inv.due_date) {
        ctx.addIssue({
            path: ['due_date'],
            message: 'Due date is required',
            code: z.ZodIssueCode.custom,
        });
    }

    if (
        inv.invoice_date &&
        inv.due_date &&
        new Date(inv.due_date) < new Date(inv.invoice_date)
    ) {
        ctx.addIssue({
            path: ['due_date'],
            message: 'Due date must be after or equal to invoice date',
            code: z.ZodIssueCode.custom,
        });
    }

    if (!inv.items?.length) {
        ctx.addIssue({
            path: ['items'],
            message: 'Invoice items are required',
            code: z.ZodIssueCode.custom,
        });
    }
});

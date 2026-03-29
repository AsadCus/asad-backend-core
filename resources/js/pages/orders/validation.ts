import { z } from 'zod';
import { quotationItemsSchema } from '../quotations/items/schema';
import { orderSchema } from './schema';

export const orderValidationSchema = orderSchema.superRefine((data, ctx) => {
    // quotation_id
    if (!data.quotation_id) {
        ctx.addIssue({
            path: ['quotation_id'],
            message: 'Quotation is required',
            code: z.ZodIssueCode.custom,
        });
    }

    // payment_plan
    if (!data.payment_plan) {
        ctx.addIssue({
            path: ['payment_plan'],
            message: 'Payment plan is required',
            code: z.ZodIssueCode.custom,
        });
    } else if (!['direct', 'full', 'installment'].includes(data.payment_plan)) {
        ctx.addIssue({
            path: ['payment_plan'],
            message: 'Invalid payment plan',
            code: z.ZodIssueCode.custom,
        });
    }

    if (data.payment_plan === 'installment') {
        const rawCount = data.installment_invoice_count;
        const parsedCount = Number(rawCount ?? 3);

        if (!Number.isFinite(parsedCount)) {
            ctx.addIssue({
                path: ['installment_invoice_count'],
                message: 'Invoice count must be a number',
                code: z.ZodIssueCode.custom,
            });
        } else if (Math.floor(parsedCount) < 3) {
            ctx.addIssue({
                path: ['installment_invoice_count'],
                message: 'Installment requires at least 3 invoices',
                code: z.ZodIssueCode.custom,
            });
        }
    }

    // invoices
    if (!data.invoices?.length) {
        ctx.addIssue({
            path: ['invoices'],
            message: 'At least one invoice is required',
            code: z.ZodIssueCode.custom,
        });

        return;
    }

    data.invoices.forEach((invoice, invoiceIndex) => {
        const itemValidation = quotationItemsSchema.safeParse({
            items: invoice.items ?? [],
        });

        if (itemValidation.success) {
            return;
        }

        itemValidation.error.issues.forEach((issue) => {
            const relativePath = issue.path.slice(1);

            ctx.addIssue({
                path: ['invoices', invoiceIndex, 'items', ...relativePath],
                message: issue.message,
                code: z.ZodIssueCode.custom,
            });
        });
    });
});

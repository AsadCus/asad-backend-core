import { z } from 'zod';
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

    // handover_date
    if (!data.handover_date) {
        ctx.addIssue({
            path: ['handover_date'],
            message: 'Handover date is required',
            code: z.ZodIssueCode.custom,
        });
    }

    // invoices
    if (!data.invoices?.length) {
        ctx.addIssue({
            path: ['invoices'],
            message: 'At least one invoice is required',
            code: z.ZodIssueCode.custom,
        });
    }
});

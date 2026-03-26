import { z } from 'zod';
import { quotationSchema } from './schema';

export const quotationFormValidationSchema = quotationSchema.superRefine(
    (data, ctx) => {
        const customerId = Number(data.customer_id ?? 0);

        if (!Number.isFinite(customerId) || customerId <= 0) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['customer_id'],
                message: 'Please select a customer.',
            });
        }
    },
);

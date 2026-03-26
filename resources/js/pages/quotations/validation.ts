import { z } from 'zod';
import { quotationSchema } from './schema';

export const quotationFormValidationSchema = quotationSchema.superRefine(
    (data, ctx) => {
        const customerId = Number(data.customer_id ?? 0);
        const customerConfirmationId = Number(data.customer_confirmation_id ?? 0);

        if (!Number.isFinite(customerId) || customerId <= 0) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['customer_id'],
                message: 'Please select a customer.',
            });
        }

        if (
            data.customer_confirmation_id !== null &&
            data.customer_confirmation_id !== undefined &&
            (!Number.isFinite(customerConfirmationId) ||
                customerConfirmationId <= 0)
        ) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['customer_confirmation_id'],
                message: 'Please select a valid customer confirmation.',
            });
        }
    },
);

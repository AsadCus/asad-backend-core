import { z } from 'zod';
import { quotationItemSchema } from './schema';

export const quotationItemValidationSchema = quotationItemSchema.superRefine(
    (item, ctx) => {
        if (!item.description) {
            ctx.addIssue({
                path: ['description'],
                message: 'Description is required',
                code: z.ZodIssueCode.custom,
            });
        }

        if (!item.is_header) {
            if (item.quantity == null) {
                ctx.addIssue({
                    path: ['quantity'],
                    message: 'Quantity is required',
                    code: z.ZodIssueCode.custom,
                });
            }

            if (item.rate == null) {
                ctx.addIssue({
                    path: ['rate'],
                    message: 'Rate is required',
                    code: z.ZodIssueCode.custom,
                });
            }
        }
    },
);

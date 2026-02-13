import { z } from 'zod';
import { generalEnquirySchema } from './schema';

export const generalEnquiryValidationSchema = generalEnquirySchema.superRefine(
    (data, ctx) => {
        // full_name
        if (!data.full_name || data.full_name.trim().length === 0) {
            ctx.addIssue({
                path: ['full_name'],
                message: 'Full name is required',
                code: z.ZodIssueCode.custom,
            });
        } else if (data.full_name.trim().length < 2) {
            ctx.addIssue({
                path: ['full_name'],
                message: 'Full name must be at least 2 characters',
                code: z.ZodIssueCode.custom,
            });
        }

        // mobile
        if (!data.mobile || data.mobile.trim().length === 0) {
            ctx.addIssue({
                path: ['mobile'],
                message: 'Mobile number is required',
                code: z.ZodIssueCode.custom,
            });
        } else if (!/^[+]?[\d\s\-()]+$/.test(data.mobile)) {
            ctx.addIssue({
                path: ['mobile'],
                message: 'Invalid mobile number format',
                code: z.ZodIssueCode.custom,
            });
        }

        // email
        if (!data.email || data.email.trim().length === 0) {
            ctx.addIssue({
                path: ['email'],
                message: 'Email is required',
                code: z.ZodIssueCode.custom,
            });
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            ctx.addIssue({
                path: ['email'],
                message: 'Invalid email address',
                code: z.ZodIssueCode.custom,
            });
        }

        // preferred_destinations
        if (
            !data.preferred_destinations ||
            data.preferred_destinations.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['preferred_destinations'],
                message: 'Preferred destinations are required',
                code: z.ZodIssueCode.custom,
            });
        } else if (data.preferred_destinations.trim().length < 3) {
            ctx.addIssue({
                path: ['preferred_destinations'],
                message: 'Please provide at least 3 characters',
                code: z.ZodIssueCode.custom,
            });
        }

        // preferred_travelling_date
        if (
            !data.preferred_travelling_date ||
            data.preferred_travelling_date.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['preferred_travelling_date'],
                message: 'Preferred travelling date is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_adults
        if (data.no_of_adults !== undefined && data.no_of_adults < 0) {
            ctx.addIssue({
                path: ['no_of_adults'],
                message: 'Number of adults cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // no_of_children
        if (data.no_of_children !== undefined && data.no_of_children < 0) {
            ctx.addIssue({
                path: ['no_of_children'],
                message: 'Number of children cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }
    },
);

import { z } from 'zod';
import { accommodationSchema, packageSchema } from './schema';

export const accommodationValidationSchema = accommodationSchema.superRefine(
    (data, ctx) => {
        // location
        if (!data.location || data.location.trim().length === 0) {
            ctx.addIssue({
                path: ['location'],
                message: 'Location is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // hotel_name
        if (!data.hotel_name || data.hotel_name.trim().length === 0) {
            ctx.addIssue({
                path: ['hotel_name'],
                message: 'Hotel name is required',
                code: z.ZodIssueCode.custom,
            });
        }
    },
);

export const packageValidationSchema = packageSchema.superRefine(
    (data, ctx) => {
        // name
        if (!data.name || data.name.trim().length === 0) {
            ctx.addIssue({
                path: ['name'],
                message: 'Package name is required',
                code: z.ZodIssueCode.custom,
            });
        } else if (data.name.trim().length < 2) {
            ctx.addIssue({
                path: ['name'],
                message: 'Package name must be at least 2 characters',
                code: z.ZodIssueCode.custom,
            });
        }

        // status
        if (!data.status) {
            ctx.addIssue({
                path: ['status'],
                message: 'Status is required',
                code: z.ZodIssueCode.custom,
            });
        }

        // price_single
        if (
            data.price_single !== undefined &&
            data.price_single !== null &&
            data.price_single < 0
        ) {
            ctx.addIssue({
                path: ['price_single'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // price_double
        if (
            data.price_double !== undefined &&
            data.price_double !== null &&
            data.price_double < 0
        ) {
            ctx.addIssue({
                path: ['price_double'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // price_triple
        if (
            data.price_triple !== undefined &&
            data.price_triple !== null &&
            data.price_triple < 0
        ) {
            ctx.addIssue({
                path: ['price_triple'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // price_quad
        if (
            data.price_quad !== undefined &&
            data.price_quad !== null &&
            data.price_quad < 0
        ) {
            ctx.addIssue({
                path: ['price_quad'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // child_with_bed_price
        if (
            data.child_with_bed_price !== undefined &&
            data.child_with_bed_price !== null &&
            data.child_with_bed_price < 0
        ) {
            ctx.addIssue({
                path: ['child_with_bed_price'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // child_no_bed_price
        if (
            data.child_no_bed_price !== undefined &&
            data.child_no_bed_price !== null &&
            data.child_no_bed_price < 0
        ) {
            ctx.addIssue({
                path: ['child_no_bed_price'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // infant_price
        if (
            data.infant_price !== undefined &&
            data.infant_price !== null &&
            data.infant_price < 0
        ) {
            ctx.addIssue({
                path: ['infant_price'],
                message: 'Price cannot be negative',
                code: z.ZodIssueCode.custom,
            });
        }

        // Accommodations validation
        if (data.accommodations && data.accommodations.length > 0) {
            data.accommodations.forEach((accommodation, index) => {
                if (
                    !accommodation.location ||
                    accommodation.location.trim().length === 0
                ) {
                    ctx.addIssue({
                        path: ['accommodations', index, 'location'],
                        message: 'Location is required',
                        code: z.ZodIssueCode.custom,
                    });
                }

                if (
                    !accommodation.hotel_name ||
                    accommodation.hotel_name.trim().length === 0
                ) {
                    ctx.addIssue({
                        path: ['accommodations', index, 'hotel_name'],
                        message: 'Hotel name is required',
                        code: z.ZodIssueCode.custom,
                    });
                }
            });
        }
    },
);

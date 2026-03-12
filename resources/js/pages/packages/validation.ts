import { z } from 'zod';
import {
    accommodationSchema,
    flightSchema,
    officialSchema,
    packageSchema,
} from './schema';

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

export const flightValidationSchema = flightSchema.superRefine((data, ctx) => {
    if (!data.from || data.from.trim().length === 0) {
        ctx.addIssue({
            path: ['from'],
            message: 'From is required',
            code: z.ZodIssueCode.custom,
        });
    }
    if (!data.to || data.to.trim().length === 0) {
        ctx.addIssue({
            path: ['to'],
            message: 'To is required',
            code: z.ZodIssueCode.custom,
        });
    }
});

export const officialValidationSchema = officialSchema.superRefine(
    (data, ctx) => {
        if (!data.type || data.type.trim().length === 0) {
            ctx.addIssue({
                path: ['type'],
                message: 'Type is required',
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

        // Price fields — coerce to number before comparison
        const priceFields = [
            'price_single',
            'price_double',
            'price_triple',
            'price_quad',
            'child_with_bed_price',
            'child_no_bed_price',
            'infant_price',
        ] as const;

        for (const field of priceFields) {
            const val = data[field];
            if (val !== undefined && val !== null && Number(val) < 0) {
                ctx.addIssue({
                    path: [field],
                    message: 'Price cannot be negative',
                    code: z.ZodIssueCode.custom,
                });
            }
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

        // Flights validation
        if (data.flights && data.flights.length > 0) {
            data.flights.forEach((flight, index) => {
                if (!flight.from || flight.from.trim().length === 0) {
                    ctx.addIssue({
                        path: ['flights', index, 'from'],
                        message: 'From is required',
                        code: z.ZodIssueCode.custom,
                    });
                }
                if (!flight.to || flight.to.trim().length === 0) {
                    ctx.addIssue({
                        path: ['flights', index, 'to'],
                        message: 'To is required',
                        code: z.ZodIssueCode.custom,
                    });
                }
            });
        }

        // Officials validation
        if (data.officials && data.officials.length > 0) {
            data.officials.forEach((official, index) => {
                if (!official.type || official.type.trim().length === 0) {
                    ctx.addIssue({
                        path: ['officials', index, 'type'],
                        message: 'Type is required',
                        code: z.ZodIssueCode.custom,
                    });
                }
            });
        }
    },
);

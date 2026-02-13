import { z } from 'zod';
import { manifestSchema, travelerSchema } from './schema';

export const travelerValidationSchema = travelerSchema.superRefine(
    (data, ctx) => {
        // sn
        if (!data.sn || data.sn < 1) {
            ctx.addIssue({
                path: ['sn'],
                message: 'S/N is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // name_as_per_passport
        if (
            !data.name_as_per_passport ||
            data.name_as_per_passport.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['name_as_per_passport'],
                message: 'Name as per passport is required.',
                code: z.ZodIssueCode.custom,
            });
        }
    },
);

export const manifestValidationSchema = manifestSchema.superRefine(
    (data, ctx) => {
        // package_id
        if (!data.package_id || data.package_id < 1) {
            ctx.addIssue({
                path: ['package_id'],
                message: 'Package is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // reference_number
        if (
            !data.reference_number ||
            data.reference_number.trim().length === 0
        ) {
            ctx.addIssue({
                path: ['reference_number'],
                message: 'Reference number is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // departure_date
        if (!data.departure_date || data.departure_date.trim().length === 0) {
            ctx.addIssue({
                path: ['departure_date'],
                message: 'Departure date is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // return_date
        if (!data.return_date || data.return_date.trim().length === 0) {
            ctx.addIssue({
                path: ['return_date'],
                message: 'Return date is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // Travelers validation
        if (data.travelers && data.travelers.length > 0) {
            data.travelers.forEach((traveler, index) => {
                if (!traveler.sn || traveler.sn < 1) {
                    ctx.addIssue({
                        path: ['travelers', index, 'sn'],
                        message: 'S/N is required.',
                        code: z.ZodIssueCode.custom,
                    });
                }

                if (
                    !traveler.name_as_per_passport ||
                    traveler.name_as_per_passport.trim().length === 0
                ) {
                    ctx.addIssue({
                        path: ['travelers', index, 'name_as_per_passport'],
                        message: 'Name as per passport is required.',
                        code: z.ZodIssueCode.custom,
                    });
                }
            });
        }
    },
);

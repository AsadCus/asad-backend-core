import { z } from 'zod';
import { manifestSchema, travelerSchema, type TravelerSchema } from './schema';

export const travelerValidationSchema = travelerSchema.superRefine(
    (data, ctx) => {
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
        const travelers =
            (data.travelers as TravelerSchema[] | undefined) ?? [];

        // package_id
        if (!data.package_id || data.package_id < 1) {
            ctx.addIssue({
                path: ['package_id'],
                message: 'Package is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        // Travelers validation
        if (travelers.length > 0) {
            travelers.forEach((traveler, index) => {
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

import { z } from 'zod';
import { manifestSchema, memberSchema, type MemberSchema } from './schema';

export const memberValidationSchema = memberSchema.superRefine(
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
        const members =
            (data.members as MemberSchema[] | undefined) ?? [];
        const packageId = Number(data.package_id ?? 0);
        const inChargeOfficialId = Number(data.in_charge_official_id ?? 0);

        // package_id
        if (!Number.isFinite(packageId) || packageId < 1) {
            ctx.addIssue({
                path: ['package_id'],
                message: 'Package is required.',
                code: z.ZodIssueCode.custom,
            });
        }

        if (
            data.in_charge_official_id !== null &&
            data.in_charge_official_id !== undefined &&
            (!Number.isFinite(inChargeOfficialId) || inChargeOfficialId < 1)
        ) {
            ctx.addIssue({
                path: ['in_charge_official_id'],
                message: 'Official in charge is invalid.',
                code: z.ZodIssueCode.custom,
            });
        }

        // Members validation
        if (members.length > 0) {
            members.forEach((member, index) => {
                if (
                    !member.name_as_per_passport ||
                    member.name_as_per_passport.trim().length === 0
                ) {
                    ctx.addIssue({
                        path: ['members', index, 'name_as_per_passport'],
                        message: 'Name as per passport is required.',
                        code: z.ZodIssueCode.custom,
                    });
                }
            });
        }
    },
);

import { customerGroupFormSchema, customerMemberSchema } from './schema';

export const customerMemberValidationSchema = customerMemberSchema.superRefine(
    (data, ctx) => {
        if (!data.name || data.name.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['name'],
                message: 'Full name is required.',
            });
        }

        if (!data.email || data.email.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['email'],
                message: 'Email is required.',
            });
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            ctx.addIssue({
                code: 'custom',
                path: ['email'],
                message: 'Please enter a valid email address.',
            });
        }

        if (!data.contact_number || data.contact_number.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['contact_number'],
                message: 'Contact number is required.',
            });
        }

        if (!data.nric_number || data.nric_number.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['nric_number'],
                message: 'NRIC number is required.',
            });
        }

        if (!data.address || data.address.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['address'],
                message: 'Residential address is required.',
            });
        }

        if (!data.nationality || data.nationality.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['nationality'],
                message: 'Nationality is required.',
            });
        }

        if (!data.passport_number || data.passport_number.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['passport_number'],
                message: 'Passport number is required.',
            });
        }

        if (
            !data.passport_issue_date ||
            data.passport_issue_date.trim().length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['passport_issue_date'],
                message: 'Passport issue date is required.',
            });
        }

        if (
            !data.passport_expiry_date ||
            data.passport_expiry_date.trim().length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['passport_expiry_date'],
                message: 'Passport expiry date is required.',
            });
        }

        if (
            !data.passport_place_of_issue ||
            data.passport_place_of_issue.trim().length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['passport_place_of_issue'],
                message: 'Passport place of issue is required.',
            });
        }

        if (!data.gender || data.gender.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['gender'],
                message: 'Gender is required.',
            });
        }

        if (!data.marital_status || data.marital_status.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['marital_status'],
                message: 'Marital status is required.',
            });
        }

        if (!data.date_of_birth || data.date_of_birth.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['date_of_birth'],
                message: 'Date of birth is required.',
            });
        }

        if (!data.place_of_birth || data.place_of_birth.trim().length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['place_of_birth'],
                message: 'Place of birth is required.',
            });
        }
    },
);

export const customerGroupFormValidationSchema =
    customerGroupFormSchema.superRefine((data, ctx) => {
        if (
            !data.date_of_application ||
            data.date_of_application.trim().length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['date_of_application'],
                message: 'Date of application is required.',
            });
        }

        if (!data.members || data.members.length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['members'],
                message: 'At least one member is required.',
            });
        }

        // Validate each member
        data.members?.forEach((member, index) => {
            const result = customerMemberValidationSchema.safeParse(member);
            if (!result.success) {
                result.error.issues.forEach((issue) => {
                    ctx.addIssue({
                        ...issue,
                        path: ['members', index, ...issue.path],
                    });
                });
            }
        });
    });

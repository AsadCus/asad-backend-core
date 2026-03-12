import { customerConfirmationFormSchema, customerSchema } from './schema';

export const customerValidationSchema = customerSchema.superRefine(
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
    },
);

export const customerConfirmationFormValidationSchema =
    customerConfirmationFormSchema.superRefine((data, ctx) => {
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
            const result = customerValidationSchema.safeParse(member);
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

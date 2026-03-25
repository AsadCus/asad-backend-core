import { z } from 'zod';

const baseUserSchema = z
    .object({
        id: z.number().optional(),
        customer_id: z.number().nullable().optional(),
        customer_number: z.string().optional(),
        name: z.string().min(1, 'The name field is required.'),
        email: z.email().min(1, 'The email field is required.'),
        password: z.string().optional(),
        password_confirmation: z.string().optional(),
        send_email: z.boolean().optional(),
        contact: z.string().optional(),
        role: z.enum(['admin', 'sales', 'supplier', 'customer']),
        country_id: z.string().optional(),
        country_name: z.string().optional(),
        branch_id: z.string().optional(),
        branch_name: z.string().optional(),
        company_name: z.string().optional(),
        nric_number: z.string().optional(),
        address: z.string().optional(),
        nationality: z.string().optional(),
        passport_number: z.string().optional(),
        passport_issue_date: z.string().optional(),
        passport_expiry_date: z.string().optional(),
        passport_place_of_issue: z.string().optional(),
        gender: z.string().optional(),
        marital_status: z.string().optional(),
        date_of_birth: z.string().optional(),
        place_of_birth: z.string().optional(),
        first_time_umrah: z.boolean().nullable().optional(),
        has_chronic_disease: z.boolean().nullable().optional(),
        chronic_disease_details: z.string().nullable().optional(),
        passport_file: z.file().optional(),
        photo_file: z.file().optional(),
        passport_path: z.string().nullable().optional(),
        photo_path: z.string().nullable().optional(),
        commission: z.union([z.string(), z.number()]).nullable().optional(),
        handled_by: z.string().nullable().optional(),
        handler_name: z.string().optional(),
        supplier_id: z.number().nullable().optional(),
        total_cost_of_maid: z
            .union([z.string(), z.number()])
            .nullable()
            .optional(),
        registration_number: z.string().optional(),
        is_active: z.boolean().nullable().optional(),
    })
    .superRefine((data, ctx) => {
        if (data.password || data.password_confirmation) {
            if (data.password !== data.password_confirmation) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['password_confirmation'],
                    message: 'Passwords do not match.',
                });
            }
        }

        if (data.role === 'sales' && !data.branch_id?.trim()) {
            ctx.addIssue({
                code: 'custom',
                path: ['branch_id'],
                message: 'The branch field is required for sales.',
            });
        }

        if (data.role === 'supplier') {
            if (!data.company_name?.trim()) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['company_name'],
                    message: 'The company name field is required.',
                });
            }

            if (!data.address?.trim()) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['address'],
                    message: 'The address field is required.',
                });
            }
        }
    });

export const userSchema = baseUserSchema;

export type UserSchema = z.infer<typeof userSchema>;

export type UserFormMode = 'create' | 'edit' | 'view';

export function validateUserData(data: UserSchema, mode: UserFormMode) {
    return userSchema
        .superRefine((currentData, ctx) => {
            const requiresManualPassword =
                currentData.role !== 'customer' &&
                currentData.role !== 'supplier';

            if (
                mode === 'create' &&
                requiresManualPassword &&
                !currentData.password?.trim()
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['password'],
                    message: 'The password field is required.',
                });
            }
        })
        .safeParse(data);
}

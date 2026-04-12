import { z } from 'zod';

const baseUserSchema = z
    .object({
        id: z.number().optional(),
        customer_id: z.number().nullable().optional(),
        customer_number: z.string().optional(),
        number_format_id: z.number().nullable().optional(),
        name: z.string().min(1, 'The name field is required.'),
        email: z.email().min(1, 'The email field is required.'),
        password: z.string().optional(),
        password_confirmation: z.string().optional(),
        send_email: z.boolean().optional(),
        contact: z.string().optional(),
        role: z.enum(['admin', 'sales', 'operations', 'customer']),
        scope_mode: z.enum(['country', 'branch']).optional(),
        scope_ids: z.array(z.string()).optional(),
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

        const scopeMode = data.scope_mode ?? 'country';
        const scopeIds = (data.scope_ids ?? []).filter((id) => id.trim().length > 0);

        if (
            (data.role === 'admin' || data.role === 'sales' || data.role === 'operations') &&
            scopeIds.length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['scope_ids'],
                message:
                    scopeMode === 'branch'
                        ? 'At least one branch is required.'
                        : 'At least one country is required.',
            });
        }
    });

export const userSchema = baseUserSchema;

export type UserSchema = z.infer<typeof userSchema>;

export type UserFormMode = 'create' | 'edit' | 'view';

export function validateUserData(data: UserSchema, mode: UserFormMode) {
    return userSchema
        .superRefine((currentData, ctx) => {
            const requiresManualPassword = currentData.role !== 'customer';

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

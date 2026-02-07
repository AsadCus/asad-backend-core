import { z } from 'zod';

export const userSchema = z
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
        branch_id: z.string().optional(),
        branch_name: z.string().optional(),
        company_name: z.string().optional(),
        nric_number: z.string().optional(),
        address: z.string().optional(),
        commission: z.union([z.string(), z.number()]).nullable().optional(),
        age_preferences: z.array(z.string()).optional(),
        country_preferences: z.array(z.string()).optional(),
        experience_preferences: z.array(z.string()).optional(),
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
    .refine(
        (data) => {
            if (data.password || data.password_confirmation) {
                return data.password === data.password_confirmation;
            }
            return true;
        },
        { message: 'Passwords do not match', path: ['password_confirmation'] },
    );

export type UserSchema = z.infer<typeof userSchema>;
